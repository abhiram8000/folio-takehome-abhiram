# Implementation Notes

## Scope

I built all three requested features in a small, plain-PHP style that matches the existing app:

- scheduled publishing for recipient views
- short readable document IDs for staff-facing references
- title search on the admin document list

## Product Choices

Readable IDs complement share tokens instead of replacing them. Staff can use IDs like `welcome-packet-7qk` in the admin/share flow, but recipient links still use private random tokens. That keeps the nice human workflow without making documents guessable from public URLs.

Scheduled documents can still have share links created ahead of time. Recipients who open a link too early see a not-yet-available page instead of the document body.

Search is case-insensitive substring matching on title, with readable ID matching included as a convenience. For this size app, that is easier to explain and verify than fuzzy search.

## Migration Approach

`schema.sql` remains the base schema. New schema changes live in `migrations/*.sql`, and `seed.php` applies them after loading the base schema. This keeps `docker compose up` working from a fresh clone while making the schema changes visible as migrations.

## AI Workflow

I used Codex to read the codebase, identify the smallest safe design, implement the PHP changes, and add tests. I kept the solution intentionally simple: no framework, no heavy routing, and no replacement of the existing token model. The main pushback against a larger AI-style solution was avoiding a full migration framework or fuzzy search library for a three-hour junior take-home.

With more time, I would add edit/delete document flows, pagination for the admin list, and browser-level tests around the recipient view.
