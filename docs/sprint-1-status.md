# Sprint 1 implementation status

Updated 7 October 2026.

## Finished

- Docker installation: PHP 8.5, Node 24, MySQL 8.4, Redis 7.4, web, queue, scheduler, optional Vite, persistent private storage, isolated test databases, CI, and local HTTPS staging.
- Identity: student self-registration, required Egyptian login phone and WhatsApp declaration, generated student code, phone/code login, password/profile changes, session invalidation, suspension/reactivation, instructor/operator recovery, and no guest course actions.
- Academic setup: semesters, courses, offerings, optional groups with capacity, lesson identities, opening/completing/archiving, manual/self/invitation enrollment, transfer requests, withdrawal/restoration, fee snapshots, and group history.
- Interface and operations: mobile English/LTR and Arabic/RTL screens, state-preserving locale switch, dashboards, course directory, localized queued notices, scoped audit log, health commands, and opt-in synthetic two-course/two-group data.
- Automated acceptance: MySQL feature/Livewire tests, a simultaneous last-seat test, formatting checks, and Playwright journeys for mobile Chromium, mobile WebKit, and desktop Chromium.

## Work in progress / external decisions

- Public staging is not deployed because no server, domain, or deployment credentials were supplied. Local HTTPS staging is ready as the deployment rehearsal.
- Physical Android/iPhone review and Arabic wording approval need the instructor's devices/content review. Browser emulation is automated.
- Production hosting, backups, monitoring, retention values, and optional instructor authenticator-app 2FA remain product/operations decisions.

## Planned

- Sprint 2: Saturday–Friday class planning, publication/cancellation, rotating authenticated QR attendance, per-student first two attended classes free, roster finalization, and corrections.
- Sprint 3: private payment-receipt upload, quarantine/scanning, instructor approval/rejection, payment entitlement, and payment notices.
- Sprint 4: timed tests, answer autosave, submission/grading, eligibility, and grade release.
- Sprint 5: reports/CSV exports, announcements, load/device/security checks, backups/restore, production runtime, and controlled pilot.

