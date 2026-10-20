# Scenario catalogue

The behaviour contract of DeviceViewBundle, shared by every line (3.x, 4.x, 5.x, 6.x, 7.x and
main). Each scenario has a stable ID, the exact request and the exact expected result. Every
line implements the same scenarios in its own PHP and Symfony idiom, and its tests cite them by
ID, so the lines stay equivalent.

## How to use this file

**Citing a scenario.** A test cites a scenario with a PHPUnit group named after its ID, on the
test method, or on the test class to cite it for every test of the class:

- PHPUnit 10 and later (6.x, 7.x, main): `#[Group('SW-03')]`;
- PHPUnit 8.5 and 9.6 (3.x, 4.x, 5.x): a `@group SW-03` annotation.

A test may cite several IDs, and several tests may cite one ID. `vendor/bin/phpunit --group SW-03`
runs the tests of one scenario. Groups that are not shaped like an ID are ignored.

**Status.** The last cell of each row is the status:

- `planned`: not implemented yet; no test needs to cite it;
- `active`: implemented; at least one test cites it.

The PR that implements a scenario cites it in its tests and sets it to `active` in the same PR.

**Lines.** A scenario applies to every line unless its status cell ends with a Lines note in
parentheses:

- `(6.x+)`: that line and every later one (`planned (6.x+)`);
- `(3.x-5.x)`: that range of lines;
- `(main)`: that line only.

The lines, lowest first, are `3.x`, `4.x`, `5.x`, `6.x`, `7.x` and `main`. On a line outside the
note, the scenario does not apply, whatever its status: no test may cite it there, and the
cross-line parity check counts it as not applicable rather than missing. A Lines note is only for
a feature that a line cannot have (e.g. a library version it cannot install), never for work left
to do.

**Check.** `tests/ScenarioCatalogueTest.php` runs in every CI job. Its `LINE` constant names the
line of the branch (`main` here). It fails when an active scenario that applies to the line is not
cited by any test, or when a test cites an ID this file does not define or that does not apply to
the line.

**IDs.** `AREA-NN` or `AREA-SUB-NN`: AREA and SUB are 2 to 5 capital letters, NN is a number of at
least two digits, written with a leading zero below 10 only (`SW-03`, `CFG-52`, `CFG-100`). A row
or a group shaped like an ID with another number (`SW-1`, `CFG-001`) is an error, not ignored.
IDs are never renumbered, reused or deleted. When a planned expectation turns out wrong, the PR
that implements the scenario fixes the row in place and says so.

**Messages.** Exception and log messages are part of the contract. In a planned scenario they are
the proposed text: the PR that activates the scenario may adjust the wording in the same PR, never
the behaviour. "Message contains" means a substring check, so the text Symfony adds around it
(e.g. `Invalid configuration for path "…":`) may differ between lines.

**Table rows.** A scenario is a table row whose first cell is the ID and whose last cell is the
status, with its optional Lines note. A `|` inside a cell is written `\|`. In a table whose header
row has a first cell starting with `ID` in any case (`ID`, `Id`, `IDs`), every row must start with
a well-formed ID: a mistyped first cell (`Sw-03`, `SW-03a`, `**SW-03**`) fails the check instead
of hiding the scenario.

## Conventions

### Baseline

- **Config:** the defaults of `docs/design.md` section 4, plus `detector: { id: mobile_detect }`.
  The Config cell lists only what differs, as YAML flow under `device_view:`. `—` means the
  baseline. Shorthands used in Config cells:
  - `M`: `redirect: { mobile: { enabled: true, host: 'https://m.example.com' } }`
  - `T`: `redirect: { tablet: { enabled: true, host: 'https://t.example.com' } }`
  - `D`: `redirect: { desktop: { enabled: true, host: 'https://www.example.com' } }`
  - `TH`: `trusted_header: { name: X-Device-View }`, with the trusted proxy `127.0.0.1` (below)
  - `<tool>` in a consent area: `consent: { provider: <tool> }`
- **Trusted proxies:** they are framework settings, so they never appear in the Config cell,
  which holds `device_view` keys only. `TH` rows trust the proxy `127.0.0.1`; RED-35 also trusts
  the `X-Forwarded-For`, `X-Forwarded-Host` and `X-Forwarded-Proto` headers. On 5.x, 6.x, 7.x and
  main the test kernel sets them with `framework: { trusted_proxies: …, trusted_headers: … }`.
  **Lines note:** 3.x and 4.x set trusted proxies with `Request::setTrustedProxies()` in the test
  front controller instead of framework config, because Symfony 3.4 deprecates
  `framework.trusted_proxies` and Symfony 4.4 has no such option.
- **Kernel:** `tests/App/Kernel` (FrameworkBundle with `framework.test: true`, this bundle,
  `debug: false`), called with `$kernel->handle($request)` as the main request. Twig,
  WebProfilerBundle, sessions and fragments are enabled only where the area says so.
- **Exceptions:** a row that expects an exception while the kernel handles a request (thrown by
  a listener, a controller or the router: EVT-RED-07, EVT-REM-09, FAC-05, the controller call of
  FAC-11, ROUT-06) calls `$kernel->handle($request, <main-request type>, false)`, so the
  exception reaches the test instead of becoming a 500 response. The main-request type is
  `HttpKernelInterface::MAIN_REQUEST`, or `HttpKernelInterface::MASTER_REQUEST` below Symfony 5.3
  (3.x and 4.x). A row or call that names no request (a direct call such as `requestAs()` in
  TST-06 or the empty `RequestStack` call of FAC-11, a template render in TWIG-05, TWIG-06 and
  TWIG-09, or a container build) calls the method in the test.
- **Request:** `GET /view` means `Request::create('http://localhost/view', 'GET')`. A full URL is
  given when the scheme, host or port matter. `Request::create()` sets `REMOTE_ADDR: 127.0.0.1`.
- **User agent:** `UA=iphone` sets `User-Agent` to the `iphone` entry of
  `tests/Fixtures/user-agents.php` (`UA=missing` removes the header). A request that names no UA
  uses `UA=desktop_chrome`. Tests always set or remove the header: the `Symfony` user agent that
  `Request::create()` adds is never relied on.
- **Headers:** `CH=?1` sends `Sec-CH-UA-Mobile: ?1`. Other headers are written `Name: value`.
- **Cookies:** `Cookie: name=value` is the request cookie as PHP decodes it (the value of
  `$request->cookies`). The HTTP area gives raw `Cookie` headers instead.
- **Front controller `/app`:** the request has `SCRIPT_NAME: /app/index.php` and
  `SCRIPT_FILENAME: /srv/app/index.php`, so its base URL is `/app`.

### Expected results

- The status code first, then the headers that matter. A header that is not mentioned is not
  asserted; "no X" means the header is absent.
- `Location`: the exact header value.
- `Set-Cookie`: the exact raw value of the `device_view` cookie (`Cookie::__toString()`).
  `E(n)` is the expiry date as the line's `Cookie::__toString()` writes it, and tests allow one
  second of clock drift:
  - 3.x, 4.x and 5.x (Symfony below 6.1): `gmdate('D, d-M-Y H:i:s T', now + n)`, with dashes;
  - 6.x, 7.x and main: `gmdate('D, d M Y H:i:s T', now + n)`, with spaces.

  Every `expires=E(n)` in a row, in a shorthand or written out (CK-15), uses this definition. The
  rest of the cookie string is the same on every line, so no row needs a Lines note for it.
  Shorthands:
  - `SC(v)`: `device_view=v; path=/; httponly; samesite=lax` (a browser-session cookie)
  - `SCS(v)`: `device_view=v; path=/; secure; httponly; samesite=lax`
  - `SC(v, n)`: `device_view=v; expires=E(n); Max-Age=n; path=/; httponly; samesite=lax`
  - `SCS(v, n)`: `device_view=v; expires=E(n); Max-Age=n; path=/; secure; httponly; samesite=lax`
  - `SC-DEL`: `device_view=deleted; expires=E(-31536001); Max-Age=0; path=/; httponly; samesite=lax`
- `Vary`: the list `Response::getVary()` returns, written `[Cookie, User-Agent]`.
- `Cache-Control`: the value of `$response->headers->get('Cache-Control')`. Symfony returns the
  directives sorted by name, whatever order they were set in, and adds `private` when neither
  `public` nor `private` is set. `NS` means `no-store, private`.
- Body: the routes below print the view; `"mobile detector"` is the exact body.
- `hasConsent`: what the configured `ConsentCheckerInterface::hasConsent()` returns for the
  request: `true`, `false` or `null`.
- Logs: the level and message of the record the bundle writes (tests use a `BufferingLogger`).

### Test application routes

The scenarios assume these routes in `tests/App`. Each PR adds the routes its scenarios need.

| Route | Path | Behaviour |
|---|---|---|
| `home` | `/` | Body `home page`. Never reads the view. |
| `view` | `/view` | Body `<view name> <source>`, from `DeviceView::getViewName()` and `DeviceView::getSource()`, e.g. `mobile detector`. |
| `blog` | `/blog/{slug}` | Same as `view`. |
| `form` | `/form` | Same as `view`; accepts GET, HEAD and POST. |
| `cacheable` | `/cacheable` | Same as `view`, and the response is `setPublic()` then `setMaxAge(3600)`. |
| `api` | `/api/thing` | Same as `view`. |
| `no_redirect` | `/no-redirect` | Same as `view`; route default `_device_view_redirect: none`. |
| `without_path` | `/without-path` | Same as `view`; route default `_device_view_redirect: redirect_without_path`. |
| `always_redirect` | `/always-redirect` | Same as `view`; route default `_device_view_redirect: redirect`. |
| `bad_default` | `/bad-default` | Same as `view`; route default `_device_view_redirect: sometimes`. |
| `no_remember` | `/no-remember` | Same as `view`; route default `_device_view_remember: false`. |
| `no_remember_xml` | `/no-remember-xml` | Same as `view`; route default `_device_view_remember: 'false'` (a string, as XML routes give). |
| `remember` | `/remember` | Same as `view`; route default `_device_view_remember: true`. |
| `custom_keys` | `/custom-keys` | Same as `view`; route defaults `_dv_redirect: none`, `_dv_remember: false`. |
| `set_view` | `/set-view/{view}` | Calls `DeviceView::setView($view, $request->query->getBoolean('remember'))`, then prints like `view`. |
| `set_remember_off` | `/set-remember-off` | Calls `DeviceView::setRemember(false)`, then prints like `view`. |
| `set_consent` | `/set-consent/{value}` | Calls `DeviceView::setConsent()` with `true` for `1`, `false` for `0`, `null` for `null`, then prints like `view`. |
| `readers` | `/readers/{name}` | JSON object of every `DeviceView` reader: `view` (`getView()` value), `name` (`getViewName()`), `mobile`, `tablet`, `desktop` (`isMobile()`, `isTablet()`, `isDesktop()`), `is` (`is($name)`), `is_mobile_type` (`is(DeviceType::Mobile)`), `source` (`getSource()` value). |
| `sub_request` | `/with-sub-request` | Renders an inline sub-request to the URI in the `sub` query parameter (default `/view`); body `main=<main view name> sub=<sub-request body>`. |
| `mobile_only` | `/mobile-only` | Body `mobile only`; condition `device_view() == 'mobile'`. |
| `is_mobile_only` | `/is-mobile-only` | Body `mobile only`; condition `is_device_view('mobile')`. |
| `m_only` | `/m-only` | Body `m only`; condition `device_view() == 'm'`. |
| `twig` | `/twig` | Renders `views.html.twig` (below); the body is the trimmed output. |
| `profiler_stub` | `/_profiler/abc` | Same as `view`. Only in kernels without WebProfilerBundle (area SKIP), so the default skip paths can be checked on a route that exists. |
| `wdt_stub` | `/_wdt/abc` | Same as `profiler_stub`. |
| `fragment_stub` | `/_fragment` | Same as `view`. Only in kernels without `framework.fragments` (SKIP-04). |

`views.html.twig`:

```twig
{{ device_view() }}|{{ is_mobile_view() ? 1 : 0 }}{{ is_tablet_view() ? 1 : 0 }}{{ is_desktop_view() ? 1 : 0 }}|{{ is_device_view('tablet') ? 1 : 0 }}|{{ device_view_switch_url('desktop') }}|{{ device_view_switch_url('mobile', 'home') }}
```

## 1. Input validation (VAL), design.md section 3

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| VAL-01 | Switch token is trimmed and lower-cased | — | `GET /view?device_view=%20Mobile%20` | 302, `Location: /view`, `Set-Cookie: SC(mobile)` | active |
| VAL-02 | Unknown switch word is ignored | — | `GET /view?device_view=phone` | 200 `"desktop detector"`, no `Location`, no `Set-Cookie`; debug log `Ignored an invalid device view from the switch parameter.` | active |
| VAL-03 | `not_mobile` is ignored | — | `GET /view?device_view=not_mobile` | 200 `"desktop detector"`, no `Location`, no `Set-Cookie` | active |
| VAL-04 | Array switch value is ignored | — | `GET /view?device_view[]=mobile` | 200 `"desktop detector"`, no `Location`, no `Set-Cookie`, no error | active |
| VAL-05 | Empty switch value is ignored | — | `GET /view?device_view=` | 200 `"desktop detector"`, no `Location`, no `Set-Cookie` | active |
| VAL-06 | Switch token must be a configured name | `views: { names: { desktop: d } }` | `GET /view?device_view=desktop` | 200 `"d detector"`, no `Location`, no `Set-Cookie` | active |
| VAL-07 | Stored token is trimmed and lower-cased | — | `GET /view`, `Cookie: device_view=MOBILE` | 200 `"mobile remembered"`, no `Set-Cookie` | active |
| VAL-08 | Forged cookie is ignored and expired | — | `GET /view`, `Cookie: device_view=phone` | 200 `"desktop detector"`, `Set-Cookie: SC-DEL`, `Vary: [Cookie, User-Agent]`; debug log `Ignored an invalid device view from the remember storage.` | planned |
| VAL-09 | Array cookie is ignored, not expired | — | `GET /view`, cookie `device_view` = `['a' => 'mobile']` | 200 `"desktop detector"`, no `Set-Cookie`, no error; debug log `Ignored an invalid device view from the remember storage.` | active |
| VAL-10 | Empty cookie is ignored and expired | — | `GET /view`, `Cookie: device_view=` | 200 `"desktop detector"`, `Set-Cookie: SC-DEL` | active |
| VAL-11 | A forged value is found only when read | — | `GET /`, `Cookie: device_view=phone` | 200 `"home page"`, no `Set-Cookie`, no `Vary` | active |
| VAL-12 | Forged session value is removed | `remember: { storage: session }` | `GET /view` with a previous session holding `_device_view = phone` | 200 `"desktop detector"`; the session no longer has `_device_view` | active |
| VAL-13 | Invalid trusted header value is ignored | `TH` | `GET /view`, `X-Device-View: phone`, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, X-Device-View, User-Agent]`; debug log `Ignored an invalid device view from the trusted header.` | planned |
| VAL-14 | Non-string session value is removed | `remember: { storage: session }` | `GET /view` with a previous session holding `_device_view = 3` (an integer) or `['a' => 'mobile']` (an array) | 200 `"desktop detector"`; the session no longer has `_device_view`; debug log `Ignored an invalid device view from the remember storage.` | active |

## 2. Resolution order and sources (RES), design.md sections 2 and 5

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| RES-01 | Resolution is lazy | — | `GET /`, `UA=iphone` | 200 `"home page"`, no `Vary`, no `Set-Cookie`; the detector is not called; `ViewResolvedEvent` is not dispatched | planned |
| RES-02 | Detector decides | — | `GET /view`, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent]` | planned |
| RES-03 | `default_view` when the detector cannot tell | — | `GET /view`, `UA=empty` | 200 `"desktop default"`, `Vary: [Cookie, User-Agent]` | planned |
| RES-04 | Forced view beats every source | `TH` | `GET /set-view/tablet`, `Cookie: device_view=mobile`, `X-Device-View: desktop`, `UA=iphone` | 200 `"tablet forced"`, no `Set-Cookie`, no `Vary` (nothing was consulted) | planned |
| RES-05 | Switch beats remembered | `switch: { target: none }` | `GET /view?device_view=tablet`, `Cookie: device_view=mobile`, `UA=iphone` | 200 `"tablet switch"`, `Set-Cookie: SC(tablet)`, no `Vary` | active |
| RES-06 | Remembered beats trusted header and detector | `TH` | `GET /view`, `Cookie: device_view=mobile`, `X-Device-View: desktop`, `UA=desktop_chrome` | 200 `"mobile remembered"`, `Vary: [Cookie]` | planned |
| RES-07 | Trusted header beats detector | `TH` | `GET /view`, `X-Device-View: tablet`, `UA=iphone` | 200 `"tablet trusted_header"`, `Vary: [Cookie, X-Device-View]` | planned |
| RES-08 | The view is resolved once per request | — (TwigBundle registered) | `GET /twig`, `UA=iphone`, the template and a `ViewResolvedEvent` listener read the view several times | the detector is called once; `ViewResolvedEvent` is dispatched once | planned |
| RES-09 | Sub-requests read the main request's view | — | `GET /with-sub-request`, `UA=iphone` | 200 `"main=mobile sub=mobile detector"`; `ViewResolvedEvent` dispatched once; the sub-request has no `_device_view` attribute | planned |
| RES-10 | The context lives in the main request | — | `GET /view`, `UA=iphone` | after `kernel.request`, the main request attribute `_device_view` is a `DeviceViewContext` | active |
| RES-11 | The resolver ignores the switch parameter when the method is not allowed | — | `POST /form?device_view=mobile` | 200 `"desktop detector"`, no `Set-Cookie` | active |
| RES-12 | The resolver ignores the switch parameter when the switch is disabled | `switch: { enabled: false }` | `GET /view?device_view=mobile` | 200 `"desktop detector"`, no `Location`, no `Set-Cookie` | active |

