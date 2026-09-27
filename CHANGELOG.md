# vskstudio/takt-symfony

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
  server-side sender, which expects an origin it appends `/api/event` to — so the
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
