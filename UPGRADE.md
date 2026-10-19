# Upgrading to the next major version

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
