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
- `src/Plugin/JWT/`: the extensible `JWTImpl` contract and Firebase JWT v7 adapter.
- `src/Plugin/SignedUrlVerificationException.php`: signed request validation failures.
- `tests/DetectorTest.php`, `tests/Plugin/SignUrlTest.php`: Nette Tester suites; `tests/bootstrap.php` loads Composer and configures Tester.
- `README.md`: public usage and integration examples.
- `.phpstorm.meta.php`: IDE suggestions for modes and algorithms; update when changing the corresponding public API.
- `phpstan.neon`, `.github/workflows/code_analysis.yaml`: static analysis and CI configuration.

See [architecture and validation notes](docs/architecture.md) for behavior details and the initialization baseline.

## Compatibility and conventions

- Composer requires PHP `^8.3`, `ext-json`, and `nette/utils` `^3.0 || ^4.0 || dev-master`. Keep source syntax compatible with PHP 8.3. The Utils development branch is intentionally allowed for PHP 8.6 compatibility until a compatible stable release is available.
- Development tools require Nette Tester `^2.6` and PHPStan `^2.3`. Tester no longer needs a development branch for PHP 8.6 support.
- Firebase JWT is optional at runtime and required for SignedUrl. Only v7 is supported by the built-in adapter; it is a development dependency for tests. Keep `JWTImpl` and adapter selection extensible for future major versions. Do not add a package-wide conflict with older JWT versions: Detector users may need them for unrelated features.
- PSR-4 maps `Redbitcz\DebugMode\` to `src/` and `Redbitcz\DebugModeTests\` to `tests/`.
- Follow existing style: strict types, four spaces, typed properties and signatures, PHPDoc for array shapes and compatibility-dependent types, braces on separate lines for classes and methods.
- Use PascalCase for project constants; preserve their values and early-bootstrap independence. `resolve()` and individual rules/plugins return `?bool`; the four main detection methods return `bool` with production as the undecided fallback and reject removed fallback arguments. This is not a Nette application despite its Nette utility and test dependencies.
- `vendor/`, `composer.lock`, test output, `tests/lock`, and `tests/temp` are ignored. Do not commit installed dependencies or generated test artifacts.

## Development checks

```sh
composer install
composer run test -- -C
composer run phpstan
```

`-C` keeps the active PHP configuration for Tester. PHPStan analyzes `src/` at level 5; The JWT adapter directory still has a legacy exclusion from the former multi-version integration. Review existing exclusions and suppressions before changing analysis configuration.

If a sandbox prevents PHPStan from opening its parallel-worker socket, run `composer run phpstan -- --debug` to analyze without parallel workers.

Tests use `Tester\TestCase`, `@testCase`, data providers, and a final `(new ...())->run()` call. Preserve method isolation when tests manipulate environment variables, superglobals, or `JWT::$timestamp`. Existing Detector tests purge `tests/temp/enabler`.

CI runs tests and PHPStan separately against Firebase JWT v7. The PHP matrix targets 8.3, 8.4, 8.5, and 8.6. Check the current workflow before changing compatibility claims.

Preserve both Composer download-cache and `composer.lock` caching in CI; retaining the lock cache is an intentional project requirement for CI performance. Apply workflow maintenance in separate commits, push each step, and wait for its CI result before proceeding to the next step.

Keep SignedUrl URL parsing, canonicalization, and redirect behavior unchanged unless explicitly requested: small changes have historically had significant consequences. The audience is intentionally optional; do not make it mandatory. Distinguish Detector mode validation from the modes carried by signed tokens.
