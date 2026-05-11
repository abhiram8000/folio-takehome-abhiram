# Folio Take-Home Walkthrough Notes

Repo: https://github.com/abhiram8000/folio-takehome-abhiram

## What I Built

- Scheduled publishing: staff can set an optional publish date/time for a document.
- Recipient gating: if a share link is opened before the publish time, the recipient sees a not-yet-available message.
- Human-readable document IDs: documents get short IDs such as `spring-permit-guide-7qk`.
- Share by name/search: staff can search the admin document list by title.
- Tests: feature coverage for readable IDs, scheduled publishing, audit logging, and search.

## Demo Outline

1. Create a document called `Spring Permit Guide` with a future publish time.
2. Show the readable ID and scheduled status in the admin table.
3. Generate a recipient share link.
4. Open the link before publish time and show the not-yet-available page.
5. Clear the schedule, save, and refresh the same link to show the document body.
6. Search for `Permit` in the admin list.
7. Show the migration, helper logic, page changes, and tests in GitHub.

## Design Decisions

Readable IDs complement share tokens instead of replacing them. The readable ID is useful for staff workflows, but recipient links still use private random tokens so documents are not easy to guess.

Scheduled documents can still be shared early. This supports the staff workflow of preparing and sending links ahead of time while keeping the document body hidden until the scheduled publish time.

Search uses simple case-insensitive substring matching instead of fuzzy search. For this small internal tool, substring search is predictable, easy to explain, and easy to test.

Schema changes live in `migrations/001_document_publishing_and_readable_ids.sql`. `schema.sql` remains the base schema, and `seed.php` applies migrations after loading it so `docker compose up` still works from a fresh clone.

## File Map

- `migrations/001_document_publishing_and_readable_ids.sql`: adds `readable_id`, `publish_at`, and supporting indexes.
- `lib/bootstrap.php`: shared helpers for migrations, readable IDs, scheduling, search, document creation, schedule updates, and share creation.
- `public/admin.php`: create documents, set schedules, search, and show readable IDs.
- `public/share.php`: create private share links and warn staff when a document is scheduled.
- `public/view.php`: blocks recipient views until a document is published.
- `tests/test.php`: feature tests for the new behavior.

## Verification

I ran the app locally with Docker and verified the main recipient flow in the browser. The test suite passed locally:

```text
6 passed, 0 failed.
```

## AI Workflow

I used Codex as a pair programmer. I first used it to inspect the repo and explain the existing PHP/SQLite flow before changing code. Then I used it to help implement the feature slices and add focused tests.

I made the main product decisions: keeping private token links, making readable IDs staff-facing, using simple title search, and keeping migrations lightweight.

One place I intentionally kept the solution smaller was avoiding a full framework, a fuzzy search library, or a large migration system. Those felt too heavy for the timebox and the size of this app.

I did not include the full raw AI chat transcript because it was long and noisy. These notes summarize the useful workflow decisions, and I used AI to help trim filler and organize the explanation for the video.

## More Time

With more time, I would add edit/delete document flows, pagination for the admin list, browser-level tests for the recipient flow, and a real migration tracking table if this grew beyond a take-home project.
