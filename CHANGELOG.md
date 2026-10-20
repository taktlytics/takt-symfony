# vskstudio/takt-symfony

## 0.7.1

### Patch Changes

- Bump `vskstudio/takt-core-php` to 0.7.3, which fixes the browser tracker
  losing `window.takt` once loaded. The inline snippet used to begin with
  `var takt=(function(exports){...})({})`, which replaced the global `takt`, so
  `takt('Event')` threw; since takt-core-php 0.6.1, every event after the first
  pageview was also sent with an empty URL, which the ingest rejects with a 400.
  Run `composer update vskstudio/takt-core-php`; no configuration change is
  needed. A regression test fails if the inline bundle reassigns the global again.
- The inline snippet is checked against the bundle that `takt-core-php` 0.7
  renders, so the test suite no longer asserts the old `var takt=` prefix.
- Documents the `takt-core-php` 0.7 changes this bundle inherits, which 0.7.0
  did not list:
  - Endpoints must be `https`. A `http://` endpoint, `localhost` included,
    throws an `InvalidArgumentException` when the `Takt` service or the
    `takt()` snippet is built, so local development needs an https collector.
  - Route redaction masks a path that cannot be decoded as `/[redacted]` when a
    route is configured (`redact_undecodable_paths: false` keeps the tolerant
    matching of 0.6).
  - A constrained route parameter `{name<regex>}` is enforced: a value that does
    not match the regex no longer matches the route, and its path is sent as is.
  - In route template mode (`mode: sdk` with `redact_routes` or
    `route_templates`), the referrer is always reduced to its origin, and a page
    URL that cannot be parsed is sent with an empty `u`.

## 0.7.0

### Minor Changes

- `redact_undecodable_paths` (default `true`) is passed to the autowired `Takt`
  client as its `redactUndecodablePaths` argument. `false` matches a path that
  cannot be decoded like any other instead of masking it as `/[redacted]`. The
  browser snippet is unaffected.
- The route redaction documentation describes the constraint grammar, the
  REST segment and ambiguity limits, and the unusable-route masking of
  `takt-core-php`.
- Requires `vskstudio/takt-core-php` 0.7.

## 0.6.0

### Minor Changes

- Route redaction. `redact_routes` lists sensitive routes sent as their pattern
  instead of the real path (`/verify/abc` becomes `/verify/{token}`), and
  `route_templates` sends every page as its Symfony route path, resolved from
  the `_route` of the main request through the router. Both apply to the
  `takt()` snippet, where they require `mode: sdk`, and to the autowired `Takt`
  service: the current route path becomes the default `route` of `pageview()`
  and `event()`, which also accept an explicit `route`.
- Requires `vskstudio/takt-core-php` 0.6.

## 0.5.1

### Patch Changes

- `takt.endpoint` no longer means two different things depending on the code path.
  It fed both the browser snippet, which expects the full collect URL, and the
  server-side sender, which expects an origin it appends `/api/event` to. So the
  shipped default (`https://taktlytics.com`) made the browser post to the service
  root, while the full URL form (`https://taktlytics.com/api/event`) made the
  server post to `/api/event/api/event`. Both forms are now normalised to whatever
  each path needs, so every existing configuration keeps working and both paths
  agree. Normalisation happens at instantiation, so `%env()%` values are covered
  too. A value carrying any other path (a same-origin first-party proxy such as
  `/collect`) is still used verbatim.
- The `Vskstudio\Takt\Takt` service is no longer shared. It captures the current
  request's IP and User-Agent when built, so a shared instance froze the first
  request's visitor under long-running runtimes (FrankenPHP worker mode,
  RoadRunner). This matches the Laravel bridge, which already binds it `scoped`.
- Docs: the accepted `endpoint` forms are spelled out in the README and in the
  bundle's configuration reference, and the registered services are listed with
  their real visibility and sharing.

Releases before 0.5.1 are documented in the repository's git tags.
