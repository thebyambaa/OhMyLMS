# Verification and acceptance

The long acceptance log for the **recovered admin and source-recovery project** (September–October 2026) was retired when that runtime was deleted on 31 October 2026 ([CUTOVER.md](CUTOVER.md)). Do not use old counts or paths from that era.

## What to run today

- **JavaScript:** `npm run check` (formatting, contracts, app tests, architecture checks, production build) and `npm test` / `npm run test:app` as described in [DEVELOPMENT.md](DEVELOPMENT.md).
- **PHP:** `php tests/unit.php`, the integration suites listed in `tests/php-suites.json`, and phpcs on changed lines (`tools/phpcs-changed.mjs`). CI layout is in [ENFORCEMENT.md](ENFORCEMENT.md#ci-jobs).
- **Browser:** `npm run test:browser` (Playwright; optional SmartScore skips when the add-on is absent).

## Feature-specific records

| Area | Document |
| --- | --- |
| Learner hub, schools, Khan practice, MCP | [../CONTINUE-LEARNER-HUB.md](../CONTINUE-LEARNER-HUB.md) |
| Hub release checklist | [learner-experience/RELEASE-READINESS.md](learner-experience/RELEASE-READINESS.md) |
| Modularization parity | [MODULARIZATION-ROADMAP.md](MODULARIZATION-ROADMAP.md) (feature table) |
| Interactivity modules | [INTERACTIVITY.md](INTERACTIVITY.md) and [LEARNER-FRONTEND.md](LEARNER-FRONTEND.md) |
| REST contracts | [api/](api/) plus matching `tests/php/*-integration.php` |

## Known limitations

Compatibility and scope limits for shipping are summarized in the root [README.md](../README.md) and in individual feature docs (for example extension-only SmartScore, optional add-ons). Test sites use disposable data; destructive suites require the isolated databases named in [DEVELOPMENT.md](DEVELOPMENT.md).
