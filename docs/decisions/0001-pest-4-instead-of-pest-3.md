# 0001. Use Pest 4 instead of Pest 3 for the PHP test suite

**Status**: Accepted
**Date**: 2026-05-08

## Context

`CLAUDE.md §2` locks the stack to Pest 3. Phase 01 installs `laravel/framework ^13` and the matching test plugin `pestphp/pest-plugin-laravel`.

`pestphp/pest-plugin-laravel ^3` declares a hard `illuminate/* ^11.0|^12.0` constraint. It does not work against Laravel 13. The next supported version is `pestphp/pest-plugin-laravel ^4`, which depends on `pestphp/pest ^4`.

Composer cannot resolve a Laravel 13 install with Pest 3. The choice is either Pest 4 (forced) or downgrade to Laravel 12 (not viable — Laravel 13 is locked).

## Decision

The PHP test suite uses **Pest 4** (and PHPUnit 11 transitively) for the lifetime of this project. `CLAUDE.md §2` is updated to reflect Pest 4 in the locked stack table.

API differences between Pest 3 and Pest 4 that we may hit:

- Pest 4 changes the way browser/architecture tests are invoked, but neither is in scope for this project.
- Pest 4 raises the minimum PHP version, which is satisfied by our PHP 8.5 lock.
- `vendor/bin/pest --parallel` continues to work the same way.

No changes to test code style or `composer qa` script are required.

## Consequences

- Easier: stays on the supported branch of the test framework alongside Laravel 13.
- Harder: anything in the project plan or external docs that references "Pest 3" is now stale and needs the update propagated alongside this ADR.
- New obligation: the `composer.json` constraint pins `pestphp/pest: ^4`. Future Laravel upgrades that drop Pest 4 support will trigger a new ADR.
