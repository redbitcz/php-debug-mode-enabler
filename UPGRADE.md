# Upgrading to the next major version

## Platform and dependency requirements

The minimum PHP version is now 8.3 (`^8.3`); PHP 8.0–8.2 are no longer supported.
CI targets PHP 8.3–8.6. Nette Utils allows `^3.0 || ^4.0 || dev-master`; the development
branch is intentionally available for PHP 8.6 compatibility pending a stable release.
Development dependencies now require Nette Tester `^2.6` and PHPStan `^2.3`.
The Tester development-branch alternative has been removed.

## Boolean detection methods

`Detector::detect()`, `Detector::detectProduction()`, `Detector::isDebugMode()`, and
`Detector::isProductionMode()` now always return `bool` and no longer accept `$default`.
Static methods retain their `$mode` and `$tempDir` arguments.

When no rule decides, debug methods return `false` and production methods return `true`.
In particular, instance `isProductionMode()` previously defaulted to `false`; it now
defaults to `true`, matching the static shortcut.

Removed positional fallback arguments throw `ArgumentCountError` rather than being
silently ignored. Named `default:` arguments are rejected by PHP as unknown parameters.
Update subclasses overriding these methods to match the new signatures and behavior.

## Advanced detection

Use the new instance method `resolve(): ?bool` to preserve an undecided result or apply
your own fallback. Rule order and plugin semantics are unchanged: the first boolean
result wins, while `null` continues to the next rule.

```php
// Before:
$result = $detector->isDebugMode(null);
$debug = $detector->isDebugMode(true);
$production = $detector->isProductionMode(false);

// After:
$result = $detector->resolve();
$debug = $detector->resolve() ?? true;
$resolved = $detector->resolve();
$production = $resolved === null ? false : !$resolved;
```

For former static calls with a custom fallback, construct a Detector and use `resolve()`.
When using `MODE_ENABLER` or `MODE_FULL`, pass an `Enabler` instance to the constructor,
as before. There is no static `resolve()` shortcut.

## Firebase JWT v7

The built-in SignedUrl integration now supports only `firebase/php-jwt:^7.0`.
Install or update this optional dependency in applications using SignedUrl:

```sh
composer require firebase/php-jwt:^7.0
```

`JWTFirebaseV5` and `JWTFirebaseV6` have been removed. Use `JWTFirebaseV7` when
constructing an adapter explicitly. `JWTImpl`, custom adapter injection, and the
factory's adapter-selection mechanism remain available for future implementations.
Automatic version detection uses Composer 2 installed-package metadata.

JWT remains optional for Detector-only applications. No package-wide conflict with
older JWT releases is imposed; `SignedUrl::create()` rejects unsupported versions.

JWT v7 rejects undersized signing keys. For HS256, generate a persistent secret once
with `base64_encode(random_bytes(32))` and store it in application configuration.
Do not generate a different secret for every request. Changing a secret invalidates
links signed with the previous secret. The application's SignedUrl token format has
not otherwise changed in this migration.
