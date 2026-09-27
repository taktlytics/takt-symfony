# takt-symfony


> 📚 **Documentation** — [taktlytics.com/docs/wrappers/symfony](https://taktlytics.com/docs/wrappers/symfony)

Symfony bundle for [Takt](https://github.com/vskstudio) analytics. It wires the
Takt snippet into your templates through a `{{ takt() }}` Twig function and
exposes an autowired `Takt` service for server-side events.

## Installation

```bash
composer require vskstudio/takt-symfony
```

If you use [Symfony Flex](https://symfony.com/doc/current/setup/flex.html), the
bundle is enabled automatically. Otherwise add it manually to
`config/bundles.php`:

```php
return [
    // ...
    Vskstudio\Takt\Symfony\TaktBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/takt.yaml`:

```yaml
takt:
  domain: 'example.com'
  endpoint: 'https://taktlytics.com'   # origin or full collect URL (see below)
  script_origin: null   # first-party origin to dodge ad-blockers (see below)
  api_key: '%env(TAKT_API_KEY)%'
  mode: 'inline'   # inline | cdn | asset | sdk (sdk = full ES-module init(), needed for scrub_url)
  outbound: false
  files: false
  file_extensions: []   # e.g. ['pdf', 'zip']; empty keeps the tracker default list
  tagged: false         # track elements marked with data-takt-event
  not_found: false      # track 404 pageviews
  exclude_localhost: true
  nonce: null           # CSP nonce for the inline <script> (request-scoped)
  # Advanced options — null keeps the tracker defaults.
  sample_rate: null     # e.g. 0.5 keeps ~50% of hits
  track_query: null     # true keeps the raw query string + hash in URLs
  query_params: []      # params to keep when track_query is off, e.g. ['utm_source']
  exclude: []           # path prefixes never tracked, requires mode: sdk (e.g. ['/app','/account'])
  respect_dnt: null     # false stops honoring the Do-Not-Track header
  enabled: null         # false disables tracking entirely (kill-switch)
  scrub_url: null       # raw JS fn to rewrite URLs; requires mode: sdk (dev-controlled only)
  redact_routes: []     # sensitive routes sent as their pattern, e.g. ['/verify/{token}']
  route_templates: false # send every page as its route path, e.g. /users/{id}
```

The `api_key` must be **ingest-scoped and domain-bound**. Keep it out of source
control via an environment variable.

`script_origin` is the first-party origin to serve the tracker + derive the
endpoint from (`{origin}/api/event`) — your Takt domain or a custom domain you
proxy through to dodge ad-blockers (`endpoint` wins over it).

`endpoint` is where events are collected. It feeds two code paths at once — the
browser snippet, which needs the full collect URL, and the server-side sender,
which needs the origin it appends `/api/event` to — so both forms are accepted
and normalised for you. These two settings are equivalent:

```yaml
takt:
  endpoint: 'https://taktlytics.com'
  # endpoint: 'https://taktlytics.com/api/event'
```

A value carrying any other path is taken as the collect URL verbatim, which is
what a same-origin first-party proxy needs (`endpoint: '/collect'`). Leave it out
to talk to the hosted Takt service. Takt is a managed service hosted in Europe;
only the `/takt.js` measurement script can be served from your own domain (see
`mode: asset` and `script_origin`).

Autocapture is opt-in. `outbound`, `files`, `tagged` and `not_found` each add a
token to the single `data-auto` attribute read by the bundled tracker;
`file_extensions` narrows which downloads count.

The advanced options map to the engine's `sampleRate`, `trackQuery`,
`queryParams`, `respectDnt` and `enabled`. `exclude` maps to the engine's
`exclude` (path prefixes never tracked) and, like `scrub_url`, only works in
`mode: sdk`. `scrub_url` is a **raw JS function** injected verbatim, so it only
works in `mode: sdk` (a full ES-module `init()` render) — keep it dev-controlled
and never build it from user input.

## Client-side tracking

Call the `takt()` Twig function inside the `<head>` of your base template:

```twig
<head>
    {# ... #}
    {{ takt() }}
</head>
```

### Modes

- `inline` — the tracking script is embedded directly in the page.
- `cdn` — a `<script>` tag pointing at jsDelivr (`@vskstudio/takt-core`) is rendered.
- `asset` — a `<script>` tag pointing at `/takt/takt.auto.js`, served by your own
  application (prefixed with `script_origin` when set).
- `sdk` — a `<script type="module">` boots the full SDK via `init()`; required for `scrub_url`, `exclude`, `redact_routes` and `route_templates`.

## Server-side events

Autowire the `Takt` service in any controller or service:

```php
use Vskstudio\Takt\Revenue;
use Vskstudio\Takt\Takt;

final class CheckoutController
{
    public function __construct(private readonly Takt $takt)
    {
    }

    public function complete(): Response
    {
        $this->takt->event('Signup', ['plan' => 'pro'], new Revenue('29.00', 'EUR'));
        $this->takt->pageview();

        // ...
    }
}
```

The autowired service is bound to the current request: it automatically
attributes events to the request's IP address and User-Agent. It is deliberately
**not** a shared service — a fresh instance is built on each injection so the
attribution never outlives the request, including under long-running runtimes
(FrankenPHP worker mode, RoadRunner).

## Route redaction

Query strings are stripped by default, but path segments are sent as they are:
`/verify/abc123` leaks the token. Two opt-in settings replace real paths with
route templates, in the snippet and in server-side events alike. In the snippet
both require `mode: sdk`: the minimal snippet of the other modes cannot honor
them, so the `SnippetRenderer` throws.

```yaml
takt:
  mode: 'sdk'
  redact_routes: ['/verify/{token}', '/reset/{code}']
```

`redact_routes` lists the sensitive routes. A matching path is sent as the
pattern, every other path keeps its real value. Patterns accept the Symfony
syntax (`{token}`, `{page?}`) as well as `[param]`, `[[optional]]`,
`[...rest]`, `:param`, `*` and `**`. The browser SDK reads `{token}` as
`[token]`, so a browser pageview reports `/verify/[token]` where a server event
reports `/verify/{token}`; write `/verify/[token]` if both must land on the same
row.

```yaml
takt:
  mode: 'sdk'
  route_templates: true
```

`route_templates` sends every page as its route path: `/users/42` becomes
`/users/{id}`. The bundle resolves the `_route` of the main request through the
router (`Route::getPath()`, inline requirements and defaults stripped), renders
it into the `takt()` snippet and uses it as the default `route` of every
`pageview()` and `event()` sent by the autowired `Takt` service. It suits fully
private apps; on a public site it merges every article into one row. A request
with no matched route keeps its real path, still subject to `redact_routes`.
Resolving a route path loads the router's route collection, so this mode costs a
little on each request.

Any server-side call can pick its own route template:

```php
$this->takt->event('Verified', url: $request->getUri(), route: '/verify/{token}');
```

## Registered services

| Service                          | Visibility        | Notes                                                              |
| -------------------------------- | ----------------- | ------------------------------------------------------------------ |
| `Vskstudio\Takt\Takt`            | public, not shared | Autowired sender for server-side events; rebuilt per injection.     |
| `Vskstudio\Takt\SnippetRenderer` | public, shared     | Renders the snippet; backs the `takt()` Twig function.              |
| `Vskstudio\Takt\Options`         | private, shared    | Built from the bundle config, injected into the renderer.           |
| `…\Twig\TaktTwigExtension`       | public, shared     | Tagged `twig.extension`, exposes the `takt()` function (HTML-safe). |
| `…\Symfony\RouteTemplate`        | private, shared    | Registered only with `route_templates: true`; resolves the current route path. |

> **Behind a proxy or load balancer?** The attributed IP comes from
> `Request::getClientIp()`. It only honours `X-Forwarded-For` when the request
> is trusted, so configure [`framework.trusted_proxies`](https://symfony.com/doc/current/deployment/proxies.html)
> for the real client IP. Without trusted proxies, clients can spoof the
> forwarded header — so never treat the attributed IP as authoritative.

## License

MIT — see [LICENSE](LICENSE).
