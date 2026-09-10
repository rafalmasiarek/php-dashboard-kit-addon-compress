<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitCompress;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Stream;

/**
 * Compresses HTML responses by collapsing whitespace and stripping comments.
 *
 * Blocks where whitespace is significant are extracted before compression
 * and restored afterwards: <script>, <style>, <pre>, <textarea>.
 *
 * Only responses with a text/html Content-Type are processed.
 * All other responses (JSON, redirects, binary) pass through untouched.
 *
 * @package rafalmasiarek\DashboardKitCompress
 */
final class HtmlCompressMiddleware implements MiddlewareInterface
{
    /**
     * Merged configuration.
     *
     * @var array{enabled: bool, exclude: list<string>}
     */
    private array $config;

    /**
     * @param array{enabled?: bool, exclude?: list<string>} $config
     *   enabled — set to false to disable compression entirely (e.g. on dev).
     *   exclude — list of path patterns to skip. Plain strings are matched as
     *             path prefixes; strings prefixed with ~ are treated as regexes
     *             (e.g. '~^/api/~').
     */
    public function __construct(array $config = [])
    {
        $this->config = \array_merge([
            'enabled' => true,
            'exclude' => [],
        ], $config);
    }

    /**
     * Process the request and compress the HTML response body.
     *
     * @param  ServerRequestInterface  $request
     * @param  RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->config['enabled']) {
            return $response;
        }

        $path = $request->getUri()->getPath();
        foreach ($this->config['exclude'] as $pattern) {
            $matched = \str_starts_with($pattern, '~')
                ? (bool) \preg_match($pattern, $path)
                : \str_starts_with($path, $pattern);

            if ($matched) {
                return $response;
            }
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType !== '' && !\str_contains($contentType, 'text/html')) {
            return $response;
        }

        $bodyRaw = $response->getBody();
        $body    = $bodyRaw !== null ? (string) $bodyRaw : '';
        if ($body === '') {
            return $response;
        }

        $compressed = self::compress($body);

        $resource = \fopen('php://temp', 'r+');
        \fwrite($resource, $compressed);
        \rewind($resource);

        return $response->withBody(new Stream($resource));
    }

    /**
     * Compress an HTML string.
     *
     * Steps:
     *   1. Extract <!-- compress:off -->...<!-- compress:on --> blocks (highest priority,
     *      content is preserved byte-for-byte including any tags inside).
     *   2. Extract <script>, <style>, <pre>, <textarea> blocks into placeholders.
     *   3. Strip HTML comments (IE conditional comments <!--[if ...]> are preserved).
     *   4. Collapse whitespace between adjacent tags.
     *   5. Collapse runs of whitespace within text/attribute content to a single space.
     *   6. Remove whitespace at placeholder boundaries (gaps left by steps 4/5).
     *   7. Restore all extracted blocks.
     *
     * @param  string $html Raw HTML.
     * @return string Compressed HTML.
     */
    private static function compress(string $html): string
    {
        $placeholders = [];
        $index        = 0;

        $extract = static function (string $pattern) use (&$html, &$placeholders, &$index): void {
            $result = \preg_replace_callback(
                $pattern,
                static function (array $m) use (&$placeholders, &$index): string {
                    $key                = "\x02PRESERVE{$index}\x03";
                    $placeholders[$key] = $m[0];
                    $index++;
                    return $key;
                },
                $html
            );
            if (\is_string($result)) {
                $html = $result;
            }
        };

        // compress:off blocks must be extracted first so that any <script>/<style>
        // tags inside them are not separately extracted and re-compressed.
        $extract('/<!--\s*compress:off\s*-->.*?<!--\s*compress:on\s*-->/is');

        // Blocks where whitespace or raw content must be preserved verbatim.
        $extract('/<(script|style|pre|textarea)\b[^>]*>.*?<\/\1>/is');

        if (!\is_string($html)) {
            return $html ?? '';
        }

        // Strip HTML comments but preserve IE conditional comments <!--[if ...]>.
        $html = \preg_replace('/<!--(?!\[if\s).*?-->/s', '', $html) ?? $html;

        // Remove whitespace between adjacent tags.
        $html = \preg_replace('/>\s+</', '><', $html) ?? $html;

        // Collapse remaining runs of whitespace to a single space.
        $html = \preg_replace('/\s{2,}/', ' ', $html) ?? $html;

        // Remove whitespace at placeholder boundaries — >\s+< cannot see through
        // placeholders, so gaps like "> \x02" or "\x03 <" survive the passes above.
        $html = \preg_replace('/>\s+\x02/',    ">\x02",    $html) ?? $html;
        $html = \preg_replace('/\x03\s+</',    "\x03<",    $html) ?? $html;
        $html = \preg_replace('/\x03\s+\x02/', "\x03\x02", $html) ?? $html;

        $html = \trim($html);

        return \strtr($html, $placeholders);
    }
}
