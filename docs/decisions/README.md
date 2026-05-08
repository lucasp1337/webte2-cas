# Architecture Decision Records

This directory holds Architecture Decision Records (ADRs) for the WEBTE2 project.

## When to write one

Write an ADR when you change anything in the locked stack (`CLAUDE.md §2`) or any architectural choice with cross-phase impact: choosing a different framework or library, replacing a service, changing the container split, or altering a contract that other phases consume (events, DTOs, route shapes).

Don't write ADRs for in-phase implementation choices, refactors, or anything reversible inside a single PR.

## File format

```
NNNN-short-slug.md
```

`NNNN` is a zero-padded sequential index (`0001`, `0002`, …). The slug is kebab-case and short — what was decided, not why.

Example: `0001-replace-mysql-with-postgres.md`.

## Required sections

```markdown
# NNNN. <Title>

**Status**: Proposed | Accepted | Superseded by ADR-NNNN | Deprecated
**Date**: YYYY-MM-DD

## Context

What forces are at play? Why is this decision needed now?

## Decision

What we're doing. One paragraph, declarative.

## Consequences

What becomes easier, what becomes harder, what new obligations this creates.
```

Keep ADRs short. If you can't explain the decision in a page, the decision is probably not yet ready.
