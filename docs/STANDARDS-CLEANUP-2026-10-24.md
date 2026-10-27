# Standards cleanup — October 24, 2026

The readiness report remains in the editor. This cleanup addresses the repository findings that were reported after that feature was implemented.

## Results

| Check | Before this cleanup | Current |
| --- | ---: | ---: |
| JavaScript ESLint | 315 errors, 37 warnings | 0 errors, 0 warnings |
| CSS/SCSS Stylelint | 125 errors | 0 errors |
| WordPress Prettier | Not recorded | Pass |
| PHP WordPressCS | 7,536 errors, 2,737 warnings | 2,322 errors, 2,685 warnings |

PHP is not certified clean. The official rules remain enabled; no blanket baseline was added. The full PHP report is retained locally under test-results/lint-php-latest-full.json.

## Changes

- Documented PHP declarations and aligned parameter documentation with the actual signatures. Existing public method names, hooks, REST fields, class mappings and assessment extension contracts remain in place.
- Replaced 400 table-name concatenations with wpdb identifier placeholders. WordPress 6.8 is the plugin minimum and supports these placeholders. Value bindings retain their query order.
- Added template output escaping according to the HTML attribute, URL, text or permitted-markup context. Shared rich content and owned interactive fragments were not escaped wholesale as plain text.
- Replaced accidental PHP shell backticks in an LMS error message with a translated sprintf message. Corrected legacy text domains and used WordPress equivalents for applicable string/JSON/URL helpers.
- Kept the existing AJAX nonce actions and parameter names while checking them before reading upload and cart input. Comment rewards now read the stored WordPress comment rather than raw POST data.
- Normalized JavaScript comparisons for live number/string ID and numeric field contracts, removed unused bindings, fixed translations at their literal definitions, and corrected hook dependencies and unconditional hook placement.
- Replaced console-only failures and blocking browser alerts with dismissible text-only error feedback. Destructive confirmations await an accessible dialog and restore focus on dismissal.
- Consolidated duplicate CSS rules and ordered rules by specificity within their conditional context. Existing public selectors remain supported.

## Narrow compatibility/tooling details

Existing public include paths retain file-local WordPress.Files.FileName exceptions. The stylesheet naming allowlist now also documents the existing HubIcons BEM modifiers and the existing AI new_skill status selector; these names were not renamed.

WordPressCS recognizes ohmylms_clean as a custom sanitizer because it recursively delegates scalar HTTP inputs to sanitize_text_field. It is not registered as an unslashing function: the request-boundary wp_unslash requirement remains enabled.

The Windows formatter encountered transient sharing violations. The final formatting pass wrote temporary sibling files, compared input hashes, and applied changes only where the source was unchanged. All generated temporary files were removed after successful application.

## Verification

- Full JavaScript suite: 629 passed, one existing skip, including error-feedback and deferred-confirmation tests.
- Source contracts: 1,718 JavaScript/JSX files parse and all 237 component contracts validate.
- AJAX nonce integration: four handlers reject invalid tokens before cart or upload work.
- PHP integration: shared structure 61, unified outline 22, learner/practice guidance 43 checks passed. Pure model and structure-tool checks passed (37 and 295).
- Browser: course authoring, publication, stale-revision protection, readiness repair navigation and refresh failure/retry passed; practice completion and refreshed guidance passed.
- Gradebook integration completed, but its temporary-table fixture emitted missing optional-table database warnings. This is not a warning-free full commerce test certification.
- Production build completed with the existing three webpack performance warnings. Generated assets were rebuilt from source.

The broad legacy PHP findings still require manual review where reported. In particular, a direct custom-table query does not become a fully reviewed caching or authorization policy merely because its SQL is prepared.
