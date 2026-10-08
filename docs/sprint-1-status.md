# Sprint 1 implementation status

Updated 8 October 2026.

## Finished

- Docker installation: PHP 8.5, Node 24, MySQL 8.4, Redis 7.4, web, queue, scheduler, optional Vite, persistent private storage, isolated test databases, CI, and local HTTPS staging.
- Identity: student self-registration, required Egyptian login phone and WhatsApp declaration, generated student code, phone/code login, password/profile changes, session invalidation, suspension/reactivation, instructor/operator recovery, and no guest course actions.
- Academic setup: semesters, courses, offerings, optional groups with capacity, lesson identities, opening/completing/archiving, instructor/invitation enrollment, enrollment-scoped student course visibility, transfer requests, withdrawal/restoration, and group history.
- Interface and operations: colorful mobile English/LTR and Arabic/RTL screens, locally bundled educational fonts, simple SVG icons, state-preserving locale switch, dashboards, course directory, localized queued notices, scoped audit log, health commands, and opt-in synthetic two-course/two-group data.
- Automated acceptance: MySQL feature/Livewire tests, a simultaneous last-seat test, formatting checks, and Playwright journeys for mobile Chromium, mobile WebKit, and desktop Chromium.

## Work in progress / external decisions

- Serv00 is the selected production host and its environment template, deploy script, web-root layout, and cron worker are documented. Public staging is not deployed because no server, domain, or deployment credentials were supplied.
- Physical Android/iPhone review and Arabic wording approval need the instructor's devices/content review. Browser emulation is automated.
- Off-host backups, monitoring, retention values, verified Serv00 capacity, and optional instructor authenticator-app 2FA remain product/operations decisions.

## Delivered after Sprint 1

- Sprint 2: Saturday–Friday class planning, publication/cancellation, rotating authenticated QR and short-code attendance, per-student first two attended lessons free, roster finalization, makeup authorization, attendance history, and audited corrections.

## Planned

- Sprint 3: private payment-receipt upload, quarantine/scanning, instructor approval/rejection, payment entitlement, and payment notices.
- Sprint 4: timed tests, answer autosave, submission/grading, eligibility, and grade release.
- Sprint 5: reports/CSV exports, announcements, load/device/security checks, backups/restore, production runtime, and controlled pilot.
