# Sprint 2 implementation status

Updated 8 October 2026.

## Finished

- Saturday–Friday planning: an interactive calendar with click-to-create, click-to-edit, drag/resize rescheduling, draft creation, custom attendance windows, semester/date validation, instructor/group conflict blocking with a reasoned override, cross-course student warnings, previous-week copying, selected/whole-week publishing, cancellation, explicit class start/completion, and immediate attendance closure.
- Authenticated attendance: instructor-only rotating HTTPS QR display, 60-second credentials refreshed every 30 seconds, rate-limited short-code fallback, authentication-first scan landing, browser/user/session-bound two-minute intents, and explicit retry-safe results.
- Access rules: enrollment/group checks, audited makeup authorization, one credit per enrollment and lesson, row-locked transactional writes, two personal free attended lessons per offering, and a course-entitlement/waiver interface for Sprint 3 payment approval.
- Attendance operations: present/late classification from accepted intent time, roster capture when the window opens, delivered-class absence finalization after intents expire, payment-denial visibility, manual corrections and voids with reasons/revisions, and student attendance history.
- Bilingual operations: English/LTR and Arabic/RTL instructor planner, class control, QR projection, student confirmation/results, localized notices, Thursday unpublished-week reminders, 24-hour class reminders, and dashboard schedule summaries.

## Not executed in this pass

- Automated PHP, browser, concurrency, formatting, and static-analysis commands were not run because this implementation was requested without testing.
- Physical Android/iPhone QR scanning, weak-network behavior, Arabic wording review, and public staging acceptance remain release validation work.

## Planned

- Sprint 3: private payment-proof upload and quarantine, instructor review, entitlement creation/revocation, temporary waivers, payment instructions, and payment notices.
- Sprint 4: timed tests, answer autosave/resume, submission, grading, result release, and assessment notices.
- Sprint 5: full reporting/CSV exports, announcements, operational hardening, load/device/security checks, backups/restore, and controlled production pilot.
