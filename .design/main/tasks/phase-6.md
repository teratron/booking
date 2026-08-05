---
phase: 6
name: "Discovery, Reporting & Public API"
status: Todo
subsystem: "app/Services, app/Http, app/Filament/Admin"
requires: ["phase-3", "phase-5"]
provides: []
key_files:
  created: []
  modified: []
patterns_established: []
duration_minutes: ~
---

# Stage 6 Tasks — Discovery, Reporting & Public API

**Phase:** 6
**Status:** Todo
**Strategic Goal:** Make the portal findable and its performance legible — URL grammar,
metadata, structured data, sitemaps and redirects; portal-wide reporting and exports;
and a versioned read-only REST contract behind issued tokens.

## Atomic Checklist

Not yet decomposed. See §Scope.

## Scope

| Area | Spec |
| --- | --- |
| URL grammar across languages and the territory hierarchy | l1-seo.md §5.1 |
| Metadata resolution with administrator-defined templates | l1-seo.md §5.2, §3.3 |
| Indexation policy for filtered and paginated catalog views | l1-seo.md §5.3, §3.2 |
| Structured data per page type, and the no-overstatement rule | l1-seo.md §5.4, §3.4 |
| Sitemap index, pagination, regeneration job, redirect table | l1-seo.md §5.5 |
| Back-office SEO administration and warnings | l1-seo.md §3.3; l1-back-office.md §5.1 |
| Portal-wide reporting across every aggregation dimension | l1-analytics.md §5.3, §5.4 |
| Traffic sources and page popularity | l1-analytics.md §5.6 |
| Versioned read contract mirroring the catalog's ordering | l1-public-api.md §5.1 |
| Token model, scoping, revocation, rate limits | l1-public-api.md §5.2, §5.3 |
| Generated API documentation | l1-public-api.md §5.4 |

## Standing Constraints

- Filtered catalog views are **not indexable by default**. A filter combination becomes
  indexable only by explicit administrator decision — this is the portal's principal
  defence against near-duplicate proliferation across three dimensions that multiply.
- Structured data must not overstate. Where the booking module is inactive, an object
  page must not emit offer availability it cannot honour.
- Collection endpoints return the **same ordering the catalog renders**. A neutral
  ordering would let a consumer build a competing listing that bypasses the portal's
  revenue model entirely.
- The API exposes published, public data only — at every tier, for every token.
- A disabled API module returns 404, not 403: a 403 confirms the capability exists.
- Slug changes create a permanent redirect; slugs are never silently reused.

## Decomposition Trigger

Decomposed into atomic `T-6XXX` tasks by `/magic.task main` once Phase 5 completes.
