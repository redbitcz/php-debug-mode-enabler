# Architecture and validation notes

## Detector

The default `MODE_SIMPLE` includes cookie, environment, and IP detection. `MODE_FULL` additionally enables Enabler detection and requires an Enabler instance. Static `detect()` requires a temporary directory when Enabler mode is enabled.

Built-in precedence is **Enabler → disabling cookie → environment → IP**. The first non-null result wins, including `false`. Plugins are inserted before or after this chain with `prependPlugin()` and `appendPlugin()`; appended plugins may never run if an earlier rule decides. Detection is evaluated on each call.

- `true` enables debug mode; `false` disables it; `null` means continue.
- `resolve()` returns the first non-null rule result, or `null` when no rule decides.
- `isDebugMode()` and `detect()` always return `bool`, defaulting to `false`.
- `isProductionMode()` and `detectProduction()` always return `bool`, defaulting to `true`.
- These four methods no longer accept `$default`. Explicit argument-count guards reject obsolete positional fallback arguments; PHP rejects obsolete named arguments. See [migration notes](../UPGRADE.md).
- `app-debug-mode` is a disabling-only cookie. It must never become an unauthenticated way to enable debugging.
- `APP_DEBUG` and the disabling cookie use numeric checks followed by integer casts, not exact string comparisons. Preserve or explicitly test changes to accepted values.
- Default allowed addresses are `::1` and `127.0.0.1`. IP detection uses `REMOTE_ADDR`, falling back to the hostname from `php_uname('n')`.
- Presence of `HTTP_X_FORWARDED_FOR`, `HTTP_FORWARDED`, or `HTTP_X_REAL_IP` makes the IP rule return `false`. This guard belongs to the IP rule, not the entire detection chain.

## Enabler

The `app-debug-token` cookie contains a random 30-character token. The first 15 characters form its ID; the server stores the ID, SHA-1 hash of the whole token, boolean value, and expiry in JSON at `<tempDir>/debug/token.bin`. Despite the suffix, this is a JSON file, not a binary format.

`override()` affects the current instance only. `activate()` persists a token, sends a cookie, and updates the instance override immediately. With no explicit expiry, the token lasts one hour and the cookie is a browser-session cookie. `deactivate()` removes the incoming cookie's token, expires the cookie, and clears the override.

Default cookie options are path `/`, Secure, HttpOnly, and SameSite Strict. `setSecure(false)` also removes SameSite. Cookie/header operations must happen before response output.

Storage reads tolerate I/O failures by returning an empty list. Invalid JSON emits a warning and returns an empty list. Saving filters expired/invalid tokens and removes the storage file if empty. Read-modify-write operations do not implement transaction-level locking; account for concurrent requests when changing persistence.

## Signed URLs and JWT

SignedUrl retains the `JWTImpl` interface and adapter-selection loop for future major versions. The built-in `JWTFirebaseV7` adapter uses Composer 2 installed-version metadata to accept only stable v7 releases and decodes with a `Firebase\JWT\Key`. Missing JWT or an unsupported version causes the factory to throw a clear `LogicException`; Detector remains usable. The v5 and v6 adapters have been removed.

Signed URLs use the `_debug` query parameter. Tokens carry issuer `cz.redbit.debug.url`, audience, issue/expiry timestamps, URL subject, allowed HTTP methods, mode, and value. JWT decoding verifies the signature and time constraints; SignedUrl verifies required claims, issuer/audience, mode/value, URL, and HTTP method.

- Signing requires an absolute URL. Normalization supplies `/` for an empty path and excludes fragments from the signed subject while preserving them in generated links.
- Preserve raw query ordering and repeated parameters. Verification deliberately avoids `parse_str()` because it would change the signed URL.
- Extra query parameters after `_debug` are rejected by default. Explicit redirect mode can strip them; the response sends a redirect and terminates execution.
- `MODE_REQUEST` returns a result for the current request. `MODE_ENABLER` also activates the attached Enabler until token expiry; without an Enabler, it still returns the request result. `MODE_DEACTIVATE_ENABLER` deactivates an attached Enabler and returns `null`.
- Plugin invocation catches `SignedUrlVerificationException` and returns `null`, allowing later detection rules to decide.
- Use a strong secret and distinct audiences for different deployments, as described in README.
- Deterministic tests set both SignedUrl's signing timestamp and Firebase's verification timestamp. Signature fixtures target v7 JSON encoding.

## Current platform requirements

PHP `^8.3`, Nette Utils `^3.0 || ^4.0 || dev-master`, optional Firebase JWT `^7.0`.
Development tools: Nette Tester `^2.6` and PHPStan `^2.3`. CI targets PHP 8.3–8.6.
Utils permits the development branch for PHP 8.6 compatibility; Tester supports it
through the stable constraint.

## Historical initialization baseline: 2026-10-19

The following results predate the PHP 8.3 minimum and dependency upgrades. They are
historical observations, not instructions to run the current project on PHP 8.2.

Initialization changed documentation only. `.github/workflows/code_analysis.yaml` already had an uncommitted change and was left intact.

- Default CLI: PHP 8.5.10, Composer 2.10.3. PhpStorm language level: PHP 8.0; configured interpreter path points to PHP 8.2.
- `composer run test -- -C` fails in the installed Tester runner on PHP 8.5 with a null-array-offset deprecation in `vendor/nette/tester/src/Framework/Dumper.php`, before reporting test results.
- Running the same suite with the available PHP 8.2 interpreter succeeds: **29 tests passed** on PHP 8.2.33.
- `composer run phpstan` cannot open its parallel-worker socket in the sandbox (`EPERM`). `composer run phpstan -- --debug` succeeds with **no errors** on the default PHP 8.5 CLI.

```sh
/usr/local/opt/php@8.2/bin/php vendor/bin/tester tests -C -p /usr/local/opt/php@8.2/bin/php
```

This interpreter path is machine-specific. Choose an available compatible interpreter on other machines. Dependency metadata reports Tester `dev-master`, while its runner banner reports v2.5.4; check the actual installation when investigating compatibility. No dependencies were upgraded during initialization.

The tests cover Detector rules and plugin ordering, signed token/URL generation and verification, HTTP methods, and redirect handling. There is no dedicated Enabler persistence/cookie test suite; passing these tests does not establish complete storage or browser behavior coverage.
