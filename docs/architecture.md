# Architecture

## Detector

Built-in rule order is **Enabler → disabling cookie → environment → IP**. `ModeSimple`
excludes Enabler; `ModeFull` includes it and requires an Enabler instance. Static
shortcuts require a temporary directory when Enabler detection is enabled.

The first boolean result wins; `null` continues. Plugins can be prepended or appended
and may never run if an earlier rule decides. Each call evaluates detection again.
`resolve()` preserves an undecided result; the boolean methods default to production.

The `app-debug-mode` cookie can only disable debugging. `APP_DEBUG` and this cookie
use numeric checks followed by integer casts. IP detection allows `::1` and
`127.0.0.1` by default, using `REMOTE_ADDR` or the hostname if unavailable. Forwarding
headers (`X-Forwarded-For`, `Forwarded`, `X-Real-IP`) make the IP rule return `false`;
this does not override an earlier rule's decision.

## Enabler

The `app-debug-token` cookie holds a random token. Storage at `<tempDir>/debug/token.bin`
is JSON containing token IDs, hashes, boolean values and expiry times. Reads check
only that decoded data is an array; matching tokens are validated during lookup.
I/O failures return an empty list; malformed JSON also emits a warning. Writes filter
invalid/expired tokens and delete an empty storage file. Temporary storage is expected
to be cleaned externally.

`override()` affects only the instance. `activate()` persists a token, sends a cookie
and immediately sets the instance override. Without an explicit expiry, the token
lasts one hour and the cookie lasts for the browser session. `deactivate()` revokes
the incoming cookie's token, expires the cookie and clears the override. Tokens issued
earlier in the same request are not tracked for revocation. Storage updates have no
transaction-level locking, so concurrent updates can be lost.

Cookies default to path `/`, Secure, HttpOnly and SameSite Strict. `setSecure(false)`
removes SameSite. Cookie operations must precede response output.

## SignedUrl

`JWTImpl` isolates JWT implementations; the built-in adapter supports Firebase JWT v7.
JWT is optional for Detector-only use. SignedUrl verifies the signature, timestamps,
required claims, issuer, optional audience, mode/value, URL and HTTP method. Invalid
tokens silently return `null` during plugin invocation; configuration errors propagate.

Signing requires an absolute URL. The signed subject supplies `/` for an empty path
and excludes fragments. Raw query ordering and repeated parameters are preserved.
Extra parameters after `_debug` are rejected unless redirect mode is enabled; that
mode strips them, sends a redirect and terminates execution.

`ModeRequest` decides for the current request. `ModeEnabler` also activates an attached
Enabler until token expiry; without one, it only decides for the request.
`ModeDeactivateEnabler` deactivates an attached Enabler and returns `null`.
