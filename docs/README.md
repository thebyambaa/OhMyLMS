# OhMyLMS documentation

This folder is the source of truth for how the plugin is built, extended and verified. The plugin root [README.md](../README.md) is the short entry point; this page lists every maintained document.

**Last reviewed:** 31 October 2026 (aligned with the recovered-admin cutover and learner Interactivity batches L1–L7).

## Build, architecture and quality

| Document | Purpose |
| --- | --- |
| [DEVELOPMENT.md](DEVELOPMENT.md) | Node/PHP commands, test databases, `OHMYLMS_SOURCE_ASSETS`, translations |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Typed admin app: routing, boundaries, extension host |
| [REACT-DEVELOPMENT.md](REACT-DEVELOPMENT.md) | Feature workflow, component rules, tests |
| [MODULARIZATION-ROADMAP.md](MODULARIZATION-ROADMAP.md) | What is modularized, what remains, feature parity table |
| [STATE-API-MODULARIZATION.md](STATE-API-MODULARIZATION.md) | Shared hooks and API access patterns in the app |
| [ENFORCEMENT.md](ENFORCEMENT.md) | Architecture checks, CI jobs, byte budgets |
| [CODING-STANDARDS.md](CODING-STANDARDS.md) | WordPress standards and repository exceptions |
| [CUTOVER.md](CUTOVER.md) | Record of deleting the recovered admin (31 Oct 2026) |
| [LEGACY-ROUTES.md](LEGACY-ROUTES.md) | Old `#/…` and `page=ohmylms-app` redirects |
| [BACKEND-BOUNDARIES.md](BACKEND-BOUNDARIES.md) | REST and persistence boundaries in PHP |
| [VENDOR-PACKAGE-MIGRATION.md](VENDOR-PACKAGE-MIGRATION.md) | Remaining third-party runtimes (MathLive, JSXGraph, …) |

## Admin UX and design

| Document | Purpose |
| --- | --- |
| [ADMIN-REDESIGN.md](ADMIN-REDESIGN.md) | Six admin pages, dashboard and gradebook phases |
| [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md) | Admin colour and typography contract |
| [DESIGN-TOKENS.md](DESIGN-TOKENS.md) | `--oml-*` variables (admin and learner) |
| [DESIGN-LANGUAGE.md](DESIGN-LANGUAGE.md) | Components and patterns |
| [STYLESHEET-OWNERSHIP.md](STYLESHEET-OWNERSHIP.md) | Which CSS ships with which bundle |
| [ADMIN-CSS-MODULES.md](ADMIN-CSS-MODULES.md) | Feature-scoped admin styles |
| [COMMAND-PALETTE.md](COMMAND-PALETTE.md) | Quick navigation |

## Learner front end

| Document | Purpose |
| --- | --- |
| [LEARNER-FRONTEND.md](LEARNER-FRONTEND.md) | Script map and Interactivity migration (L1–L7) |
| [INTERACTIVITY.md](INTERACTIVITY.md) | Store contracts, events and extension slots |
| [STUDENT-HOME.md](STUDENT-HOME.md) | `[ohmylms_student_hub]` and hub settings |
| [learner-experience/](learner-experience/) | Schools, practice, release readiness |

## Product features (authoring and content)

Content hub, syllabus, skills, assessments, commerce, schools, gamification and related topics live as standalone guides in this folder (for example [CONTENT-HUB.md](CONTENT-HUB.md), [ASSESSMENT-ENGINE.md](ASSESSMENT-ENGINE.md), [GUTENBERG-AUTHORING.md](GUTENBERG-AUTHORING.md), [SCHOOL-FAMILY-SETUP.md](SCHOOL-FAMILY-SETUP.md)). Use the root README’s “Authoring content” and “Learners” sections for the curated list.

## REST API contracts

One markdown file per admin feature under [api/](api/) (for example [api/courses.md](api/courses.md), [api/gradebook.md](api/gradebook.md)). These match the typed `assets/src/app/features/*/api.ts` clients and PHP integration tests.

## Verification and coexistence

| Document | Purpose |
| --- | --- |
| [ACCEPTANCE.md](ACCEPTANCE.md) | Where to look for current test coverage (not a historical log) |
| [CREATORLMS-COEXISTENCE.md](CREATORLMS-COEXISTENCE.md) | Optional side-by-side install with Creator LMS |
| [GRADEBOOK-RESEARCH.md](GRADEBOOK-RESEARCH.md) | External platform research for gradebook design |

## Retired material

One-time migration plans, vendor audits, dashboard latency notes, the in-repo design prototype (`docs/design-reference/`), and the recovered-admin acceptance log were removed or folded into the documents above. Details remain in git history and in [CONTINUE-LEARNER-HUB.md](../CONTINUE-LEARNER-HUB.md) for learner-hub work.
