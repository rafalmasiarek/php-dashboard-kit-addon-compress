# dashboard-kit-addon-compress

HTML output compression middleware for [rafalmasiarek/dashboard-kit](https://github.com/rafalmasiarek/php-dashboard-kit).

Collapses whitespace and strips HTML comments from every `text/html` response. Sensitive blocks (`<script>`, `<style>`, `<pre>`, `<textarea>`) are preserved verbatim.

---

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

## License

Business Source License 1.1 — see [LICENSE](LICENSE).
For alternative licensing, [contact us](https://masiarek.pl/contact/?af_subject=Commercial+license+%E2%80%94+dashboard-kit-addon-compress&af_message=Hello%2C+I+am+interested+in+a+commercial+license+for+dashboard-kit-addon-compress.).
