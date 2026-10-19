# Project guide

## Communication and documentation

- Communicate with the user in Czech. Write code, comments, and project documentation in English.
- Keep durable code and project knowledge in this file or linked files in `docs/`. Personal memory is reserved for user preferences and feedback, never project facts.
- Preserve existing uncommitted changes. Keep initialization and documentation work separate from behavior changes or dependency upgrades.

## Purpose and layout

`redbitcz/debug-mode-enabler` is a standalone PHP library that decides whether an application should enable debug mode. It does not provide debugging tools. It runs early in application bootstrap, before a framework DI container is available.

- `src/Detector.php`: ordered detection rules, mode flags, plugins, and static convenience methods.
- `src/Enabler.php`: browser-specific overrides backed by a cookie and a temporary token file.
- `src/InconsistentEnablerModeException.php`: invalid Detector/Enabler configuration.
- `src/Plugin/Plugin.php`: invokable detection plugin contract.
- `src/Plugin/SignedUrl.php`: signed URL generation, verification, and optional Enabler activation.
- `src/Plugin/JWT/`: abstraction and adapters for Firebase JWT v5 and v6.
- `src/Plugin/SignedUrlVerificationException.php`: signed request validation failures.
- `tests/DetectorTest.php`, `tests/Plugin/SignUrlTest.php`: Nette Tester suites; `tests/bootstrap.php` loads Composer and configures Tester.
- `README.md`: public usage and integration examples.
- `.phpstorm.meta.php`: IDE suggestions for modes and algorithms; update when changing the corresponding public API.
- `phpstan.neon`, `.github/workflows/code_analysis.yaml`: static analysis and CI configuration.

See [architecture and validation notes](docs/architecture.md) for behavior details and the initialization baseline.

## Compatibility and conventions

- Composer requires PHP `~8.0` (PHP 8.x), `ext-json`, and `nette/utils` `^3.0 || ^4.0`. Keep source syntax compatible with PHP 8.0.
- Firebase JWT is optional at runtime and required for SignedUrl. Preserve v5 and v6 support; it is a development dependency for tests.
- PSR-4 maps `Redbitcz\DebugMode\` to `src/` and `Redbitcz\DebugModeTests\` to `tests/`.
- Follow existing style: strict types, four spaces, typed properties and signatures, PHPDoc for array shapes and compatibility-dependent types, braces on separate lines for classes and methods.
- Preserve existing public constant names, nullable detection semantics, and early-bootstrap independence. This is not a Nette application despite its Nette utility and test dependencies.
- `vendor/`, `composer.lock`, test output, `tests/lock`, and `tests/temp` are ignored. Do not commit installed dependencies or generated test artifacts.

## Development checks

```sh
composer install
composer run test -- -C
composer run phpstan
```

`-C` keeps the active PHP configuration for Tester. PHPStan analyzes `src/` at level 5; JWT adapters are excluded because their dependency APIs differ. Review existing exclusions and suppressions before changing analysis configuration.

If a sandbox prevents PHPStan from opening its parallel-worker socket, run `composer run phpstan -- --debug` to analyze without parallel workers.

Tests use `Tester\TestCase`, `@testCase`, data providers, and a final `(new ...())->run()` call. Preserve method isolation when tests manipulate environment variables, superglobals, or `JWT::$timestamp`. Existing Detector tests purge `tests/temp/enabler`.

CI runs tests and PHPStan separately against Firebase JWT v5/v6. The working-tree workflow inspected during initialization lists PHP 8.0–8.5; README currently lists 8.0–8.4. Check the current workflow before changing compatibility claims.
