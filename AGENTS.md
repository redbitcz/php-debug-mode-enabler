# Project guide

## Working conventions

- Write code, comments and documentation in English.
- Document current behavior in the repository and migration steps in `UPGRADE.md`.
  Put work history, review deliberations and validation milestones in PR descriptions.
- Use strict types, four spaces, PascalCase constants and the existing brace style.
  Prefer native types; avoid complex PHPDoc solely to increase the PHPStan level.

## Design constraints

This standalone library decides debug mode during early bootstrap, before a DI
container exists. See [architecture](docs/architecture.md) for rule ordering,
persistence and SignedUrl behavior; [README](README.md) for usage and
[UPGRADE](UPGRADE.md) for v5 → v6 migration.

- Keep source compatible with PHP 8.3. Dependency constraints live in `composer.json`.
- JWT is optional for Detector-only applications. Preserve `JWTImpl` and extensible
  adapter selection. Do not impose a package-wide conflict with older JWT versions.
- Keep URL parsing, canonicalization and redirects unchanged unless requested.
  Audience is intentionally optional. Invalid JWT input must remain neutral (`null`).
- Storage reads check the top-level array only; do not add token or expiry filtering.
- Update `.phpstorm.meta.php` when changing public modes or algorithms.
- Preserve both Composer download and lock-file caches in CI. Apply workflow changes
  in separate commits, push each step and wait for CI before proceeding.
- Do not commit dependencies, `composer.lock` or generated test artifacts.

## Validation

```sh
composer install
composer run test -- -C
composer run phpstan
```

Tester uses `TestCase`, `@testCase` and a final `run()` call. Restore modified globals,
environment variables and `JWT::$timestamp`. Persistence tests use unique temporary
paths via the Enabler constructor and delete only their own data. Their namespaced
`setcookie()` spy captures cookie output; it does not test browser enforcement.

PHPStan analyzes `src/` at level 5, including JWT adapters. If sandbox restrictions
prevent worker sockets, use `composer run phpstan -- --debug`. The CI PHP matrix is
maintained in `.github/workflows/code_analysis.yaml`.
