# Review of the major-version preparation

Reviewed commit: `7917a42`. Its GitHub Actions run succeeded:
https://github.com/redbitcz/php-debug-mode-enabler/actions/runs/39274172583

The current suite has 39 tests and PHPStan level 5 includes JWT adapters. The
following focused reproductions were run separately on PHP 8.5 with JWT 7.2.1,
using temporary storage outside the repository. No production code was changed.

## Recommended fixes before release

1. **Malformed JWT exceptions escape detection.** With the SignedUrl plugin reached,
   `_debug=eA.e30.eA` throws `DomainException: Syntax error, malformed JSON`.
   `verifyToken()` wraps only `RuntimeException`; `__invoke()` catches only
   `SignedUrlVerificationException`. Handle malformed-token failures at the JWT
   boundary without hiding key/algorithm configuration errors. This does not require
   changing URL parsing, normalization, or redirects.
2. **Cookie setter composition emits a warning.** Calling `setSecure(false)` followed
   by `setCookieOptions(path: '/app')` accesses the removed `samesite` key. Reproduced
   with a warning-to-exception handler that respects PHP error suppression.
3. **Storage structure is trusted before validation.** A file containing JSON `null`
   produces a return-type error; `[{}]` produces an undefined `id` warning. Handle
   invalid top-level values and malformed entries before dereferencing them. Keep
   this separate from cosmetic array-shape annotations.
4. **Push CI targets the wrong branch.** The repository's default branch is `master`,
   while the workflow's push filter specifies `main`. PR CI works, but merging to or
   pushing directly to `master` does not trigger this workflow through the push event.

## Additional worthwhile work

- `activate(true)` followed by `deactivate()` on the same instance with no incoming
  token leaves one token in storage. Repeated activation likewise cannot revoke the
  newly created token because lookup still uses the incoming cookie. Confirmed for
  activate/deactivate; this is a lifecycle/cleanup issue, not evidence that the final
  browser cookie remains enabled.
- Token storage reads and writes have no transaction-level locking. The installed
  FileSystem implementation writes directly with `file_put_contents()`. Concurrent
  requests can overwrite each other's updates; this follows from code inspection,
  not a concurrency stress test. Atomic replacement alone would not solve lost updates.
- Add a dedicated Enabler suite covering persistence, expiry, malformed storage,
  setter composition, and repeated activation/deactivation. Current Detector tests
  exercise the in-memory Enabler override, not persistence or browser cookies.
- Expand negative JWT tests for malformed encoding, wrong audience, tampering, and
  expired tokens through the actual Detector plugin path. Keep audience optional.
- Consider `hash_equals()` for token hash comparison as a small hardening change;
  no practical timing exploit was established by this review.

## Deliberately deferred

Do not redesign URL handling or redirects, require audience, remove lock-file
caching, or replace the extensible JWT adapter interface. Keep PHPStan level 5
unless more precise Enabler data types are justified by actual maintenance needs.
Legacy PHP 7 inspection suppressions and stale documentation wording can be cleaned
up separately; these are not release blockers.