## 3. Views (VW): views.names, default_view, tablet_as_mobile, design.md sections 3 and 4

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| VW-01 | `default_view: mobile` | `default_view: mobile` | `GET /view`, `UA=empty` | 200 `"mobile default"` | active |
| VW-02 | `default_view: tablet` without user agent | `default_view: tablet` | `GET /view`, `UA=missing` | 200 `"tablet default"` | active |
| VW-03 | `tablet_as_mobile` maps a detected tablet | `tablet_as_mobile: true` | `GET /view`, `UA=ipad_legacy` | 200 `"mobile detector"` | active |
| VW-04 | `tablet_as_mobile` maps a remembered tablet | `tablet_as_mobile: true` | `GET /view`, `Cookie: device_view=tablet` | 200 `"mobile remembered"`, no `Set-Cookie` | active |
| VW-05 | `tablet_as_mobile` maps a switch to tablet, and the cookie stores the mapped view | `tablet_as_mobile: true`, `switch: { target: none }` | `GET /view?device_view=tablet` | 200 `"mobile switch"`, `Set-Cookie: SC(mobile)` | active |
| VW-06 | `tablet_as_mobile` maps `default_view: tablet` | `tablet_as_mobile: true`, `default_view: tablet` | `GET /view`, `UA=empty` | 200 `"mobile default"` | active |
| VW-07 | `tablet_as_mobile` leaves other views alone | `tablet_as_mobile: true` | `GET /view`, `UA=desktop_chrome` | 200 `"desktop detector"` | active |
| VW-08 | Renamed views: switch | `views: { names: { mobile: m, tablet: t, desktop: d } }` | `GET /view?device_view=m` | 302, `Location: /view`, `Set-Cookie: device_view=m; path=/; httponly; samesite=lax` | active |
| VW-09 | Renamed views: the canonical name is no longer a token | `views: { names: { mobile: m, tablet: t, desktop: d } }` | `GET /view?device_view=mobile` | 200 `"d detector"`, no `Set-Cookie` | active |
| VW-10 | Renamed views: remembered | `views: { names: { mobile: m, tablet: t, desktop: d } }` | `GET /view`, `Cookie: device_view=t` | 200 `"t remembered"` | active |
| VW-11 | Renamed views: a stored canonical name is forged | `views: { names: { mobile: m, tablet: t, desktop: d } }` | `GET /view`, `Cookie: device_view=mobile` | 200 `"d detector"`, `Set-Cookie: SC-DEL` | active |
| VW-12 | Renamed views: cross-host redirect | `views: { names: { mobile: m } }`, `M` | `GET http://www.example.com/blog/post`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?device_view=m` | active |
| VW-13 | Renamed views: trusted header | `views: { names: { tablet: t } }`, `TH` | `GET /view`, `X-Device-View: t` | 200 `"t trusted_header"` | planned |
| VW-14 | Renaming one view keeps the others | `views: { names: { mobile: phone } }` | `GET /view?device_view=tablet` | 302, `Location: /view`, `Set-Cookie: SC(tablet)` | active |

## 4. Detectors (DET), design.md section 9

### Mobile Detect (DET-MD)

DET-MD-06 and CFG-13 apply from 6.x on: `detector.mobile_detect.cache` needs
mobiledetect/mobiledetectlib 4, which the PHP 7 lines cannot install (design.md sections 4 and 13).

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| DET-MD-01 | Every user agent fixture | — | `GET /view` once per entry of `tests/Fixtures/user-agents.php`, with its user agent | body `"<expected.mobile_detect> detector"`, or `"desktop default"` when it is null | active |
| DET-MD-02 | Reads the Request, never `$_SERVER` | — | `$_SERVER['HTTP_USER_AGENT']` = the `iphone` user agent; `GET /view`, `UA=desktop_chrome` | 200 `"desktop detector"` | active |
| DET-MD-03 | A new MobileDetect per call | — | one kernel: `GET /view` `UA=iphone`, then `GET /view` `UA=desktop_chrome`, then `UA=ipad_legacy` | `"mobile detector"`, `"desktop detector"`, `"tablet detector"` | active |
| DET-MD-04 | Configured headers are passed and listed in `Vary` | `detector: { mobile_detect: { headers: [User-Agent, X-Device-User-Agent] } }` | `GET /view`, `UA=desktop_chrome`, `X-Device-User-Agent:` the `iphone` user agent | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent, X-Device-User-Agent]` | planned |
| DET-MD-05 | Headers not configured are not passed | — | `GET /view`, `UA=desktop_chrome`, `X-Device-User-Agent:` the `iphone` user agent | 200 `"desktop detector"`, `Vary: [Cookie, User-Agent]` | planned |
| DET-MD-06 | PSR-16 cache | `detector: { mobile_detect: { cache: app.detector_cache } }` (a `Psr16Cache` over an `ArrayAdapter`) | `GET /view` `UA=iphone`, twice | `"mobile detector"` both times; the cache holds at least one item after the first request | planned (6.x+) |

