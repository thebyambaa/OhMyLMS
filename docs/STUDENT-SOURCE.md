# Student and registration source — 2026-09-24

> **Historical.** The converted student components and adapters described here were deleted with the recovered
> admin on 31 October 2026 ([CUTOVER.md](CUTOVER.md)); the signup fields are now the `admin-app-registration`
> entry ([ARCHITECTURE.md](ARCHITECTURE.md)).

The opt-in source build loads four student components from `assets/src/features/students/`: StudentList, StudentNameCell, StudentReport and CourseStudents. StudentList has authored JSX, with request state in `useStudents.js` and REST contracts in `api.mjs`. The remaining components contain reconstructed JSX and retain shared runtime dependencies recorded in `components.json`. They are editable, but are not fully independent of the recovered application.

`tools/student-adapters.mjs` verifies and replaces all four original component bindings. Do not rerun the extraction tool over authored changes.

## Registration

`RegistrationForm.jsx` and `mountRegistration.js` replace the standard fields and submit button inside the existing PHP form. PHP retains the start/form/end hooks, nonce, action, redirect, privacy-policy field, login link and country list. The shared global template also supplies checkout registration. Theme overrides without the mount markers continue to use their existing form.

React uses the existing signup AJAX endpoint and submits the complete FormData, including extension fields. Native required/email/password validation, privacy acceptance, password visibility, submission locking, text-only error display, same-origin redirects and pending email-verification notices are handled in source. Account creation, roles and verification remain server-owned.

Student sorting uses the endpoint's `order_by` parameter. Sort buttons issue server requests directly because the shared table does not emit the originally supplied `onChange`. Bulk action state follows the current selection, and failed requests keep the confirmation available for retry.

## Verification

- `npm run build`, `npm test` (18 tests including source parity), and `npm run lint`.
- `tests/browser/students.spec.cjs`: real account creation and redirect with fixture cleanup; privacy gating, password toggle, preserved nonce/extension fields, validation errors and mocked pending-verification responses; sorting, search and bulk selection against mocked student responses.
- `tests/php/students-integration.php`: guest/student permission rejection for list/report/block/unblock and actual administrator block/unblock persistence, with fixture cleanup in the disposable WordPress database.
- Existing desktop/mobile admin route smoke test, including Students.

Browser tests use the existing isolated profile page (ID 210). Set `OHMYLMS_TEST_CREDENTIALS` as described in DEVELOPMENT.md. PHP integration requires `OHMYLMS_TEST_SITE` and database `ohmylms_source_test`.

Actual verification email delivery/link completion, full student reports, course enrollment management, checkout purchase completion, and comprehensive responsive/RTL testing remain broader acceptance work. The source build remains behind the existing whole-application `OHMYLMS_SOURCE_ASSETS` switch and is enabled on the isolated test site. This increment does not enable it on the working math site or add a database migration.
