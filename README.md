# dashboard-kit-addon-compress
[![Packagist Version](https://img.shields.io/packagist/v/rafalmasiarek/dashboard-kit-addon-compress)](https://packagist.org/packages/rafalmasiarek/dashboard-kit-addon-compress)
[![Total Downloads](https://img.shields.io/packagist/dt/rafalmasiarek/dashboard-kit-addon-compress)](https://packagist.org/packages/rafalmasiarek/dashboard-kit-addon-compress)
[![PHP](https://img.shields.io/packagist/php-v/rafalmasiarek/dashboard-kit-addon-compress)](https://packagist.org/packages/rafalmasiarek/dashboard-kit-addon-compress)
[![License](https://img.shields.io/badge/license-BUSL--1.1-red)](https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress/blob/main/LICENSE)

HTML output compression middleware for [rafalmasiarek/dashboard-kit](https://github.com/rafalmasiarek/php-dashboard-kit).

Collapses whitespace and strips HTML comments from every `text/html` response. Sensitive blocks (`<script>`, `<style>`, `<pre>`, `<textarea>`) are preserved verbatim.




## Installation

```bash
composer require rafalmasiarek/dashboard-kit-addon-compress
```

Requires PHP 8.2+, `slim/slim ^4`, and `slim/psr7 ^1`.

---

## Usage

Configure the addon in `Dashboard::create()` under the `compress` key, then register it:

```php
$dashboard = Dashboard::create(__DIR__ . '/../', [
    // ...
    'compress' => [
        'enabled' => true,
        'exclude' => ['/api/'],
    ],
]);

CompressAddon::register($dashboard->getApp(), $dashboard->getContainer());
```

### Configuration options

| Option | Type | Default | Description |
|---|---|---|---|
| `enabled` | `bool` | `true` | Set to `false` to disable compression entirely (e.g. on dev) |
| `exclude` | `string[]` | `[]` | Request paths matching any pattern pass through unmodified |

`exclude` supports two pattern types:

```php
'exclude' => [
    '/api/',        // prefix match — any path starting with /api/
    '~^/debug/~',  // regex match  — strings starting with ~ are treated as regexes
],
```

---

## What gets compressed

- Whitespace between adjacent tags (`> <` → `><`)
- Runs of two or more whitespace characters collapsed to a single space
- HTML comments stripped (except IE conditional comments `<!--[if ...]>`)

---

## What is preserved

The following blocks are extracted before compression and restored afterwards — their content is never modified:

| Block | Reason |
|---|---|
| `<script>...</script>` | JavaScript is whitespace-sensitive |
| `<style>...</style>` | CSS is whitespace-sensitive |
| `<pre>...</pre>` | Preformatted text, whitespace is meaningful |
| `<textarea>...</textarea>` | User-visible whitespace |

---

## Opting out of compression

Wrap any section of HTML with `<!-- compress:off -->` / `<!-- compress:on -->` to exclude it from compression:

```html
<!-- compress:off -->
<pre class="my-indented-block">
    line one
    line two
</pre>
<!-- compress:on -->
```

The entire block (including the directive comments) is preserved byte-for-byte. Any `<script>` or `<style>` tags inside a `compress:off` block are also preserved as part of that block and are not separately extracted.

---

## Content-type filtering

Only responses where `Content-Type` contains `text/html` are processed. JSON, redirects (empty body), binary assets, and all other response types pass through untouched.


## Index

### Classes

- [`CompressAddon`](#compressaddon) — `rafalmasiarek\DashboardKitCompress`
- [`HtmlCompressMiddleware`](#htmlcompressmiddleware) — `rafalmasiarek\DashboardKitCompress`

## `CompressAddon`

*final class* — `rafalmasiarek\DashboardKitCompress\CompressAddon` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress/blob/main/src/CompressAddon.php#L25)

Registers HTML output compression for a dashboard-kit application. Adds HtmlCompressMiddleware to the Slim application. Configuration is read from the 'compress' key of app.config in the container: Dashboard::create(__DIR__ . '/../', [ 'compress' => [ 'enabled' => true, 'exclude' => ['/api/', '~^/debug/~'], ], ]);

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`register()`](#compressaddon-register) | public static | `void` | Add the HTML compression middleware to the Slim application. |

<details>
<summary><code>register()</code></summary>

#### `CompressAddon register()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress/blob/main/src/CompressAddon.php#L34)

```php
public static function register(App $app, ContainerInterface $container): void
```

Add the HTML compression middleware to the Slim application.

| Param | Type | Description |
|---|---|---|
| `$app` | `App` | Slim application instance. |
| `$container` | `ContainerInterface` | PHP-DI container. |

</details>

## `HtmlCompressMiddleware`

*final class* — `rafalmasiarek\DashboardKitCompress\HtmlCompressMiddleware` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress/blob/main/src/HtmlCompressMiddleware.php#L24)

Compresses HTML responses by collapsing whitespace and stripping comments. Blocks where whitespace is significant are extracted before compression and restored afterwards: <script>, <style>, <pre>, <textarea>. Only responses with a text/html Content-Type are processed. All other responses (JSON, redirects, binary) pass through untouched.

implements `MiddlewareInterface`

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$config` | `array{enabled: bool, exclude: list<string>}` | private | Merged configuration. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#htmlcompressmiddleware-__construct) | public | — | — |
| [`process()`](#htmlcompressmiddleware-process) | public | `ResponseInterface` | Process the request and compress the HTML response body. |
| [`compress()`](#htmlcompressmiddleware-compress) | private static | `string` | Compress an HTML string. Steps: 1. Extract <!-- compress:off -->...<!-- compress:on --> blocks (highest priority, content is preserved byte-for-byte including any tags inside). 2. Extract <script>, <style>, <pre>, <textarea> blocks into placeholders. 3. Strip HTML comments (IE conditional comments <!--[if ...]> are preserved). 4. Collapse whitespace between adjacent tags. 5. Collapse runs of whitespace within text/attribute content to a single space. 6. Remove whitespace at placeholder boundaries (gaps left by steps 4/5). 7. Restore all extracted blocks. |

<details>
<summary><code>__construct()</code></summary>

#### `HtmlCompressMiddleware __construct()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress/blob/main/src/HtmlCompressMiddleware.php#L40)

```php
public function __construct(array $config)
```

| Param | Type | Description |
|---|---|---|
| `$config` | `array{enabled?: bool, exclude?: list<string>}` | enabled — set to false to disable compression entirely (e.g. on dev). exclude — list of path patterns to skip. Plain strings are matched as path prefixes; strings prefixed with ~ are treated as regexes (e.g. '~^/api/~'). |

</details>

<details>
<summary><code>process()</code></summary>

#### `HtmlCompressMiddleware process()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress/blob/main/src/HtmlCompressMiddleware.php#L55)

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```

Process the request and compress the HTML response body.

| Param | Type | Description |
|---|---|---|
| `$request` | `ServerRequestInterface` | — |
| `$handler` | `RequestHandlerInterface` | — |

**Returns:** `ResponseInterface`

</details>

<details>
<summary><code>compress()</code></summary>

#### `HtmlCompressMiddleware compress()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress/blob/main/src/HtmlCompressMiddleware.php#L110)

```php
private static function compress(string $html): string
```

Compress an HTML string. Steps: 1. Extract <!-- compress:off -->...<!-- compress:on --> blocks (highest priority, content is preserved byte-for-byte including any tags inside). 2. Extract <script>, <style>, <pre>, <textarea> blocks into placeholders. 3. Strip HTML comments (IE conditional comments <!--[if ...]> are preserved). 4. Collapse whitespace between adjacent tags. 5. Collapse runs of whitespace within text/attribute content to a single space. 6. Remove whitespace at placeholder boundaries (gaps left by steps 4/5). 7. Restore all extracted blocks.

| Param | Type | Description |
|---|---|---|
| `$html` | `string` | Raw HTML. |

**Returns:** `string` — Compressed HTML.

</details>



## License

![License: BUSL-1.1](https://img.shields.io/badge/license-BUSL--1.1-red)

Business Source License 1.1.

Source available; commercial use restricted until 2099-01-01.


For alternative licensing, contact: https://masiarek.pl/contact/?af_subject=Commercial+license+%E2%80%94+dashboard-kit-addon-compress&af_message=Hello%2C+I+am+interested+in+a+commercial+license+for+dashboard-kit-addon-compress.



See [LICENSE](LICENSE) for full details.
## Authors

- Rafał Masiarek | [website](https://masiarek.pl) | [github](https://github.com/rafalmasiarek)


<!-- references -->

[repo_link]: https://github.com/rafalmasiarek/php-dashboard-kit-addon-compress