### Matomo (DET-MAT)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| DET-MAT-01 | Every user agent fixture | `detector: { id: matomo }` | `GET /view` once per fixture entry, with its user agent and no client hints | body `"<expected.matomo> detector"`, or `"desktop default"` when it is null | planned |
| DET-MAT-02 | `phablet_as: tablet` | `detector: { id: matomo, matomo: { phablet_as: tablet } }` | `GET /view`, `UA=phablet` | 200 `"tablet detector"` | planned |
| DET-MAT-03 | Bots get desktop | `detector: { id: matomo }` | `GET /view`, `UA=googlebot_smartphone` | 200 `"desktop detector"` | planned |
| DET-MAT-04 | Unknown device falls back to `default_view` | `detector: { id: matomo }`, `default_view: mobile` | `GET /view`, `UA=curl` | 200 `"mobile default"` | planned |
| DET-MAT-05 | Client hints are passed | `detector: { id: matomo }` | `GET /view`, `UA=android_tablet_reduced`, `CH=?1`, `Sec-CH-UA-Model: "Pixel 9"`, `Sec-CH-UA-Platform: "Android"` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent, Sec-CH-UA, Sec-CH-UA-Arch, Sec-CH-UA-Bitness, Sec-CH-UA-Full-Version, Sec-CH-UA-Full-Version-List, Sec-CH-UA-Mobile, Sec-CH-UA-Model, Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version]` | planned |
| DET-MAT-06 | `client_hints: false` ignores the hints | `detector: { id: matomo, matomo: { client_hints: false } }` | same request as DET-MAT-05 | 200 `"tablet detector"`, `Vary: [Cookie, User-Agent]` | planned |
| DET-MAT-07 | PSR-6 cache pool | `detector: { id: matomo, matomo: { cache_pool: cache.app } }` | `GET /view` `UA=iphone`, twice | `"mobile detector"` both times; the pool holds at least one item after the first request | planned |
| DET-MAT-08 | Reads the Request, never `$_SERVER` | `detector: { id: matomo }` | `$_SERVER['HTTP_USER_AGENT']` = the `iphone` user agent; `GET /view`, `UA=desktop_chrome` | 200 `"desktop detector"` | planned |
| DET-MAT-09 | A feature phone is mobile | `detector: { id: matomo }` | `GET /view`, `UA=feature_phone` | 200 `"mobile detector"` | planned |
| DET-MAT-10 | Another known device type is desktop | `detector: { id: matomo }`, `default_view: mobile` | `GET /view`, `UA=smart_tv` (Matomo reports `tv`) | 200 `"desktop detector"` | planned |

### Client Hints (DET-CH)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| DET-CH-01 | `?1` is mobile | `detector: { id: client_hints }` | `GET /view`, `CH=?1`, `UA=desktop_chrome` | 200 `"mobile detector"`, `Vary: [Cookie, Sec-CH-UA-Mobile]` | planned |
| DET-CH-02 | `?0` is desktop | `detector: { id: client_hints }` | `GET /view`, `CH=?0`, `UA=iphone` | 200 `"desktop detector"` | planned |
| DET-CH-03 | No hint: `default_view` | `detector: { id: client_hints }`, `default_view: tablet` | `GET /view`, `UA=iphone` | 200 `"tablet default"`, `Vary: [Cookie, Sec-CH-UA-Mobile]` | planned |
| DET-CH-04 | Other values: `default_view` | `detector: { id: client_hints }` | `GET /view`, `Sec-CH-UA-Mobile` = `?2`, then `1`, then `true` | 200 `"desktop default"` each time | planned |
| DET-CH-05 | Never tablet | `detector: { id: client_hints }` | `GET /view`, `CH=?0`, `UA=ipad_legacy` | 200 `"desktop detector"` | planned |
| DET-CH-06 | Every user agent fixture | `detector: { id: client_hints }` | `GET /view` once per fixture entry, with its user agent and its `sec_ch_ua_mobile` header | body `"<expected.client_hints> detector"`, or `"desktop default"` when it is null | planned |

### Chain (DET-CHAIN)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| DET-CHAIN-01 | First answer wins | `detector: { id: chain }` (chain `[client_hints, mobile_detect]`) | `GET /view`, `CH=?1`, `UA=desktop_chrome` | 200 `"mobile detector"`, `Vary: [Cookie, Sec-CH-UA-Mobile]` | planned |
| DET-CHAIN-02 | Null passes to the next detector | `detector: { id: chain }` | `GET /view`, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, Sec-CH-UA-Mobile, User-Agent]` | planned |
| DET-CHAIN-03 | All null: `default_view` | `detector: { id: chain }` | `GET /view`, `UA=empty` | 200 `"desktop default"`, `Vary: [Cookie, Sec-CH-UA-Mobile, User-Agent]` | planned |
| DET-CHAIN-04 | Order is the configured order | `detector: { id: chain, chain: [mobile_detect, client_hints] }` | `GET /view`, `UA=iphone`, `CH=?0` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent]` | planned |
| DET-CHAIN-05 | A service id in the chain | `detector: { id: chain, chain: [app.tablet_detector, mobile_detect] }` (a test detector that always answers tablet and varies on nothing) | `GET /view`, `UA=iphone` | 200 `"tablet detector"`, `Vary: [Cookie]` | planned |

### Static, custom and auto (DET-STA, DET-CUS, DET-AUTO)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| DET-STA-01 | Static answer | `detector: { id: static, static: { view: tablet } }` | `GET /view`, `UA=iphone` | 200 `"tablet detector"`, `Vary: [Cookie]` | planned |
| DET-STA-02 | Static default is desktop | `detector: { id: static }` | `GET /view`, `UA=iphone` | 200 `"desktop detector"` | planned |
| DET-CUS-01 | Custom detector service | `detector: { id: app.header_detector }` (a test detector reading `X-Test-View`, `varyHeaders()` = `[X-Test-View]`) | `GET /view`, `X-Test-View: tablet` | 200 `"tablet detector"`, `Vary: [Cookie, X-Test-View]` | planned |
| DET-CUS-02 | Custom detector answering null | `detector: { id: app.header_detector }` | `GET /view` | 200 `"desktop default"` | active |
| DET-CUS-03 | The interface alias follows the configuration | `detector: { id: app.header_detector }` | container | `DeviceDetectorInterface` and `device_view.detector` resolve to `app.header_detector` | active |
| DET-AUTO-01 | Auto prefers Matomo | `detector: { id: auto }`, both libraries installed | container and `GET /view` `UA=googlebot_smartphone` | the detector is the matomo one; 200 `"desktop detector"` | planned |
| DET-AUTO-02 | Auto falls back to Mobile Detect | `detector: { id: auto }`, Matomo not installed | container and `GET /view` `UA=googlebot_smartphone` | the detector is the mobile_detect one; 200 `"mobile detector"` | planned |
| DET-AUTO-03 | Auto with no library | `detector: { id: auto }`, neither library installed | container build | `LogicException`, message: "No device detector is installed. Run `composer require mobiledetect/mobiledetectlib` (or `matomo/device-detector`), or set `device_view.detector.id` to `client_hints` or to your own service id." | active |
| DET-AUTO-04 | No library is needed when no library detector is used | `detector: { id: chain, chain: [client_hints, static] }`, neither library installed | container build | builds; `GET /view` `CH=?1` gives 200 `"mobile detector"` | planned |

Tests of DET-AUTO and of the missing-package errors (CFG-46 to CFG-48, CFG-54 and CFG-55)
simulate a missing package: the availability check is injectable, or the test runs in a separate
process with the package's autoloading removed.

## 5. Switch (SW), design.md sections 4, 5 and 6

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| SW-01 | Switch to the current page | — | `GET /view?device_view=mobile` | 302, `Location: /view`, `Cache-Control: NS`, `Set-Cookie: SC(mobile)`, no `Vary` | active |
| SW-02 | Other query parameters are kept in order | — | `GET /view?b=2&device_view=mobile&a=1` | 302, `Location: /view?b=2&a=1` (not sorted) | active |
| SW-03 | The query is rebuilt with RFC 3986 encoding | — | `GET /view?q=a+b&tags[]=x&device_view=mobile` | 302, `Location: /view?q=a%20b&tags%5B0%5D=x` | active |
| SW-04 | The base URL is kept | — | front controller `/app`: `GET http://localhost/app/view?device_view=mobile` | 302, `Location: /app/view` | active |
| SW-05 | `target: home` | `switch: { target: home }` | `GET /view?a=1&device_view=mobile` | 302, `Location: /`, `Set-Cookie: SC(mobile)` | active |
| SW-06 | `target: home` keeps the base URL | `switch: { target: home }` | front controller `/app`: `GET http://localhost/app/view?device_view=mobile` | 302, `Location: /app/` | active |
| SW-07 | `target: none` applies to this request only | `switch: { target: none }` | `GET /view?device_view=mobile` | 200 `"mobile switch"`, `Set-Cookie: SC(mobile)`, no `Location`, no `Vary` | active |
| SW-08 | Status 303 | `switch: { status: 303 }` | `GET /view?device_view=mobile` | 303, `Location: /view` | active |
| SW-09 | Status 307 | `switch: { status: 307 }` | `GET /view?device_view=mobile` | 307, `Location: /view` | active |
| SW-10 | Custom parameter | `switch: { parameter: view }` | `GET /view?view=tablet` | 302, `Location: /view`, `Set-Cookie: SC(tablet)` | active |
| SW-11 | Custom parameter: the default name is ignored | `switch: { parameter: view }` | `GET /view?device_view=tablet` | 200 `"desktop detector"`, no `Set-Cookie` | active |
| SW-12 | POST is not switched | — | `POST /form?device_view=mobile` | 200 `"desktop detector"`, no `Location`, no `Set-Cookie` | active |
| SW-13 | HEAD is switched | — | `HEAD /form?device_view=mobile` | 302, `Location: /form`, `Set-Cookie: SC(mobile)` | active |
| SW-14 | `redirect.methods` also governs the switch | `redirect: { methods: [GET, POST] }` | `POST /form?device_view=mobile` | 302, `Location: /form`, `Set-Cookie: SC(mobile)` | active |
| SW-15 | `cache_control: null` sets nothing; the cookie makes the redirect private | `redirect: { cache_control: null }` | `GET /view?device_view=mobile` | 302, `Set-Cookie: SC(mobile)`, `Cache-Control: private` (Symfony's computed `no-cache, private` is not a stored directive, so the `private_when_remembering` step leaves `private` only) | active |
| SW-16 | Custom `cache_control` | `redirect: { cache_control: 'no-cache, no-store, must-revalidate' }` | `GET /view?device_view=mobile` | 302, `Cache-Control: must-revalidate, no-cache, no-store, private` (Symfony sorts the directives and adds `private`) | active |
| SW-17 | Switch to a view served by another host | `M` | `GET http://www.example.com/blog/post?a=1&device_view=mobile` | 302, `Location: https://m.example.com/blog/post?a=1&device_view=mobile`, `Cache-Control: NS`, `Set-Cookie: SC(mobile)` | active |
| SW-18 | Switch arriving on the target host | `M` | `GET https://m.example.com/blog/post?a=1&device_view=mobile` | 302, `Location: /blog/post?a=1`, `Set-Cookie: SCS(mobile)` | active |
| SW-19 | Switch back to desktop from the mobile host | `M`, `D` | `GET https://m.example.com/blog/post?device_view=desktop`, `UA=iphone` | 302, `Location: https://www.example.com/blog/post?device_view=desktop`, `Set-Cookie: SCS(desktop)` | active |
| SW-20 | The redirected view's host equals the current host | `M` | `GET https://m.example.com/view?device_view=mobile` | 302, relative `Location: /view` | active |
| SW-21 | A switch to the stored view is written again | — | `GET /view?device_view=mobile`, `Cookie: device_view=mobile` | 302, `Location: /view`, `Set-Cookie: SC(mobile)` | active |
| SW-22 | Sub-requests never switch | — | `GET /with-sub-request?sub=%2Fview%3Fdevice_view%3Dtablet`, `UA=iphone` | 200 `"main=mobile sub=mobile detector"`, no `Set-Cookie`, no `Location` | active |

## 6. Remember decision table (REM), design.md section 7

Switch rows use `GET /view?device_view=mobile` and expect 302 `Location: /view`. Detected rows use
`GET /view` with `UA=iphone` and expect 200 `"mobile detector"`. `TAC` is
`consent: { provider: tarteaucitron }`; granted is `Cookie: tarteaucitron=!device_view=true`,
denied is `Cookie: tarteaucitron=!device_view=false`, unknown is no consent cookie.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| REM-01 | Switch, `on_switch: true`, consent granted | `TAC` | switch, granted | `Set-Cookie: SC(mobile, 31536000)` | active |
| REM-02 | Switch, `on_switch: true`, consent denied | `TAC` | switch, denied | `Set-Cookie: SC(mobile)` | active |
| REM-03 | Switch, `on_switch: true`, consent unknown | `TAC` | switch, unknown | `Set-Cookie: SC(mobile)` | active |
| REM-04 | Switch, `on_switch: true`, no provider | — | switch | `Set-Cookie: SC(mobile)` | active |
| REM-05 | Switch, no provider, `lifetime` set | `remember: { lifetime: 3600 }` | switch | `Set-Cookie: SC(mobile, 3600)` | active |
| REM-06 | Switch, `on_switch: false` | `TAC`, `remember: { on_switch: false }` | switch, granted | no `Set-Cookie` | active |
| REM-07 | Detected, `detected_view: false` | `TAC` | detected, granted | no `Set-Cookie` | active |
| REM-08 | Detected, `detected_view: true`, no provider | `remember: { detected_view: true }` | detected | `Set-Cookie: SC(mobile)` | active |
| REM-09 | Detected, `detected_view: true`, no provider, persistent lifetime | `remember: { detected_view: true, lifetime: 31536000 }` | detected | `Set-Cookie: SC(mobile, 31536000)` | active |
| REM-10 | Detected, `detected_view: true`, provider, granted | `TAC`, `remember: { detected_view: true }` | detected, granted | `Set-Cookie: SC(mobile, 31536000)` | active |
| REM-11 | Detected, `detected_view: true`, provider, denied | `TAC`, `remember: { detected_view: true }` | detected, denied | no `Set-Cookie` | active |
| REM-12 | Detected, `detected_view: true`, provider, unknown | `TAC`, `remember: { detected_view: true }` | detected, unknown | no `Set-Cookie` | active |
| REM-13 | A remembered view is not written again | `remember: { detected_view: true }` | `GET /view`, `Cookie: device_view=mobile` | 200 `"mobile remembered"`, no `Set-Cookie` | active |
| REM-14 | Nothing is written when the view is not used | `remember: { detected_view: true }` | `GET /`, `UA=iphone` | 200 `"home page"`, no `Set-Cookie` | active |
| REM-15 | `default_view` is not a detected view | `remember: { detected_view: true }` | `GET /view`, `UA=empty` | 200 `"desktop default"`, no `Set-Cookie` | active |
| REM-16 | A trusted header view counts as detected | `TH`, `remember: { detected_view: true }` | `GET /view`, `X-Device-View: tablet` | 200 `"tablet trusted_header"`, `Set-Cookie: SC(tablet)` | planned |
| REM-17 | `setRemember(false)` stops a write | `switch: { target: none }` | `GET /set-remember-off?device_view=mobile` | 200 `"mobile switch"`, no `Set-Cookie` | active |
| REM-18 | `setView($view, true)` writes like a switch | — | `GET /set-view/tablet?remember=1` | 200 `"tablet forced"`, `Set-Cookie: SC(tablet)` | active |
| REM-19 | `setView($view, true)` with consent granted | `TAC` | `GET /set-view/tablet?remember=1`, granted | `Set-Cookie: SC(tablet, 31536000)` | active |
| REM-20 | `setView($view)` does not write | — | `GET /set-view/tablet` | 200 `"tablet forced"`, no `Set-Cookie` | active |
| REM-21 | `setConsent(true)` overrides a denied checker | `TAC`, `switch: { target: none }` | `GET /set-consent/1?device_view=mobile`, denied | 200 `"mobile switch"`, `Set-Cookie: SC(mobile, 31536000)` | active |
| REM-22 | `setConsent(false)` overrides a granted checker | `TAC`, `switch: { target: none }` | `GET /set-consent/0?device_view=mobile`, granted | `Set-Cookie: SC(mobile)` | active |
| REM-23 | `setConsent(true)` without a provider | `switch: { target: none }` | `GET /set-consent/1?device_view=mobile` | `Set-Cookie: SC(mobile, 31536000)` | active |
| REM-24 | `setConsent(null)` restores the checker | `TAC`, `switch: { target: none }` | `GET /set-consent/null?device_view=mobile`, granted | `Set-Cookie: SC(mobile, 31536000)` | active |
| REM-25 | Custom `consented_lifetime` | `TAC`, `remember: { consented_lifetime: 15552000 }` | switch, granted | `Set-Cookie: SC(mobile, 15552000)` | active |
| REM-26 | The checker is asked only when a write is planned | `TAC` (checker wrapped in a counting spy) | `GET /view`, `UA=iphone` | 200 `"mobile detector"`; `hasConsent()` called 0 times | active |

## 7. Cookie attributes (CK), design.md section 4

All rows switch with `GET /view?device_view=mobile` (302, `Location: /view`) unless said.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CK-01 | Defaults over HTTP | — | `GET http://localhost/view?device_view=mobile` | `Set-Cookie: device_view=mobile; path=/; httponly; samesite=lax` | active |
| CK-02 | `secure: auto` over HTTPS | — | `GET https://localhost/view?device_view=mobile` | `Set-Cookie: device_view=mobile; path=/; secure; httponly; samesite=lax` | active |
| CK-03 | `secure: true` over HTTP | `remember: { cookie: { secure: true } }` | `GET http://localhost/view?device_view=mobile` | `Set-Cookie: device_view=mobile; path=/; secure; httponly; samesite=lax` | active |
| CK-04 | `secure: false` over HTTPS | `remember: { cookie: { secure: false } }` | `GET https://localhost/view?device_view=mobile` | `Set-Cookie: device_view=mobile; path=/; httponly; samesite=lax` | active |
| CK-05 | Custom name is written | `remember: { cookie: { name: dv } }` | switch | `Set-Cookie: dv=mobile; path=/; httponly; samesite=lax` | active |
| CK-06 | Custom name is read, the default name is not | `remember: { cookie: { name: dv } }` | `GET /view`, `Cookie: dv=tablet; device_view=mobile` | 200 `"tablet remembered"`, no `Set-Cookie` | active |
| CK-07 | Custom path | `remember: { cookie: { path: /shop } }` | switch | `Set-Cookie: device_view=mobile; path=/shop; httponly; samesite=lax` | active |
| CK-08 | Custom domain | `remember: { cookie: { domain: .example.com } }` | switch | `Set-Cookie: device_view=mobile; path=/; domain=.example.com; httponly; samesite=lax` | active |
| CK-09 | `http_only: false` | `remember: { cookie: { http_only: false } }` | switch | `Set-Cookie: device_view=mobile; path=/; samesite=lax` | active |
| CK-10 | `same_site: strict` | `remember: { cookie: { same_site: strict } }` | switch | `Set-Cookie: device_view=mobile; path=/; httponly; samesite=strict` | active |
| CK-11 | `same_site: none` over HTTPS | `remember: { cookie: { same_site: none } }` | `GET https://localhost/view?device_view=mobile` | `Set-Cookie: device_view=mobile; path=/; secure; httponly; samesite=none` | active |
| CK-12 | `same_site: null` | `remember: { cookie: { same_site: null } }` | switch | `Set-Cookie: device_view=mobile; path=/; httponly` | active |
| CK-13 | `raw: true` | `remember: { cookie: { raw: true } }` | switch | `Set-Cookie: SC(mobile)`; the `Cookie` object is raw | active |
| CK-14 | Persistent cookie over HTTPS | `TAC` | `GET https://localhost/view?device_view=mobile`, `Cookie: tarteaucitron=!device_view=true` | `Set-Cookie: SCS(mobile, 31536000)` | active |
| CK-15 | The expired cookie carries the configured attributes | `remember: { cookie: { name: dv, path: /shop, domain: .example.com } }` | `GET /view`, `Cookie: dv=phone` | `Set-Cookie: dv=deleted; expires=E(-31536001); Max-Age=0; path=/shop; domain=.example.com; httponly; samesite=lax` | active |
| CK-16 | `same_site: none` forces `secure`, even over HTTP | `remember: { cookie: { same_site: none } }` | `GET http://localhost/view?device_view=mobile` | `Set-Cookie: device_view=mobile; path=/; secure; httponly; samesite=none` (`secure: auto` would leave it out over HTTP; `same_site: none` adds it whatever `secure` says, and `secure: false` is refused by CFG-26) | active |

## 8. Session storage (SES), design.md sections 4 and 7

Config `remember: { storage: session }`; the test app enables `framework.session` with the mock
file storage. "Previous session" means the request carries the session cookie of a session
created before.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| SES-01 | Switch writes the session | `remember: { storage: session }` | `GET /view?device_view=mobile` | 302, `Location: /view`, no `device_view` cookie; the session holds `_device_view = mobile` | active |
| SES-02 | Reads a previous session | `remember: { storage: session }` | `GET /view`, previous session holding `_device_view = tablet`, `UA=iphone` | 200 `"tablet remembered"`, `Vary: [Cookie]` | planned |
| SES-03 | Never starts a session to read | `remember: { storage: session }` | `GET /view`, no session cookie, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent]`; the session is not started; no session cookie | planned |
| SES-04 | Lifetimes are ignored | `remember: { storage: session }`, `TAC` | `GET /view?device_view=mobile`, `Cookie: tarteaucitron=!device_view=true` | no `device_view` cookie; the session holds `_device_view = mobile`; the session cookie is the framework's, unchanged | active |
| SES-05 | Custom session key | `remember: { storage: session, session: { key: dv } }` | `GET /view?device_view=mobile` | the session holds `dv = mobile` and no `_device_view` | active |
| SES-06 | Detected view written to the session | `remember: { storage: session, detected_view: true }` | `GET /view`, `UA=iphone` | 200; the session holds `_device_view = mobile` | active |
| SES-07 | Detected view with consent denied is not written | `remember: { storage: session, detected_view: true }`, `TAC` | `GET /view`, `UA=iphone`, `Cookie: tarteaucitron=!device_view=false` | 200; the session is not started | active |
| SES-08 | The route default stops a session write | `remember: { storage: session }` | `GET /no-remember?device_view=mobile` | 302, `Location: /no-remember`; the session is not started | planned |

## 9. Storage none and custom storage (STO), design.md sections 4 and 10

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| STO-01 | `storage: none` never writes | `remember: { storage: none }` | `GET /view?device_view=mobile` | 302, `Location: /view`, no `Set-Cookie`, no session | active |
| STO-02 | `storage: none` never reads | `remember: { storage: none }` | `GET /view`, `Cookie: device_view=mobile`, `UA=desktop_chrome` | 200 `"desktop detector"`, `Vary: [User-Agent]` | planned |
| STO-03 | `storage: none` never expires a forged cookie | `remember: { storage: none }` | `GET /view`, `Cookie: device_view=phone` | 200 `"desktop detector"`, no `Set-Cookie` | active |
| STO-04 | Custom storage service | `remember: { storage: app.array_storage }` (a test `ViewStorageInterface` keeping views in memory) | `GET /view?device_view=tablet`, then `GET /view` | 302 without `Set-Cookie`, then 200 `"tablet remembered"` | active |
| STO-05 | The interface alias follows the configuration | `remember: { storage: app.array_storage }` | container | `ViewStorageInterface` and `device_view.storage` resolve to `app.array_storage` | active |

## 10. Consent (CON), design.md section 8

Readers never throw. Unless a row says otherwise, each row runs the reader on the request and
asserts `hasConsent`, then runs `GET /view?device_view=mobile` with the same cookies and asserts
`Set-Cookie: SC(mobile, 31536000)` when `hasConsent` is true, or `Set-Cookie: SC(mobile)` when it
is false or null.

### Generic (CON)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-01 | No provider: no checker is called | — | switch | `Set-Cookie: SC(mobile)`; no `device_view.consent_checker` service | active |
| CON-02 | Custom checker service | `consent: { provider: app.header_consent }` (a test checker: `X-Consent: yes` → true, `no` → false, else null) | switch, `X-Consent: yes` | `hasConsent` true; `Set-Cookie: SC(mobile, 31536000)` | active |
| CON-03 | Custom checker answering null | `consent: { provider: app.header_consent }` | switch, no `X-Consent` | `hasConsent` null; `Set-Cookie: SC(mobile)` | active |
| CON-04 | The interface alias follows the configuration | `consent: { provider: app.header_consent }` | container | `ConsentCheckerInterface` and `device_view.consent_checker` resolve to `app.header_consent` | active |

### tarteaucitron (CON-TAC): cookie `tarteaucitron`, key `device_view`

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-TAC-01 | Granted | `tarteaucitron` | `Cookie: tarteaucitron=!device_view=true` | `hasConsent` true | active |
| CON-TAC-02 | Granted among other services | `tarteaucitron` | `Cookie: tarteaucitron=!gtag=true!device_view=true!youtube=false` | `hasConsent` true | active |
| CON-TAC-03 | Denied | `tarteaucitron` | `Cookie: tarteaucitron=!device_view=false` | `hasConsent` false | active |
| CON-TAC-04 | Waiting | `tarteaucitron` | `Cookie: tarteaucitron=!device_view=wait` | `hasConsent` null | active |
| CON-TAC-05 | Service absent | `tarteaucitron` | `Cookie: tarteaucitron=!gtag=true` | `hasConsent` null | active |
| CON-TAC-06 | No cookie | `tarteaucitron` | no cookie | `hasConsent` null | active |
| CON-TAC-07 | Exact key match only | `tarteaucitron` | `Cookie: tarteaucitron=!device_view_extra=true` | `hasConsent` null | active |
| CON-TAC-08 | Garbage | `tarteaucitron` | `Cookie: tarteaucitron=%%%!!!==` | `hasConsent` null | active |
| CON-TAC-09 | Array cookie | `tarteaucitron` | cookie `tarteaucitron` = `['x' => '1']` | `hasConsent` null, no error | active |
| CON-TAC-10 | Custom cookie name | `consent: { provider: tarteaucitron, cookie: tac }` | `Cookie: tac=!device_view=true; tarteaucitron=!device_view=false` | `hasConsent` true | active |
| CON-TAC-11 | Custom key | `consent: { provider: tarteaucitron, key: dv_pref }` | `Cookie: tarteaucitron=!dv_pref=true!device_view=false` | `hasConsent` true | active |

### CookieConsent v3 (CON-CC): cookie `cc_cookie`, key `preferences`

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-CC-01 | Category accepted | `cookieconsent` | `Cookie: cc_cookie={"categories":["necessary","preferences"],"revision":0}` | `hasConsent` true | active |
| CON-CC-02 | Category not accepted | `cookieconsent` | `Cookie: cc_cookie={"categories":["necessary"],"revision":0}` | `hasConsent` false | active |
| CON-CC-03 | No cookie | `cookieconsent` | no cookie | `hasConsent` null | active |
| CON-CC-04 | Value still URL-encoded | `cookieconsent` | `Cookie: cc_cookie=%7B%22categories%22%3A%5B%22preferences%22%5D%7D` | `hasConsent` true | active |
| CON-CC-05 | `category.service` key, service accepted | `consent: { provider: cookieconsent, key: preferences.device_view }` | `Cookie: cc_cookie={"categories":["preferences"],"services":{"preferences":["device_view"]}}` | `hasConsent` true | active |
| CON-CC-06 | `category.service` key, service not accepted | `consent: { provider: cookieconsent, key: preferences.device_view }` | `Cookie: cc_cookie={"categories":["preferences"],"services":{"preferences":[]}}` | `hasConsent` false | active |
| CON-CC-07 | `category.service` key, category not accepted | `consent: { provider: cookieconsent, key: preferences.device_view }` | `Cookie: cc_cookie={"categories":["necessary"],"services":{"necessary":[]}}` | `hasConsent` false | active |
| CON-CC-08 | Garbage | `cookieconsent` | `Cookie: cc_cookie={not json` | `hasConsent` null | active |
| CON-CC-09 | JSON without categories | `cookieconsent` | `Cookie: cc_cookie={"revision":0}` | `hasConsent` null | active |
| CON-CC-10 | Categories not a list | `cookieconsent` | `Cookie: cc_cookie={"categories":"preferences"}` | `hasConsent` null | active |
| CON-CC-11 | Array cookie | `cookieconsent` | cookie `cc_cookie` = `['categories' => 'preferences']` | `hasConsent` null, no error | active |
| CON-CC-12 | Custom cookie name and key | `consent: { provider: cookieconsent, cookie: cc_site, key: analytics }` | `Cookie: cc_site={"categories":["analytics"]}` | `hasConsent` true | active |

### Klaro (CON-KLA): cookie `klaro`, key `device_view`

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-KLA-01 | Granted | `klaro` | `Cookie: klaro={"device_view":true,"analytics":false}` | `hasConsent` true | active |
| CON-KLA-02 | Denied | `klaro` | `Cookie: klaro={"device_view":false}` | `hasConsent` false | active |
| CON-KLA-03 | Service absent | `klaro` | `Cookie: klaro={"analytics":true}` | `hasConsent` null | active |
| CON-KLA-04 | No cookie | `klaro` | no cookie | `hasConsent` null | active |
| CON-KLA-05 | URL-encoded once | `klaro` | `Cookie: klaro=%7B%22device_view%22%3Atrue%7D` | `hasConsent` true | active |
| CON-KLA-06 | URL-encoded twice | `klaro` | `Cookie: klaro=%257B%2522device_view%2522%253Atrue%257D` | `hasConsent` true | active |
| CON-KLA-07 | Not a boolean | `klaro` | `Cookie: klaro={"device_view":"yes"}` | `hasConsent` null | active |
| CON-KLA-08 | Garbage | `klaro` | `Cookie: klaro=not-json` | `hasConsent` null | active |
| CON-KLA-09 | Array cookie | `klaro` | cookie `klaro` = `['device_view' => '1']` | `hasConsent` null, no error | active |
| CON-KLA-10 | Custom cookie name and key | `consent: { provider: klaro, cookie: klaro-consent, key: views }` | `Cookie: klaro-consent={"views":true}` | `hasConsent` true | active |

### Complianz (CON-CMP): cookie prefix `cmplz_`, key `preferences`

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-CMP-01 | Allowed | `complianz` | `Cookie: cmplz_preferences=allow` | `hasConsent` true | active |
| CON-CMP-02 | Denied | `complianz` | `Cookie: cmplz_preferences=deny` | `hasConsent` false | active |
| CON-CMP-03 | No cookie | `complianz` | no cookie | `hasConsent` null | active |
| CON-CMP-04 | Other value | `complianz` | `Cookie: cmplz_preferences=maybe` | `hasConsent` null | active |
| CON-CMP-05 | Exact value only | `complianz` | `Cookie: cmplz_preferences=ALLOW` | `hasConsent` null | active |
| CON-CMP-06 | Custom key | `consent: { provider: complianz, key: statistics }` | `Cookie: cmplz_statistics=allow; cmplz_preferences=deny` | `hasConsent` true | active |
| CON-CMP-07 | Custom prefix | `consent: { provider: complianz, cookie: site_ }` | `Cookie: site_preferences=allow` | `hasConsent` true | active |
| CON-CMP-08 | Array cookie | `complianz` | cookie `cmplz_preferences` = `['a' => 'allow']` | `hasConsent` null, no error | active |

### Cookiebot (CON-CB): cookie `CookieConsent`, key `preferences`

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-CB-01 | `-1`: no consent needed | `cookiebot` | `Cookie: CookieConsent=-1` | `hasConsent` true | active |
| CON-CB-02 | Granted | `cookiebot` | `Cookie: CookieConsent={stamp:'k7vR4UXCrdIN0JRQ6ZJhvUh1yz6UvC4gOGBQafPnz6ZLKNQiQJn1Ow==',necessary:true,preferences:true,statistics:false,marketing:false,method:'explicit',ver:1,utc:1792483200000,region:'fr'}` | `hasConsent` true | active |
| CON-CB-03 | Denied | `cookiebot` | same value with `preferences:false` | `hasConsent` false | active |
| CON-CB-04 | Key absent | `cookiebot` | `Cookie: CookieConsent={stamp:'x',necessary:true}` | `hasConsent` null | active |
| CON-CB-05 | No cookie | `cookiebot` | no cookie | `hasConsent` null | active |
| CON-CB-06 | Garbage | `cookiebot` | `Cookie: CookieConsent={preferences:` | `hasConsent` null | active |
| CON-CB-07 | `0`: no cookie accepted | `cookiebot` | `Cookie: CookieConsent=0` | `hasConsent` false | active |
| CON-CB-08 | Array cookie | `cookiebot` | cookie `CookieConsent` = `['preferences' => 'true']` | `hasConsent` null, no error | active |
| CON-CB-09 | Custom key | `consent: { provider: cookiebot, key: statistics }` | the CON-CB-02 value | `hasConsent` false | active |
| CON-CB-10 | Custom cookie name | `consent: { provider: cookiebot, cookie: CookieConsentSite }` | `Cookie: CookieConsentSite=-1` | `hasConsent` true | active |

### OneTrust (CON-OT): cookie `OptanonConsent`, key `C0003`

`<OT groups=…>` stands for
`isGpcEnabled=0&datestamp=Tue Oct 20 2026 10:00:00 GMT+0200&version=202409.1.0&isIABGlobal=false&hosts=&consentId=0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0&interactionCount=1&landingPath=NotLandingPage&groups=…`,
the value PHP gives after decoding the cookie.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-OT-01 | Granted | `onetrust` | `Cookie: OptanonConsent=<OT groups=C0001:1,C0002:0,C0003:1,C0004:0>` | `hasConsent` true | active |
| CON-OT-02 | Denied | `onetrust` | `Cookie: OptanonConsent=<OT groups=C0001:1,C0002:0,C0003:0,C0004:0>` | `hasConsent` false | active |
| CON-OT-03 | Group absent | `onetrust` | `Cookie: OptanonConsent=<OT groups=C0001:1,C0002:1>` | `hasConsent` null | active |
| CON-OT-04 | No `groups` parameter | `onetrust` | `Cookie: OptanonConsent=isGpcEnabled=0&interactionCount=0` | `hasConsent` null | active |
| CON-OT-05 | No cookie | `onetrust` | no cookie | `hasConsent` null | active |
| CON-OT-06 | Value still URL-encoded | `onetrust` | `Cookie: OptanonConsent=isGpcEnabled%3D0%26groups%3DC0001%253A1%252CC0003%253A1` | `hasConsent` true | active |
| CON-OT-07 | Garbage | `onetrust` | `Cookie: OptanonConsent=%%%` | `hasConsent` null | active |
| CON-OT-08 | Array cookie | `onetrust` | cookie `OptanonConsent` = `['groups' => 'C0003:1']` | `hasConsent` null, no error | active |
| CON-OT-09 | Custom key | `consent: { provider: onetrust, key: C0002 }` | the CON-OT-01 value | `hasConsent` false | active |
| CON-OT-10 | Custom cookie name | `consent: { provider: onetrust, cookie: OptanonConsentSite }` | `Cookie: OptanonConsentSite=<OT groups=C0003:1>` | `hasConsent` true | active |

### Axeptio (CON-AX): cookie `axeptio_cookies`, key `device_view`

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-AX-01 | Granted | `axeptio` | `Cookie: axeptio_cookies={"$$token":"k1x8b3","$$date":"2026-10-20T10:00:00.000Z","$$cookiesVersion":{"name":"site-en","identifier":"64f0"},"device_view":true,"$$completed":true}` | `hasConsent` true | active |
| CON-AX-02 | Denied | `axeptio` | same value with `"device_view":false` | `hasConsent` false | active |
| CON-AX-03 | Not completed | `axeptio` | `Cookie: axeptio_cookies={"device_view":true,"$$completed":false}` | `hasConsent` null | active |
| CON-AX-04 | Vendor absent | `axeptio` | `Cookie: axeptio_cookies={"$$completed":true,"analytics":true}` | `hasConsent` null | active |
| CON-AX-05 | No cookie | `axeptio` | no cookie | `hasConsent` null | active |
| CON-AX-06 | Value still URL-encoded | `axeptio` | `Cookie: axeptio_cookies=%7B%22%24%24completed%22%3Atrue%2C%22device_view%22%3Atrue%7D` | `hasConsent` true | active |
| CON-AX-07 | Not a boolean | `axeptio` | `Cookie: axeptio_cookies={"$$completed":true,"device_view":1}` | `hasConsent` null | active |
| CON-AX-08 | Garbage | `axeptio` | `Cookie: axeptio_cookies=[[[` | `hasConsent` null | active |
| CON-AX-09 | Array cookie | `axeptio` | cookie `axeptio_cookies` = `['device_view' => 'true']` | `hasConsent` null, no error | active |
| CON-AX-10 | Custom cookie name and key | `consent: { provider: axeptio, cookie: axeptio_site, key: views }` | `Cookie: axeptio_site={"$$completed":true,"views":true}` | `hasConsent` true | active |

### Didomi (CON-DID): cookie `didomi_token`, key `device_view`

`B64(json)` is the standard base64 of the JSON. `G` is
`{"user_id":"0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0","created":"2026-10-20T10:00:00.000Z","updated":"2026-10-20T10:00:00.000Z","purposes":{"enabled":["device_view"],"disabled":[]},"vendors":{"enabled":["c:analytics"],"disabled":["c:ads"]},"version":2}`.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CON-DID-01 | Purpose enabled | `didomi` | `Cookie: didomi_token=B64(G)` | `hasConsent` true | active |
| CON-DID-02 | Purpose disabled | `didomi` | `G` with `"purposes":{"enabled":[],"disabled":["device_view"]}` | `hasConsent` false | active |
| CON-DID-03 | Purpose in neither list | `didomi` | `G` with `"purposes":{"enabled":["other"],"disabled":[]}` | `hasConsent` null | active |
| CON-DID-04 | URL-safe base64 without padding | `didomi` | `B64(G)` with `+` → `-`, `/` → `_` and the `=` removed | `hasConsent` true | active |
| CON-DID-05 | `+` read back from a space | `didomi` | `Cookie: didomi_token=` the token below with every `+` replaced by a space (what PHP gives for a raw `+`) | `hasConsent` true | active |
| CON-DID-06 | Vendor key, enabled | `consent: { provider: didomi, key: 'vendors:c:analytics' }` | `B64(G)` | `hasConsent` true | active |
| CON-DID-07 | Vendor key, disabled | `consent: { provider: didomi, key: 'vendors:c:ads' }` | `B64(G)` | `hasConsent` false | active |
| CON-DID-08 | Not base64 | `didomi` | `Cookie: didomi_token=***` | `hasConsent` null | active |
| CON-DID-09 | Base64 of something that is not JSON | `didomi` | `Cookie: didomi_token=bm90IGpzb24=` | `hasConsent` null | active |
| CON-DID-10 | No cookie | `didomi` | no cookie | `hasConsent` null | active |
| CON-DID-11 | Array cookie | `didomi` | cookie `didomi_token` = `['purposes' => 'x']` | `hasConsent` null, no error | active |
| CON-DID-12 | Custom cookie name | `consent: { provider: didomi, cookie: didomi_site }` | `Cookie: didomi_site=B64(G)` | `hasConsent` true | active |

The token with `+` and `=` used by CON-DID-05 and HTTP-03 (the base64 of
`{"user_id":"0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f00","purposes":{"enabled":["device_view"],"disabled":[]},"vendors":{"enabled":["c:dv~test"],"disabled":[]},"version":2}`):

```
eyJ1c2VyX2lkIjoiMGYxZTJkM2MtNGI1YS02OTc4LTg3OTYtYTViNGMzZDJlMWYwMCIsInB1cnBvc2VzIjp7ImVuYWJsZWQiOlsiZGV2aWNlX3ZpZXciXSwiZGlzYWJsZWQiOltdfSwidmVuZG9ycyI6eyJlbmFibGVkIjpbImM6ZHZ+dGVzdCJdLCJkaXNhYmxlZCI6W119LCJ2ZXJzaW9uIjoyfQ==
```

## 11. Device redirects (RED), design.md sections 4, 5 and 6

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| RED-01 | Mobile redirect | `M` | `GET http://www.example.com/blog/post?page=2`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?page=2&device_view=mobile`, `Cache-Control: NS`, `Vary: [Cookie, User-Agent]` | planned |
| RED-02 | Desktop redirect | `D` | `GET https://m.example.com/blog/post`, `UA=desktop_chrome` | 302, `Location: https://www.example.com/blog/post?device_view=desktop`, `Cache-Control: NS` | active |
| RED-03 | Tablet redirect | `T` | `GET https://www.example.com/`, `UA=ipad_legacy` | 302, `Location: https://t.example.com/?device_view=tablet` | active |
| RED-04 | No redirect on the target host | `M` | `GET https://m.example.com/blog/post`, `UA=iphone` | 200 `"mobile detector"` | active |
| RED-05 | No redirect for a view without one | `M` | `GET http://www.example.com/blog/post`, `UA=desktop_chrome` | 200 `"desktop detector"` | active |
| RED-06 | `action: redirect_without_path` | `redirect: { mobile: { enabled: true, host: 'https://m.example.com', action: redirect_without_path } }` | `GET http://www.example.com/blog/post?page=2`, `UA=iphone` | 302, `Location: https://m.example.com/?device_view=mobile` | active |
| RED-07 | `action: none` | `redirect: { mobile: { enabled: true, host: 'https://m.example.com', action: none } }` | `GET http://www.example.com/blog/post`, `UA=iphone` | 200 `"mobile detector"` | active |
| RED-08 | `keep_query: false` | `redirect: { mobile: { enabled: true, host: 'https://m.example.com', keep_query: false } }` | `GET http://www.example.com/blog/post?page=2`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?device_view=mobile` | active |
| RED-09 | Base path in the host is kept | `redirect: { mobile: { enabled: true, host: 'https://m.example.com/shop' } }` | `GET http://www.example.com/blog/post?page=2`, `UA=iphone` | 302, `Location: https://m.example.com/shop/blog/post?page=2&device_view=mobile` | active |
| RED-10 | Base path with `redirect_without_path` | `redirect: { mobile: { enabled: true, host: 'https://m.example.com/shop', action: redirect_without_path } }` | `GET http://www.example.com/blog/post`, `UA=iphone` | 302, `Location: https://m.example.com/shop/?device_view=mobile` | active |
| RED-11 | The current base URL is not carried | `M` | front controller `/app`: `GET http://www.example.com/app/blog/post`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?device_view=mobile` | active |
| RED-12 | Port in the host | `redirect: { mobile: { enabled: true, host: 'http://m.example.com:8080' } }` | `GET http://m.example.com/`, `UA=iphone` | 302, `Location: http://m.example.com:8080/?device_view=mobile` | active |
| RED-13 | Same host and port | `redirect: { mobile: { enabled: true, host: 'http://m.example.com:8080' } }` | `GET http://m.example.com:8080/view`, `UA=iphone` | 200 `"mobile detector"` | active |
| RED-14 | Default port in the host | `redirect: { mobile: { enabled: true, host: 'https://m.example.com:443' } }` | `GET https://m.example.com/view` `UA=iphone`; then `GET http://www.example.com/` `UA=iphone` | 200 `"mobile detector"`; then 302, `Location: https://m.example.com/?device_view=mobile` | active |
| RED-15 | Host case is ignored | `M` | `GET https://M.Example.COM/view`, `UA=iphone` | 200 `"mobile detector"` | active |
| RED-16 | Trailing dot of the request host is ignored | `M` | `GET https://m.example.com./view`, `UA=iphone` | 200 `"mobile detector"` | active |
| RED-17 | Trailing dot and slash of the configured host are ignored | `redirect: { mobile: { enabled: true, host: 'https://m.example.com./' } }` | `GET https://m.example.com/view` `UA=iphone`; then `GET http://www.example.com/blog/post` `UA=iphone` | 200 `"mobile detector"`; then 302, `Location: https://m.example.com/blog/post?device_view=mobile` | active |
| RED-18 | Status 301 | `redirect: { mobile: { enabled: true, host: 'https://m.example.com', status: 301 } }` | `GET http://www.example.com/`, `UA=iphone` | 301, `Cache-Control: NS`, `Vary: [Cookie, User-Agent]` | planned |
| RED-19 | Status 308 | `redirect: { mobile: { enabled: true, host: 'https://m.example.com', status: 308 } }` | `GET http://www.example.com/`, `UA=iphone` | 308, `Location: https://m.example.com/?device_view=mobile` | active |
| RED-20 | POST is not redirected | `M` | `POST http://www.example.com/form`, `UA=iphone` | 200 `"mobile detector"` | active |
| RED-21 | HEAD is redirected | `M` | `HEAD http://www.example.com/form`, `UA=iphone` | 302, `Location: https://m.example.com/form?device_view=mobile` | active |
| RED-22 | Custom methods | `M`, `redirect: { methods: [GET] }` | `HEAD http://www.example.com/form`, `UA=iphone` | 200 | active |
| RED-23 | `cache_control: null` | `M`, `redirect: { cache_control: null }` | `GET http://www.example.com/`, `UA=iphone` | 302, `Cache-Control: no-cache, private` | active |
| RED-24 | A remembered view decides the redirect | `D` | `GET https://m.example.com/`, `Cookie: device_view=desktop`, `UA=iphone` | 302, `Location: https://www.example.com/?device_view=desktop`, `Vary: [Cookie]` | planned |
| RED-25 | The query is encoded with RFC 3986 | `M` | `GET http://www.example.com/blog/post?q=a+b`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?q=a%20b&device_view=mobile` | active |
| RED-26 | Host from a valid env var | `redirect: { mobile: { enabled: true, host: '%env(DV_MOBILE_HOST)%' } }`, `DV_MOBILE_HOST=https://m.example.com` | `GET http://www.example.com/blog/post`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?device_view=mobile` | active |
| RED-27 | Host from an invalid env var | as RED-26 with `DV_MOBILE_HOST=m.example.com` | `GET http://www.example.com/view`, `UA=iphone` | 200 `"mobile detector"`; error log `The redirect host "m.example.com" of the mobile view is invalid: expected an absolute http(s) URL without query, fragment or user info. The mobile redirect is disabled.` | active |
| RED-28 | Cross-host warning without a cookie domain | `M` | `GET http://www.example.com/`, `UA=iphone` | 302; warning log `The mobile redirect goes to m.example.com, but the device_view cookie has no domain, so that host cannot read the remembered view. Set device_view.remember.cookie.domain to a domain both hosts share.` | active |
| RED-29 | No warning with a cookie domain | `M`, `remember: { cookie: { domain: .example.com } }` | `GET http://www.example.com/`, `UA=iphone` | 302; no warning log | active |
| RED-30 | No warning without cookie storage | `M`, `remember: { storage: session }` | `GET http://www.example.com/`, `UA=iphone` | 302; no warning log | active |
| RED-31 | The Host header never reaches `Location` | `M` | `GET http://evil.example/blog/post`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?device_view=mobile` | active |
| RED-32 | Sub-requests are never redirected | `M` | `GET https://m.example.com/with-sub-request?sub=http%3A%2F%2Fwww.example.com%2Fview`, `UA=iphone` | 200 `"main=mobile sub=mobile detector"`, no `Location` | active |
| RED-33 | Disabled redirects leave the view lazy | — | `GET /`, `UA=iphone` | 200 `"home page"`, no `Vary` | active |
| RED-34 | Enabled redirects resolve the view | `M` | `GET https://m.example.com/`, `UA=iphone` | 200 `"home page"`, `Vary: [Cookie, User-Agent]` | planned |
| RED-35 | The host forwarded by a trusted proxy is the current host | `M` | trusted proxy `127.0.0.1` with the `X-Forwarded-For`, `X-Forwarded-Host` and `X-Forwarded-Proto` headers (Conventions, Trusted proxies); `GET http://10.0.0.5/view`, `X-Forwarded-Host: m.example.com`, `X-Forwarded-Proto: https`, `UA=iphone`; then the same request with `REMOTE_ADDR: 203.0.113.9` | 200 `"mobile detector"`; then 302, `Location: https://m.example.com/view?device_view=mobile` (an untrusted client's forwarded host is ignored) | active |

## 12. Per-route overrides (RTE), design.md sections 4 and 7

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| RTE-01 | `_device_view_redirect: none` | `M` | `GET http://www.example.com/no-redirect`, `UA=iphone` | 200 `"mobile detector"` | planned |
| RTE-02 | `_device_view_redirect: redirect_without_path` | `M` | `GET http://www.example.com/without-path?a=1`, `UA=iphone` | 302, `Location: https://m.example.com/?device_view=mobile` | planned |
| RTE-03 | `_device_view_redirect: redirect` over `action: none` | `redirect: { mobile: { enabled: true, host: 'https://m.example.com', action: none } }` | `GET http://www.example.com/always-redirect`, `UA=iphone` | 302, `Location: https://m.example.com/always-redirect?device_view=mobile` | planned |
| RTE-04 | Invalid redirect default is ignored | `M` | `GET http://www.example.com/bad-default`, `UA=iphone` | 302, `Location: https://m.example.com/bad-default?device_view=mobile`; debug log `Ignored the invalid route default _device_view_redirect "sometimes".` | planned |
| RTE-05 | `_device_view_redirect: none` keeps the switch | `M` | `GET http://www.example.com/no-redirect?device_view=desktop`, `UA=iphone` | 302, `Location: /no-redirect`, `Set-Cookie: SC(desktop)` | planned |
| RTE-06 | `_device_view_remember: false` stops a switch write | — | `GET /no-remember?device_view=mobile` | 302, `Location: /no-remember`, no `Set-Cookie` | planned |
| RTE-07 | `_device_view_remember: false` stops a detected write | `remember: { detected_view: true }` | `GET /no-remember`, `UA=iphone` | 200 `"mobile detector"`, no `Set-Cookie` | planned |
| RTE-08 | `_device_view_remember: 'false'` as a string | — | `GET /no-remember-xml?device_view=mobile` | 302, no `Set-Cookie` | planned |
| RTE-09 | `_device_view_remember: true` does not bypass consent | `TAC`, `remember: { detected_view: true }` | `GET /remember`, `UA=iphone`, `Cookie: tarteaucitron=!device_view=false` | 200 `"mobile detector"`, no `Set-Cookie` | planned |
| RTE-10 | Custom route default keys | `M`, `route_defaults: { redirect: _dv_redirect, remember: _dv_remember }` | `GET http://www.example.com/custom-keys` `UA=iphone`; then `GET http://www.example.com/custom-keys?device_view=desktop` `UA=iphone`; then `GET http://www.example.com/no-redirect` `UA=iphone` | 200 `"mobile detector"`, no `Location` (`_dv_redirect: none` is read); then 302, `Location: /custom-keys`, no `Set-Cookie` (`_dv_remember: false` is read); then 302, `Location: https://m.example.com/no-redirect?device_view=mobile` (the default key is no longer read) | planned |

## 13. Vary (VARY), design.md section 6

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| VARY-01 | View never used | — | `GET /`, `UA=iphone` | no `Vary` | planned |
| VARY-02 | Detector decided | — | `GET /view`, `UA=iphone` | `Vary: [Cookie, User-Agent]` | planned |
| VARY-03 | Remembered decided | — | `GET /view`, `Cookie: device_view=tablet` | `Vary: [Cookie]` | planned |
| VARY-04 | Switch decided | `switch: { target: none }` | `GET /view?device_view=tablet` | no `Vary` | planned |
| VARY-05 | Trusted header decided | `TH` | `GET /view`, `X-Device-View: tablet` | `Vary: [Cookie, X-Device-View]` | planned |
| VARY-06 | Appended to the controller's values | — | `GET /view`, `UA=iphone`, the controller sets `Vary: Accept-Language` | `Vary: [Accept-Language, Cookie, User-Agent]` | planned |
| VARY-07 | No duplicates | — | `GET /view`, `UA=iphone`, the controller sets `Vary: user-agent` | `Vary: [user-agent, Cookie]` | planned |
| VARY-08 | `http_cache.vary: false` | `http_cache: { vary: false }` | `GET /view`, `UA=iphone` | no `Vary` | planned |
| VARY-09 | Static detector varies on nothing | `detector: { id: static }` | `GET /view` | `Vary: [Cookie]` | planned |
| VARY-10 | `storage: none` does not vary on `Cookie` | `remember: { storage: none }` | `GET /view`, `UA=iphone` | `Vary: [User-Agent]` | planned |
| VARY-11 | A forced view varies on nothing | — | `GET /set-view/mobile` | no `Vary` | planned |
| VARY-12 | Sub-request responses get no `Vary` | — | `GET /with-sub-request`, `UA=iphone` | the sub-request response has no `Vary`; the main response has `Vary: [Cookie, User-Agent]` | planned |

## 14. Cache-Control (HC), design.md sections 4 and 6

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| HC-01 | A response writing the cookie becomes private | `remember: { detected_view: true }` | `GET /cacheable`, `UA=iphone` | `Set-Cookie: SC(mobile)`, `Cache-Control: max-age=3600, private` | active |
| HC-02 | `private_when_remembering: false` | `remember: { detected_view: true }`, `http_cache: { private_when_remembering: false }` | `GET /cacheable`, `UA=iphone` | `Set-Cookie: SC(mobile)`, `Cache-Control: max-age=3600, public` | active |
| HC-03 | A response without the cookie stays public | — | `GET /cacheable`, `Cookie: device_view=mobile` | no `Set-Cookie`, `Cache-Control: max-age=3600, public`, `Vary: [Cookie]` | planned |
| HC-04 | Expiring a forged cookie also makes it private | — | `GET /cacheable`, `Cookie: device_view=phone` | `Set-Cookie: SC-DEL`, `Cache-Control: max-age=3600, private` | active |

## 15. Trusted header (TH), design.md sections 4 and 5

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| TH-01 | Read from a trusted proxy | `TH` | `GET /view`, `X-Device-View: tablet`, `UA=iphone` | 200 `"tablet trusted_header"`, `Vary: [Cookie, X-Device-View]` | planned |
| TH-02 | Ignored from an untrusted client | `TH` | `GET /view` with `REMOTE_ADDR: 203.0.113.9`, `X-Device-View: tablet`, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent]` | planned |
| TH-03 | `trusted_proxies_only: false` | `trusted_header: { name: X-Device-View, trusted_proxies_only: false }` | as TH-02 | 200 `"tablet trusted_header"` | planned |
| TH-04 | Value is trimmed and lower-cased | `TH` | `GET /view`, `X-Device-View: " Tablet "` | 200 `"tablet trusted_header"` | planned |
| TH-05 | Not configured: never read | — | `GET /view`, `X-Device-View: tablet`, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent]` | planned |

## 16. Skipped requests (SKIP), design.md sections 4 and 5

A skipped request still resolves the view when asked, but it never switches (the switch
parameter is ignored), redirects, remembers or expires a stored value.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| SKIP-01 | The fragment path is skipped | `M`, `remember: { detected_view: true }`, `framework.fragments: true` | `GET http://www.example.com/_fragment?…` (a signed fragment URL rendering the `view` controller), `UA=iphone`, `Cookie: device_view=phone` | 200 `"mobile detector"`, no `Location`, no `Set-Cookie` | planned |
| SKIP-02 | `skip.fragments: false` | as SKIP-01, plus `skip: { fragments: false }` | as SKIP-01 | 302, `Location` = `https://m.example.com/_fragment?` + the query of the signed URL the test sent, rebuilt as in RED-25 (same parameter order, RFC 3986 encoding) + `&device_view=mobile`; the test computes this value from the URL it signed | planned |
| SKIP-03 | A custom fragment path is skipped | as SKIP-01 with `framework.fragments.path: /_frag` | the signed `/_frag?…` URL | 200 `"mobile detector"`, no `Location`, no `Set-Cookie` | planned |
| SKIP-04 | `/_fragment` when fragments are off | `M`, `remember: { detected_view: true }` (no `framework.fragments`, route `fragment_stub`) | `GET http://www.example.com/_fragment`, `UA=iphone` | 200 `"mobile detector"`, no `Location`, no `Set-Cookie` | planned |
| SKIP-05 | Profiler paths are skipped by default | `M` (routes `profiler_stub` and `wdt_stub`) | `GET http://www.example.com/_profiler/abc`, then `GET http://www.example.com/_wdt/abc`, `UA=iphone` | 200 `"mobile detector"` each time, no `Location` | planned |
| SKIP-06 | Custom skip paths | `skip: { paths: ['^/api'] }` | `GET /api/thing?device_view=mobile`, `UA=desktop_chrome` | 200 `"desktop detector"`, no `Location`, no `Set-Cookie` | planned |
| SKIP-07 | Custom paths replace the defaults | `M`, `skip: { paths: ['^/api'] }` (route `profiler_stub`) | `GET http://www.example.com/_profiler/abc`, `UA=iphone` | 302, `Location: https://m.example.com/_profiler/abc?device_view=mobile` | planned |
| SKIP-08 | A skipped request still resolves and varies | `skip: { paths: ['^/api'] }` | `GET /api/thing`, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent]` | planned |
| SKIP-09 | Paths match the path info | `skip: { paths: ['^/api'] }` | front controller `/app`: `GET http://localhost/app/api/thing?device_view=mobile` | 200 `"desktop detector"`, no `Location` | planned |

## 17. Accept-CH (ACH), design.md sections 4 and 6

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| ACH-01 | Sent when the view was used | `detector: { client_hints: { accept_ch: true } }` | `GET /view` | `Accept-CH: Sec-CH-UA-Mobile` | planned |
| ACH-02 | Not sent when the view was not used | `detector: { client_hints: { accept_ch: true } }` | `GET /` | no `Accept-CH` | planned |
| ACH-03 | Off by default | — | `GET /view` | no `Accept-CH` | planned |
| ACH-04 | Appended to the controller's value | `detector: { client_hints: { accept_ch: true } }` | `GET /view`, the controller sets `Accept-CH: Sec-CH-UA-Model` | `Accept-CH: Sec-CH-UA-Model, Sec-CH-UA-Mobile` | planned |

## 18. Events (EVT), design.md section 10

### ViewResolvedEvent (EVT-RES)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| EVT-RES-01 | A listener changes the view, the source stays | a listener calling `setView(DeviceType::Tablet)` | `GET /view`, `UA=iphone` | 200 `"tablet detector"` | planned |
| EVT-RES-02 | Getters | a recording listener | `GET /view`, `UA=iphone` | `getView()` Mobile, `getSource()` Detector, `getRequest()` is the main request | planned |
| EVT-RES-03 | Dispatched once per main request | a counting listener | `GET /with-sub-request`, `UA=iphone` | 1 call | planned |
| EVT-RES-04 | Not dispatched when the view is not used | a counting listener | `GET /`, `UA=iphone` | 0 calls | planned |
| EVT-RES-05 | Dispatched after `tablet_as_mobile` | `tablet_as_mobile: true`, a recording listener | `GET /view`, `UA=ipad_legacy` | `getView()` Mobile | planned |
| EVT-RES-06 | The listener's view drives the redirect | `M`, a listener setting Mobile | `GET http://www.example.com/`, `UA=desktop_chrome` | 302, `Location: https://m.example.com/?device_view=mobile` | planned |
| EVT-RES-07 | Event name | a listener on `ViewResolvedEvent::class` (on lines without FQCN event names, on `DeviceViewEvents::VIEW_RESOLVED`) | `GET /view` | the listener is called | planned |

### BeforeRedirectEvent (EVT-RED)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| EVT-RED-01 | Switch redirect getters | a recording listener | `GET /view?device_view=mobile` | `getReason()` `switch`, `getUrl()` `/view`, `getStatus()` 302 | planned |
| EVT-RED-02 | Device redirect getters | `M`, a recording listener | `GET http://www.example.com/blog/post`, `UA=iphone` | `getReason()` `device`, `getUrl()` `https://m.example.com/blog/post?device_view=mobile`, `getStatus()` 302 | planned |
| EVT-RED-03 | `setUrl()` | a listener calling `setUrl('/elsewhere')` | `GET /view?device_view=mobile` | 302, `Location: /elsewhere` | planned |
| EVT-RED-04 | `setStatus()` | `M`, a listener calling `setStatus(303)` | `GET http://www.example.com/`, `UA=iphone` | 303 | planned |
| EVT-RED-05 | Cancelling a switch redirect keeps the switch | a listener calling `cancel()` | `GET /view?device_view=mobile` | 200 `"mobile switch"`, `Set-Cookie: SC(mobile)`, no `Location` | planned |
| EVT-RED-06 | Cancelling a device redirect | `M`, a listener calling `cancel()` | `GET http://www.example.com/view`, `UA=iphone` | 200 `"mobile detector"`, `Vary: [Cookie, User-Agent]`, no `Location` | planned |
| EVT-RED-07 | Invalid status | a listener calling `setStatus(200)` | `GET /view?device_view=mobile` | `InvalidArgumentException`, message `The redirect status must be one of 301, 302, 303, 307, 308; got 200.` | planned |
| EVT-RED-08 | Event name | a listener on `BeforeRedirectEvent::class` (on lines without FQCN event names, on `DeviceViewEvents::BEFORE_REDIRECT`) | `GET /view?device_view=mobile` | the listener is called | planned |

### BeforeRememberEvent (EVT-REM)

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| EVT-REM-01 | Switch getters | a recording listener | `GET /view?device_view=mobile` | `getTrigger()` `switch`, `getView()` Mobile, `getConsent()` null, `getLifetime()` 0 | planned |
| EVT-REM-02 | Detected getters | `remember: { detected_view: true }`, a recording listener | `GET /view`, `UA=iphone` | `getTrigger()` `detected`, `getView()` Mobile | planned |
| EVT-REM-03 | Consent getters | `TAC`, a recording listener | `GET /view?device_view=mobile`, `Cookie: tarteaucitron=!device_view=true` | `getConsent()` true, `getLifetime()` 31536000 | planned |
| EVT-REM-04 | `cancel()` | a listener calling `cancel()` | `GET /view?device_view=mobile` | 302, `Location: /view`, no `Set-Cookie` | planned |
| EVT-REM-05 | `setLifetime()` | a listener calling `setLifetime(3600)` | `GET /view?device_view=mobile` | `Set-Cookie: SC(mobile, 3600)` | planned |
| EVT-REM-06 | `setLifetime(0)` makes a session cookie | `TAC`, a listener calling `setLifetime(0)` | `GET /view?device_view=mobile`, `Cookie: tarteaucitron=!device_view=true` | `Set-Cookie: SC(mobile)` | planned |
| EVT-REM-07 | Not dispatched when the table says no | `TAC`, `remember: { detected_view: true }`, a counting listener | `GET /view`, `UA=iphone`, `Cookie: tarteaucitron=!device_view=false` | 0 calls | planned |
| EVT-REM-08 | Cancelling a session write | `remember: { storage: session }`, a listener calling `cancel()` | `GET /view?device_view=mobile` | the session is not started | planned |
| EVT-REM-09 | Negative lifetime | a listener calling `setLifetime(-1)` | `GET /view?device_view=mobile` | `InvalidArgumentException`, message `The remember lifetime must be 0 or more seconds; got -1.` | planned |
| EVT-REM-10 | Event name | a listener on `BeforeRememberEvent::class` (on lines without FQCN event names, on `DeviceViewEvents::BEFORE_REMEMBER`) | `GET /view?device_view=mobile` | the listener is called | planned |

## 19. Twig (TWIG), design.md section 10

The kernel registers TwigBundle and the `twig` route, unless a row says otherwise.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| TWIG-01 | Mobile | — | `GET /twig`, `UA=iphone` | 200 `"mobile\|100\|0\|/twig?device_view=desktop\|/?device_view=mobile"` | planned |
| TWIG-02 | Tablet | — | `GET /twig`, `UA=ipad_legacy` | 200 `"tablet\|010\|1\|/twig?device_view=desktop\|/?device_view=mobile"` | planned |
| TWIG-03 | Desktop | — | `GET /twig`, `UA=desktop_chrome` | 200 `"desktop\|001\|0\|/twig?device_view=desktop\|/?device_view=mobile"`, `Vary: [Cookie, User-Agent]` | planned |
| TWIG-04 | Switch URL keeps and escapes the query | — | `GET /twig?a=1`, `UA=iphone` | 200 `"mobile\|100\|0\|/twig?a=1&amp;device_view=desktop\|/?device_view=mobile"` | planned |
| TWIG-05 | Prefix | `twig: { prefix: dv_ }` | render `{{ dv_device_view() }}` `UA=iphone`; render `{{ device_view() }}` | `"mobile"`; Twig `SyntaxError`, message contains `Unknown "device_view" function` | planned |
| TWIG-06 | `twig.enabled: false` | `twig: { enabled: false }` | render `{{ device_view() }}` | Twig `SyntaxError`, message contains `Unknown "device_view" function` | planned |
| TWIG-07 | Without TwigBundle | kernel without TwigBundle | container build | builds; no Twig extension service of the bundle | planned |
| TWIG-08 | Renamed views | `views: { names: { mobile: m } }` | render `{{ device_view() }}\|{{ is_device_view('m') ? 1 : 0 }}\|{{ is_mobile_view() ? 1 : 0 }}` `UA=iphone` | `"m\|1\|1"` | planned |
| TWIG-09 | Unknown view name | — | render `{{ is_device_view('phone') }}` | Twig `RuntimeError` whose previous exception is `InvalidArgumentException`, message `Unknown device view "phone". Expected one of: mobile, tablet, desktop.` | planned |
| TWIG-10 | The runtime is lazy | — | render a template that calls no bundle function | the bundle's Twig runtime is not instantiated | planned |

## 20. Routing conditions (ROUT), design.md section 10

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| ROUT-01 | `device_view()` with the dumped matcher | kernel `debug: false` | `GET /mobile-only` `UA=iphone`; then `UA=desktop_chrome` | 200 `"mobile only"`, `Vary: [Cookie, User-Agent]`; then 404 | planned |
| ROUT-02 | `device_view()` in debug | kernel `debug: true` | as ROUT-01 | as ROUT-01 | planned |
| ROUT-03 | `is_device_view()` | kernel `debug: false`, then `debug: true` | `GET /is-mobile-only` `UA=iphone`; then `UA=desktop_chrome` | 200; then 404, in both modes | planned |
| ROUT-04 | The switch parameter applies to conditions | — | `GET /mobile-only?device_view=mobile`, `UA=desktop_chrome`; then `GET /mobile-only` with the cookie | 302, `Location: /mobile-only`, `Set-Cookie: SC(mobile)`; then 200 `"mobile only"` | planned |
| ROUT-05 | Renamed views | `views: { names: { mobile: m } }` | `GET /m-only`, `UA=iphone` | 200 `"m only"` | planned |
| ROUT-06 | `routing.expression_functions: false` | `routing: { expression_functions: false }` | `GET /mobile-only` | exception, message contains `The function "device_view" does not exist` | planned |
| ROUT-07 | `router:match` uses `default_view` | `default_view: mobile` | `bin/console router:match /mobile-only` | the `mobile_only` route matches; with the baseline `default_view: desktop`, no route matches | planned |

## 21. Profiler (PROF), design.md section 10

The kernel runs with `debug: true`, TwigBundle, WebProfilerBundle and
`framework.profiler: { collect: true }`. Collector name `device_view`.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| PROF-01 | Detected view | — | `GET /view`, `UA=iphone` | collector: view `mobile`, source `detector`, detector `mobile_detect`, inputs `[cookie device_view, header User-Agent]`, consent null, remember `{written: false}`, redirect `{decision: none}`, vary `[Cookie, User-Agent]`, warnings `[]` | planned |
| PROF-02 | Switch | — | `GET /view?device_view=mobile` | collector: source `switch`, remember `{written: true, lifetime: 0}`, redirect `{decision: switch, target: /view}` | planned |
| PROF-03 | Consent | `TAC` | `GET /view?device_view=mobile`, `Cookie: tarteaucitron=!device_view=true` | collector: consent true, remember `{written: true, lifetime: 31536000}` | planned |
| PROF-04 | Device redirect | `M` | `GET http://www.example.com/`, `UA=iphone` | collector: redirect `{decision: device, target: https://m.example.com/?device_view=mobile}`, warnings has the RED-28 message | planned |
| PROF-05 | View not used | — | `GET /` | collector: view null (not resolved) | planned |
| PROF-06 | The panel renders | — | `GET /view` `UA=iphone`, then `GET /_profiler/<token>?panel=device_view` | 200; the body contains `mobile`, `detector` and `mobile_detect` | planned |
| PROF-07 | `profiler: false` | `profiler: false` | `GET /view` | the profile has no `device_view` collector | planned |
| PROF-08 | Follows `kernel.debug` by default | kernel `debug: false` with WebProfilerBundle | container | no collector service of the bundle | planned |

## 22. DeviceView facade (FAC), design.md section 10

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| FAC-01 | Readers | — | in a controller, `UA=iphone` | `getView()` Mobile, `getViewName()` `mobile`, `isMobile()` true, `isTablet()` false, `isDesktop()` false, `is('mobile')` true, `is(DeviceType::Mobile)` true, `is('tablet')` false, `getSource()` Detector | active |
| FAC-02 | Renamed view name | `views: { names: { mobile: m } }` | in a controller, `UA=iphone` | `getViewName()` `m`, `is('m')` true | active |
| FAC-03 | Outside a request | `default_view: tablet` | empty `RequestStack` (e.g. a console command) | `getView()` Tablet, `getSource()` Default, no exception, the detector is not called | active |
| FAC-04 | `setView()` outside a request | — | empty `RequestStack` | no exception; debug log `Ignored DeviceView::setView(): there is no request.` | active |
| FAC-05 | Unknown view name | — | `setView('phone')` and `is('phone')` in a controller | `InvalidArgumentException`, message `Unknown device view "phone". Expected one of: mobile, tablet, desktop.` | active |
| FAC-06 | Changes after the response is built | a `kernel.response` listener at priority -10 calling `setView('mobile', true)`, `setRemember(true)` and `setConsent(true)` | `GET /view`, `UA=desktop_chrome` | 200 `"desktop detector"`, no `Set-Cookie`; debug log `Ignored a device view change made after the response was built.` | active |
| FAC-07 | `getSwitchUrl()` | — | `GET /view?a=1`; `getSwitchUrl('mobile')`, `getSwitchUrl(DeviceType::Tablet, 'home')` | `/view?a=1&device_view=mobile`, `/?device_view=tablet` | active |
| FAC-08 | `getSwitchUrl()` keeps the base URL | — | front controller `/app`: `GET http://localhost/app/view`; same calls | `/app/view?device_view=mobile`, `/app/?device_view=tablet` | active |
| FAC-09 | `getSwitchUrl()` uses the parameter and names | `switch: { parameter: view }`, `views: { names: { mobile: m } }` | `GET /view`; `getSwitchUrl('m')` | `/view?view=m` | active |
| FAC-10 | `getSwitchUrl()` outside a request | — | empty `RequestStack`; `getSwitchUrl('mobile')` | `/?device_view=mobile` | active |
| FAC-11 | Unknown switch target | — | `getSwitchUrl('mobile', 'back')` called twice: in a controller during `GET /view`, and with an empty `RequestStack` | both calls throw `InvalidArgumentException`, message `Unknown switch target "back". Expected "current" or "home".` | active |
| FAC-12 | `setView()` after a read | — | in a controller, `UA=iphone`: read, then `setView('tablet')`, then read | `mobile`, then `tablet` with source Forced; `Vary: [Cookie, User-Agent]` (inputs already consulted stay) | planned |
| FAC-13 | Sub-requests see the main view | — | `GET /with-sub-request`, `UA=iphone` | the sub-request controller's `getView()` is Mobile | active |
| FAC-14 | Autowiring | — | container | `DeviceView::class` is an alias of `device_view.device_view` | active |

## 23. Services (SVC), design.md sections 2 and 10

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| SVC-01 | Interface aliases | `TAC` | container | `DeviceDetectorInterface` → `device_view.detector`, `ConsentCheckerInterface` → `device_view.consent_checker`, `ViewStorageInterface` → `device_view.storage`, `RedirectPolicyInterface` → `device_view.redirect_policy`, `ViewResolverInterface` → `device_view.resolver`, `RememberPolicyInterface` → `device_view.remember_policy` | planned |
| SVC-02 | `services.resolver` | `services: { resolver: app.tablet_resolver }` (always tablet, source default) | `GET /view`, `UA=iphone` | 200 `"tablet default"` | active |
| SVC-03 | `services.redirect_policy` | `M`, `services: { redirect_policy: app.never_redirect }` | `GET http://www.example.com/`, `UA=iphone` | 200, no `Location` | planned |
| SVC-04 | `services.remember_policy` | `services: { remember_policy: app.always_remember }` | `GET /view`, `UA=iphone` | `Set-Cookie: SC(mobile)` | planned |
| SVC-05 | Decorating the detector | a service decorating `device_view.detector` that answers tablet | `GET /view`, `UA=iphone` | 200 `"tablet detector"` | planned |

## 24. Configuration validation (CFG), design.md section 4

Each row boots the kernel with the config and expects the exception when the container compiles.
The exception is `InvalidConfigurationException` unless said.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| CFG-01 | Unknown key | `unknown_key: true` | container build | message contains `Unrecognized option "unknown_key" under "device_view"` | active |
| CFG-02 | `default_view` enum | `default_view: phone` | container build | message contains `The value "phone" is not allowed for path "device_view.default_view"` | active |
| CFG-03 | `tablet_as_mobile` type | `tablet_as_mobile: 'yes'` | container build | message contains `Invalid type for path "device_view.tablet_as_mobile"` | active |
| CFG-04 | View names unique | `views: { names: { mobile: m, tablet: m } }` | container build | message contains `The view names must be unique: "m" is used twice.` | active |
| CFG-05 | View name pattern | `views: { names: { mobile: 'Mobile!' } }` | container build | message contains `The view name "Mobile!" is invalid: use lowercase letters, digits, "_" and "-" only.` | active |
| CFG-06 | Unknown view key | `views: { names: { phone: p } }` | container build | message contains `Unrecognized option "phone" under "device_view.views.names"` | active |
| CFG-07 | `detector.id` service missing | `detector: { id: app.nope }` | container build | message contains `The service "app.nope" set in device_view.detector.id does not exist.` | active |
| CFG-08 | `detector.id` wrong class | `detector: { id: app.not_a_detector }` | container build | message contains `The service "app.not_a_detector" set in device_view.detector.id must implement DeviceViewBundle\Detector\DeviceDetectorInterface.` | active |
| CFG-09 | Empty chain | `detector: { id: chain, chain: [] }` | container build | message contains `device_view.detector.chain must list at least one detector.` | planned |
| CFG-10 | Chain cannot nest | `detector: { id: chain, chain: [auto, chain] }` | container build | message contains `device_view.detector.chain cannot contain "auto" or "chain".` | planned |
| CFG-11 | Chain service missing | `detector: { id: chain, chain: [app.nope] }` | container build | message contains `The service "app.nope" set in device_view.detector.chain does not exist.` | planned |
| CFG-12 | Mobile Detect headers not empty | `detector: { mobile_detect: { headers: [] } }` | container build | message contains `device_view.detector.mobile_detect.headers must list at least one header.` | active |
| CFG-13 | Mobile Detect cache interface | `detector: { mobile_detect: { cache: app.not_a_cache } }` | container build | message contains `The service "app.not_a_cache" set in device_view.detector.mobile_detect.cache must implement Psr\SimpleCache\CacheInterface.` | active (6.x+) |
| CFG-14 | Matomo cache pool interface | `detector: { id: matomo, matomo: { cache_pool: app.not_a_cache } }` | container build | message contains `The service "app.not_a_cache" set in device_view.detector.matomo.cache_pool must implement Psr\Cache\CacheItemPoolInterface.` | planned |
| CFG-15 | `phablet_as` enum | `detector: { matomo: { phablet_as: desktop } }` | container build | message contains `The value "desktop" is not allowed for path "device_view.detector.matomo.phablet_as"` | planned |
| CFG-16 | `static.view` enum | `detector: { static: { view: phone } }` | container build | message contains `The value "phone" is not allowed for path "device_view.detector.static.view"` | planned |
| CFG-17 | `switch.target` enum | `switch: { target: back }` | container build | message contains `The value "back" is not allowed for path "device_view.switch.target"` | active |
| CFG-18 | `switch.status` set | `switch: { status: 301 }` | container build | message contains `The value 301 is not allowed for path "device_view.switch.status"` | active |
| CFG-19 | `switch.parameter` not empty | `switch: { parameter: '' }` | container build | message contains `The path "device_view.switch.parameter" cannot contain an empty value` | active |
| CFG-20 | `remember.storage` service | `remember: { storage: app.not_a_storage }` | container build | message contains `The service "app.not_a_storage" set in device_view.remember.storage must implement DeviceViewBundle\Remember\ViewStorageInterface.` | active |
| CFG-21 | Negative lifetime | `remember: { lifetime: -1 }` | container build | message contains `The value -1 is too small for path "device_view.remember.lifetime"` | active |
| CFG-22 | `consented_lifetime` below `lifetime` | `remember: { lifetime: 3600, consented_lifetime: 60 }` | container build | message contains `device_view.remember.consented_lifetime (60) must be greater than or equal to device_view.remember.lifetime (3600).` | active |
| CFG-23 | `consented_lifetime` is free when `lifetime` is 0 | `remember: { lifetime: 0, consented_lifetime: 60 }` | container build | builds | active |
| CFG-24 | `cookie.secure` enum | `remember: { cookie: { secure: maybe } }` | container build | message contains `device_view.remember.cookie.secure` and `auto` | active |
| CFG-25 | `cookie.same_site` enum | `remember: { cookie: { same_site: relaxed } }` | container build | message contains `The value "relaxed" is not allowed for path "device_view.remember.cookie.same_site"` | active |
| CFG-26 | `same_site: none` needs secure | `remember: { cookie: { same_site: none, secure: false } }` | container build | message contains `device_view.remember.cookie.same_site "none" needs device_view.remember.cookie.secure set to true or auto.` | active |
| CFG-27 | Cookie name | `remember: { cookie: { name: 'dv;x' } }` | container build | message contains `The cookie name "dv;x" is invalid.` | active |
| CFG-28 | Session key not empty | `remember: { session: { key: '' } }` | container build | message contains `The path "device_view.remember.session.key" cannot contain an empty value` | active |
| CFG-29 | `storage: none` with `detected_view` | `remember: { storage: none, detected_view: true }` | container build | message contains `device_view.remember.detected_view cannot be true when device_view.remember.storage is "none".` | active |
| CFG-30 | `storage: none` with a consent provider | `remember: { storage: none }`, `TAC` | container build | message contains `device_view.consent.provider cannot be set when device_view.remember.storage is "none".` | active |
| CFG-31 | `consent.provider` service | `consent: { provider: app.not_a_checker }` | container build | message contains `The service "app.not_a_checker" set in device_view.consent.provider must implement DeviceViewBundle\Consent\ConsentCheckerInterface.` | active |
| CFG-32 | Consent key not empty | `consent: { provider: tarteaucitron, key: '' }` | container build | message contains `device_view.consent.key must be null or a non-empty string, got "".` | active |
| CFG-33 | Host required | `redirect: { mobile: { enabled: true } }` | container build | message contains `device_view.redirect.mobile.host is required when the mobile redirect is enabled.` | active |
| CFG-34 | Host must be absolute | `redirect: { mobile: { enabled: true, host: m.example.com } }` | container build | message contains `device_view.redirect.mobile.host "m.example.com" must be an absolute http(s) URL with a host, an optional port and an optional path, and no query, fragment or user info.` | active |
| CFG-35 | Host without query | `redirect: { mobile: { enabled: true, host: 'https://m.example.com/?a=1' } }` | container build | the CFG-34 message for `"https://m.example.com/?a=1"` | active |
| CFG-36 | Host without fragment | `redirect: { mobile: { enabled: true, host: 'https://m.example.com/#top' } }` | container build | the CFG-34 message for that value | active |
| CFG-37 | Host without user info | `redirect: { mobile: { enabled: true, host: 'https://user:pass@m.example.com' } }` | container build | the CFG-34 message for that value | active |
| CFG-38 | Host scheme | `redirect: { mobile: { enabled: true, host: 'ftp://m.example.com' } }` | container build | the CFG-34 message for that value | active |
| CFG-39 | Host from an env var is checked at runtime only | `redirect: { mobile: { enabled: true, host: '%env(DV_MOBILE_HOST)%' } }` | container build | builds (see RED-26 and RED-27) | active |
| CFG-40 | Redirect action enum | `redirect: { mobile: { action: back } }` | container build | message contains `The value "back" is not allowed for path "device_view.redirect.mobile.action"` | active |
| CFG-41 | Device redirect status set | `redirect: { mobile: { status: 304 } }` | container build | message contains `The value 304 is not allowed for path "device_view.redirect.mobile.status"` | active |
| CFG-42 | Redirect methods not empty | `redirect: { methods: [] }` | container build | message contains `device_view.redirect.methods must list at least one method.` | active |
| CFG-43 | Skip path regex | `skip: { paths: ['^/(api'] }` | container build | message contains `device_view.skip.paths contains an invalid regular expression: "^/(api".` | planned |
| CFG-44 | Trusted header name | `trusted_header: { name: 'X Device' }` | container build | message contains `device_view.trusted_header.name "X Device" is not a valid header name.` | planned |
| CFG-45 | Twig prefix | `twig: { prefix: 'dv-' }` | container build | message contains `device_view.twig.prefix "dv-" must be empty or match ^[a-z_][a-z0-9_]*$.` | planned |
| CFG-46 | Auto without any detector package | `detector: { id: auto }`, neither library installed | container build | `LogicException`, the DET-AUTO-03 message | active |
| CFG-47 | Mobile Detect not installed | `detector: { id: mobile_detect }`, mobiledetect not installed | container build | `LogicException`, message `The "mobile_detect" detector needs mobiledetect/mobiledetectlib. Run "composer require mobiledetect/mobiledetectlib", or set device_view.detector.id to another detector.` | active |
| CFG-48 | Matomo not installed | `detector: { id: matomo }`, matomo not installed | container build | `LogicException`, message `The "matomo" detector needs matomo/device-detector. Run "composer require matomo/device-detector", or set device_view.detector.id to another detector.` | planned |
| CFG-49 | `services.resolver` service | `services: { resolver: app.not_a_resolver }` | container build | message contains `The service "app.not_a_resolver" set in device_view.services.resolver must implement DeviceViewBundle\Resolver\ViewResolverInterface.` | active |
| CFG-50 | `services.redirect_policy` service | `services: { redirect_policy: app.nope }` | container build | message contains `The service "app.nope" set in device_view.services.redirect_policy does not exist.` | planned |
| CFG-51 | `services.remember_policy` service | `services: { remember_policy: app.not_a_policy }` | container build | message contains `The service "app.not_a_policy" set in device_view.services.remember_policy must implement DeviceViewBundle\Remember\RememberPolicyInterface.` | planned |
| CFG-52 | The defaults are valid | `{}` (no `detector` key) with both libraries installed | container build | builds | active |
| CFG-53 | Consent cookie not empty | `consent: { provider: tarteaucitron, cookie: '' }` | container build | message contains `device_view.consent.cookie must be null or a non-empty string, got "".` | active |
| CFG-54 | Mobile Detect in the chain not installed | `detector: { id: chain }` (chain `[client_hints, mobile_detect]`), mobiledetect not installed | container build | `LogicException`, message `The "mobile_detect" detector in device_view.detector.chain needs mobiledetect/mobiledetectlib. Run "composer require mobiledetect/mobiledetectlib", or remove it from device_view.detector.chain.` | planned |
| CFG-55 | Matomo in the chain not installed | `detector: { id: chain, chain: [client_hints, matomo] }`, matomo not installed | container build | `LogicException`, message `The "matomo" detector in device_view.detector.chain needs matomo/device-detector. Run "composer require matomo/device-detector", or remove it from device_view.detector.chain.` | planned |
| CFG-56 | `remember.storage` service missing | `remember: { storage: app.nope }` | container build | message contains `The service "app.nope" set in device_view.remember.storage does not exist.` | active |
| CFG-57 | Session storage needs the framework session | `remember: { storage: session }`, `framework.session` not enabled | container build | message contains `device_view.remember.storage "session" needs the framework session: enable framework.session, or set device_view.remember.storage to another storage.` | active |
| CFG-58 | A service id cannot be the alias it sets | `remember: { storage: device_view.storage }` | container build | message contains `device_view.remember.storage cannot be "device_view.storage", the alias it sets: set it to the id of your own service.` | active |

## 25. Configuration reference (REF), design.md section 12

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| REF-01 | Snapshot | — | `bin/console config:dump-reference device_view` | output equals `tests/Fixtures/config-reference.yaml` | planned |
| REF-02 | Every key is documented | — | same command | the output has every key of design.md section 4 with its default | planned |

## 26. Worker (WRK), design.md section 12

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| WRK-01 | Same answers as fresh kernels | `M`, `TAC` | one booted kernel, 200 requests from a seeded list mixing fixture user agents, cookies, `www`/`m` hosts, consent cookies and switch parameters | each response (status, `Location`, `Set-Cookie`, `Vary`, `Cache-Control`, body) equals the response of a fresh kernel to the same request | planned |
| WRK-02 | Nothing leaks between requests | `switch: { target: none }` | one kernel: `GET /set-consent/1?device_view=tablet`, then `GET /view` `UA=iphone` with no cookie | second response: 200 `"mobile detector"`, no `Set-Cookie` | active |
| WRK-03 | Memory stays flat | same as WRK-01 | the 200 requests, with `gc_collect_cycles()` before each measure | `memory_get_usage()` after request 200 minus after request 50 is below 1 MiB | planned |
| WRK-04 | `$_SERVER` changes nothing | same as WRK-01 | the 200 requests with `$_SERVER['HTTP_USER_AGENT']` unset, then set to the `iphone` user agent | the responses are identical to WRK-01's | planned |
| WRK-05 | Services hold no request state | same as WRK-01 | after the 200 requests | no property of a `device_view.*` service holds a `Request`, a `DeviceViewContext` or a `DeviceType` | planned |

## 27. Real HTTP (HTTP), design.md section 12

These scenarios run the test app with `php -S` and send raw headers, so PHP decodes cookies as
it does in production. Unless a row says otherwise, the request is
`GET /view?device_view=mobile` with `UA=desktop_chrome` plus the raw `Cookie` header of the row,
and the response is 302 with `Location: /view`.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| HTTP-01 | Raw switch cookie | — | no `Cookie` header | 302, header line `Set-Cookie: device_view=mobile; path=/; httponly; samesite=lax` | planned |
| HTTP-02 | tarteaucitron raw cookie | `TAC` | raw `Cookie: tarteaucitron=!device_view=true` | 302, `Set-Cookie` with `Max-Age=31536000` | planned |
| HTTP-03 | Didomi base64 with `+` and `=` | `didomi` | raw `Cookie: didomi_token=` the CON-DID-05 token | 302, `Set-Cookie` with `Max-Age=31536000` | planned |
| HTTP-04 | CookieConsent URL-encoded JSON | `cookieconsent` | raw `Cookie: cc_cookie=%7B%22categories%22%3A%5B%22necessary%22%2C%22preferences%22%5D%2C%22revision%22%3A0%7D` | 302, `Set-Cookie` with `Max-Age=31536000` | planned |
| HTTP-05 | Cookiebot URL-encoded object | `cookiebot` | raw `Cookie: CookieConsent=%7Bstamp%3A%27x%27%2Cnecessary%3Atrue%2Cpreferences%3Atrue%7D` | 302, `Set-Cookie` with `Max-Age=31536000` | planned |
| HTTP-06 | OneTrust URL-encoded query | `onetrust` | raw `Cookie: OptanonConsent=isGpcEnabled=0&groups=C0001%3A1%2CC0003%3A1` | 302, `Set-Cookie` with `Max-Age=31536000` | planned |
| HTTP-07 | Bracketed cookie name | `TAC` | raw `Cookie: tarteaucitron[a]=1` | 302, `Location: /view`, `Set-Cookie` without `Max-Age`, no error | planned |

## 28. MobileDetectBundle regressions (REG), design.md section 12 and the approved plan

One named test per bug of the old bundle. Each also passes on every line.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| REG-01 | Custom cookie name honoured on read | `remember: { cookie: { name: dv } }` | `GET /view`, `Cookie: dv=mobile`, `UA=desktop_chrome` | 200 `"mobile remembered"` | active |
| REG-02 | Custom switch parameter honoured | `switch: { parameter: view }` | `GET /view?view=mobile` | 302, `Location: /view`, `Set-Cookie: SC(mobile)` | active |
| REG-03 | `not_mobile` cannot bypass a redirect | `M` | `GET http://www.example.com/?device_view=not_mobile`, `UA=iphone` | 302, `Location: https://m.example.com/?device_view=mobile` | active |
| REG-04 | Array parameter is harmless | `M` | `GET http://www.example.com/?device_view[]=x`, `UA=iphone` | 302, `Location: https://m.example.com/?device_view=mobile`, no error | active |
| REG-05 | Garbage parameter is harmless | — | `GET /view?device_view=%00%FF` | 200 `"desktop detector"`, no `Set-Cookie` | planned |
| REG-06 | Host case causes no loop | `M` | `GET https://M.EXAMPLE.COM/`, `UA=iphone` | 200 | active |
| REG-07 | Host port causes no loop | `redirect: { mobile: { enabled: true, host: 'https://m.example.com:443' } }` | `GET https://m.example.com/`, `UA=iphone` | 200 | active |
| REG-08 | Trailing dot causes no loop | `M` | `GET https://m.example.com./`, `UA=iphone` | 200 | active |
| REG-09 | Trailing slash in the configured host causes no loop | `redirect: { mobile: { enabled: true, host: 'https://m.example.com/' } }` | `GET https://m.example.com/` `UA=iphone`; then `GET http://www.example.com/` `UA=iphone` | 200; then 302, `Location: https://m.example.com/?device_view=mobile` | active |
| REG-10 | Following redirects ends | `M`, `D` | from `GET http://www.example.com/blog/post` `UA=iphone`, follow each `Location` with the cookies received, at most 5 times | ends with 200 `"mobile remembered"` on `https://m.example.com/blog/post` after 2 redirects | active |
| REG-11 | Spoofed Host never reaches `Location` | `M` | `GET /blog/post` with `Host: evil.example`, `UA=iphone` | 302, `Location: https://m.example.com/blog/post?device_view=mobile` | active |
| REG-12 | POST is never redirected | `M` | `POST http://www.example.com/form?device_view=desktop`, `UA=iphone` | 200 `"mobile detector"`, no `Location`, no `Set-Cookie` | active |
| REG-13 | `/_fragment` gets no cookie and no redirect | `M`, `remember: { detected_view: true }`, `framework.fragments: true` | `GET http://www.example.com/_fragment?…` (a signed fragment URL rendering the `view` controller), `UA=iphone`, `Cookie: device_view=phone` | 200 `"mobile detector"`, no `Location`, no `Set-Cookie` | planned |
| REG-14 | Redirects are not cacheable | `redirect: { mobile: { enabled: true, host: 'https://m.example.com', status: 301 } }` | `GET http://www.example.com/`, `UA=iphone` | 301, `Cache-Control: NS`, `Vary: [Cookie, User-Agent]` | planned |
| REG-15 | Route override without loading the route collection | `M`, the router's `getRouteCollection()` throws | `GET http://www.example.com/no-redirect`, `UA=iphone` | 200 `"mobile detector"` | planned |
| REG-16 | Invalid config fails at build, not at request time | `redirect: { mobile: { enabled: true, host: m.example.com } }` | container build | `InvalidConfigurationException` (CFG-34) before any request | active |
| REG-17 | No cookie on a first visit | — | `GET /view`, `UA=iphone`, no cookie | 200 `"mobile detector"`, no `Set-Cookie` | active |
| REG-18 | The desktop redirect works | `D` | `GET https://m.example.com/`, `UA=desktop_chrome` | 302, `Location: https://www.example.com/?device_view=desktop` | active |
| REG-19 | A view never leaks to the next request | `M`, `D` | one kernel: `GET https://m.example.com/view?device_view=desktop` `UA=iphone`, then `GET https://m.example.com/view` `UA=iphone` with no cookie | first response: 302, `Location: https://www.example.com/view?device_view=desktop`; second response: 200 `"mobile detector"`, no `Location` (a leaked desktop view would redirect to `www`) | active |

## 29. Test helpers (TST), design.md section 10

The helpers ship in `src/Test` for applications' own tests. The rows use the test app through
the framework's test client (`KernelBrowser`, `Client` on 3.x; `framework.test: true`); "then
`GET /view`" means `$client->request('GET', '/view')`.

| ID | Scenario | Config | Request | Expected | Status |
|---|---|---|---|---|---|
| TST-01 | `requestAs()` mobile | — | `requestAs($client, 'mobile')`, then `GET /view` | the request has `User-Agent` = `UserAgents::ANDROID_PHONE_REDUCED` and `Sec-CH-UA-Mobile: ?1`; 200 `"mobile detector"` | planned |
| TST-02 | `requestAs()` tablet | — | `requestAs($client, 'tablet')`, then `GET /view` | the request has `User-Agent` = `UserAgents::IPAD_LEGACY` and no `Sec-CH-UA-Mobile`; 200 `"tablet detector"` | planned |
| TST-03 | `requestAs()` desktop | — | `requestAs($client, 'desktop')`, then `GET /view` | the request has `User-Agent` = `UserAgents::DESKTOP_FIREFOX` and no `Sec-CH-UA-Mobile`; 200 `"desktop detector"` | planned |
| TST-04 | Every built-in detector gets the view | `detector: { id: matomo }`, then `detector: { id: chain }` | for each detector, `requestAs()` mobile, tablet and desktop, each followed by `GET /view` | `"mobile detector"`, `"tablet detector"`, `"desktop detector"` with each detector | planned |
| TST-05 | A later call replaces the earlier one | — | `requestAs($client, 'mobile')`, then `requestAs($client, 'desktop')`, then `GET /view` | the request has the TST-03 `User-Agent` and no `Sec-CH-UA-Mobile`; 200 `"desktop detector"` | planned |
| TST-06 | Unknown view | — | `requestAs($client, 'phone')` | `InvalidArgumentException`, message `Unknown device view "phone". Expected one of: mobile, tablet, desktop.` | planned |
| TST-07 | `UserAgents` matches the fixtures | — | read `UserAgents` and `tests/Fixtures/user-agents.php` | every constant equals the `user_agent` of the fixture entry whose name is the constant name in lower case (`UserAgents::IPHONE` = entry `iphone`); every entry with a real user agent (all but `empty`, `missing`, `long_2kb` and `invalid_utf8`) has a constant; the docblock of each constant gives the entry's source and date | planned |

## Choices this catalogue makes where design.md is silent

These rows settle details design.md leaves open. Each is a proposal for the PR that implements
the area; if the owner disagrees, the rows change before they become active.

- The detected view is remembered only when the view was resolved during the request (REM-14);
  `default_view` is not a detected view, a trusted header view is (REM-15, REM-16).
- A forged stored value is expired only when the request read it (VAL-11).
- The resolver reads the switch parameter only when the switch would apply: switch enabled,
  method allowed, main request, not skipped (RES-11, RES-12, SKIP-06).
- With `tablet_as_mobile`, the remembered value is the mapped view (VW-05).
- A `ViewResolvedEvent` listener that changes the view keeps the recorded source (EVT-RES-01).
- Removing a forged cookie counts as writing the remember cookie for `private_when_remembering`
  (HC-04).
- Session storage adds `Cookie` to `Vary`, because the session id is a cookie (SES-02, SES-03).
- Any session value under the session key that is not a configured view name is removed, whatever
  its type: unlike a bracketed cookie, the session can always remove its key (VAL-12, VAL-14).
- `remember.storage: session` fails the container build when `framework.session` is not
  enabled, instead of failing on the first switch at runtime (CFG-57).
- A service id equal to the alias the option sets (`device_view.detector` in `detector.id`,
  `device_view.storage` in `remember.storage`, `device_view.resolver` in `services.resolver`,
  `device_view.consent_checker` in `consent.provider`)
  fails the container build with the CFG-58 message for that option.
- `setView($view, true)` is remembered with the `switch` trigger (REM-18, REM-19);
  `setConsent(true)` without a provider gives `consented_lifetime` (REM-23). Without a provider,
  a detected view follows the no-provider row of the decision table whatever `setConsent(false)`
  says, and `setConsent(true)` gives it `consented_lifetime` too.
- `consent.key` and `consent.cookie` set up a built-in reader; a `consent.provider` that is a
  service id gets neither. They accept `null` (the reader's default) or a non-empty string
  (CFG-32, CFG-53).
- Developer API calls with an unknown view name or switch target throw `InvalidArgumentException`
  (FAC-05, FAC-11, TWIG-09); request inputs never throw. An argument error throws whether or not
  there is a request (FAC-11); a missing request alone never throws (FAC-03, FAC-04, FAC-10).
- An array value of the remember cookie (`device_view[a]=mobile`) is ignored and not expired: a
  `device_view` deletion would not remove the browser's bracketed cookie, and would make every
  later response private under `private_when_remembering`. Like any invalid input, it is logged
  at debug level with `Ignored an invalid device view from the remember storage.` (VAL-09, HC-04).
- Route default values: booleans or the strings `true`, `false`, `1`, `0`; other values are
  ignored with a debug log (RTE-04, RTE-08).
- `Vary` keeps the response's values first, then adds the consulted inputs in the order they were
  consulted, skipping names already present (compared without case) (VARY-06, VARY-07).
- Cancelling a switch redirect keeps the switched view and its remember (EVT-RED-05).
- Matomo with `client_hints: true` passes and varies on `Sec-CH-UA`, `Sec-CH-UA-Arch`,
  `Sec-CH-UA-Bitness`, `Sec-CH-UA-Full-Version`, `Sec-CH-UA-Full-Version-List`,
  `Sec-CH-UA-Mobile`, `Sec-CH-UA-Model`, `Sec-CH-UA-Platform` and `Sec-CH-UA-Platform-Version`
  (DET-MAT-05); not `Sec-CH-UA-Form-Factors` (design.md section 14).
- A host with a base path redirects only when its host or port differs from the current one
  (RED-09).
- `detector.mobile_detect.headers`, `detector.chain` and `redirect.methods` must not be empty
  (CFG-12, CFG-09, CFG-42); the chain cannot contain `auto` or `chain` (CFG-10).
- `Accept-CH: Sec-CH-UA-Mobile` is sent whenever `detector.client_hints.accept_ch` is true and
  the view was used, whatever detector is configured (ACH-01 uses the baseline `mobile_detect`).
- Matomo's `X-Requested-With` header, which `ClientHints::factory()` also reads, is neither passed
  to Matomo nor added to `Vary`: it names the app that sent the request, not the device type
  (DET-MAT-05).
- `requestAs()` takes a canonical view name and sets the client's server parameters for the next
  requests; it uses `android_phone_reduced` with `Sec-CH-UA-Mobile: ?1` for mobile,
  `ipad_legacy` for tablet and `desktop_firefox` for desktop, so that every built-in detector
  gives the asked view and only mobile sends the hint (TST-01 to TST-05).
- A switch to the view already stored writes the cookie again, which renews its lifetime
  (SW-21).
- The consent checker is asked only when a remember write is planned, so a page that writes
  nothing never reads the consent cookie (REM-26).
- A configured host with its scheme's default port (`https://m.example.com:443`) matches the
  host without a port, and the port is left out of the `Location` (RED-14, REG-07).
- `BeforeRedirectEvent::setStatus()` with a status outside 301, 302, 303, 307 and 308, and
  `BeforeRememberEvent::setLifetime()` with a negative value, throw `InvalidArgumentException`
  (EVT-RED-07, EVT-REM-09).
- `same_site: none` always writes the `secure` attribute, whatever `secure` says, even over plain
  HTTP where `secure: auto` would leave it out: browsers drop a `samesite=none` cookie without
  `secure` (CK-16). `same_site: none` with `secure: false` is a configuration error (CFG-26).
- A library detector named in `detector.chain` whose package is missing fails the container
  build with its own message, which names the chain (CFG-54, CFG-55).
- The test app adds stub routes on the skipped paths (`profiler_stub`, `wdt_stub`,
  `fragment_stub`), because a path without a route gets a 404 from the router before the switch
  and redirect listeners run (SKIP-04, SKIP-05, SKIP-07).
- A table is a scenario table when the first cell of its header row starts with `ID` in any case
  (`ID`, `Id`, `IDs`), so a table whose header was mistyped still fails on a mistyped row instead
  of hiding its scenarios; a data row starting with `ID` does not count.
- A redirect the bundle returns gets `redirect.cache_control` when it is built. When the same
  response also writes the remember cookie, the `private_when_remembering` step then calls
  `Response::setPrivate()`, which adds `private` to the directives already set and removes
  `public`: the default gives `no-store, private` (SW-01, SW-17), a custom value keeps its
  directives plus `private` (SW-16), and with `cache_control: null` no directive is stored, so
  Symfony's computed `no-cache, private` becomes `private` (SW-15). A redirect that writes no
  cookie keeps `redirect.cache_control`, or Symfony's `no-cache, private` with `null` (RED-23).
- Trusted proxies stay out of the Config cell, which holds `device_view` keys only; 3.x and 4.x
  set them with `Request::setTrustedProxies()` in the test front controller (Conventions,
  Trusted proxies; `TH` rows, RED-35).
- A switch to a view served on another host goes where a device redirect of that view would go,
  with the switch status: `action: redirect` keeps the path and, with `keep_query`, the other
  query parameters (SW-17); `action: redirect_without_path` and `switch.target: home` go to the
  home page of that host; `action: none` keeps the switch on the current host. The view is the
  one the request resolves to, so with `tablet_as_mobile` a switch to tablet goes to the mobile
  host.
- Hosts are compared without their scheme: `http://m.example.com` and `https://m.example.com`
  are the same host, each with its own default port, so a site behind a TLS proxy that is not
  trusted is not redirected in a loop.
- The cross-host warning (RED-28) is logged on every redirect to another host, device redirect
  or switch, while `remember.storage` is `cookie` and `remember.cookie.domain` is `null`.
- A host that holds an env var is checked on each request that would use it, so an invalid one
  logs the RED-27 error on each of them (services keep no state).
- With `cache_control: null`, a redirect with status 301 has no `Cache-Control` at all: Symfony's
  `RedirectResponse` removes it on a 301 that sets none.
- The exception and log messages of the rows above.
