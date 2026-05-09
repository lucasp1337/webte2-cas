# 0002. ULID primary keys for `api_keys` and `request_logs`; 8-char prefix; `webte2_` plaintext

**Status**: Accepted
**Date**: 2026-05-09

## Context

Phase 03 (`docs/phases/phase-03-auth-api-logging.md` § 3.1) introduces the
`api_keys` and `request_logs` tables. The phase doc proposes:

- `api_keys.id` — `bigInt` auto-increment.
- `api_keys.key_prefix` — 8 chars.
- `request_logs.api_key_id` — `bigInt` foreign key.
- API-key plaintext format (§ 3.3 command) — `webte2_<48 random chars>`,
  yielding an 8-char prefix `webte2_x` (`x` = first char of the random part).

The Phase 00 OpenAPI skeleton (`openapi.yaml`) already locks a different
shape:

- `ListRequestLogs` filter `api_key_id` — `format: ulid`
  (`openapi.yaml`, `parameters.in=query, name=api_key_id`).
- `RequestLog.api_key_prefix` — string in the response payload (no length
  given, but the example shows `wk_live_a1b2`, a 12-char prefix).

CLAUDE.md § 11 makes openapi.yaml load-bearing: "if a route changes, this
file MUST change in the same PR." The `api_key_id` filter being typed
`ulid` is a change to the contract surface.

We therefore have to reconcile three things:

1. ID type — bigInt or ULID.
2. Plaintext format — `webte2_…` or `wk_live_…`.
3. Prefix length — 8 or 12 chars.

## Decision

- `api_keys.id` and `request_logs.api_key_id` are **ULIDs**, not bigInts.
  The migration uses `$table->ulid('id')->primary()` and the FK uses
  `$table->foreignUlid('api_key_id')->nullable()->constrained()->nullOnDelete()`.
- API-key plaintext format stays **`webte2_<random>`** as the phase doc
  specifies — distinct, project-branded, and avoids the generic-looking
  `wk_live_` from the openapi example.
- API-key prefix length stays **8 characters** as the phase doc specifies.
- `openapi.yaml` is updated in the same PR: the `RequestLog.api_key_prefix`
  example moves from `wk_live_a1b2` to `webte2_a` to match. The
  `ListRequestLogs.api_key_id` query parameter stays `format: ulid` (no
  change — already correct).

## Consequences

- Easier:
  - PHP-side ULID generation matches the bridge's request_id format —
    consistent ID vocabulary across services.
  - URL-friendly IDs without exposing row counts (sequential bigInts leak
    rate of API-key issuance).
  - openapi.yaml stays the source of truth for the wire contract; phase
    doc is implementation guidance.
- Harder:
  - Larger PK index (16 bytes vs 8 for bigInt). At expected request-log
    volumes (< 1M rows over a 90-day window) this is irrelevant.
  - Eloquent factories must call `Str::ulid()` rather than rely on
    auto-increment.
- Obligations:
  - Every later phase that adds a model with cross-service IDs (e.g.
    `AnimationUsage` in phase 09) defaults to ULID PKs unless there is a
    specific reason to deviate. ADR follows if so.
  - Phase 03 PR ships the openapi.yaml prefix-example update alongside
    the migrations.
