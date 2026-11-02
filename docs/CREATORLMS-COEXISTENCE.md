# Running OhMyLMS with CreatorLMS

> **Note:** Local development sites for this plugin usually run **OhMyLMS alone** (Tutor LMS and other LMS
> plugins are removed to avoid side effects). This document applies when you deliberately install Creator LMS
> alongside OhMyLMS for coexistence testing.

OhMyLMS can load alongside Creator LMS 1.2.21 in the same WordPress installation. CreatorLMS's source does not need modification. Both plugin load orders and activating either plugin second are covered by the isolated integration test.

## Runtime separation

OhMyLMS owns `OhMyLMS\Commerce` instead of sharing CreatorLMS's `CodeRex\Ecommerce` namespace. Its package, offline and Stripe gateways, tax calculator, VAT helpers, and previously shared procedural functions also have distinct names. Composer generates separate `OhMyLMSCore` and `OhMyLMSCommerce` bootstrap classes, avoiding a second collision in the copied dependency loaders.

Courses, order stores, settings, sessions, cookies, hooks, and payment services retain their existing OhMyLMS identifiers and data. There is no course or order database conversion. The established `course_category` and `course_tag` taxonomies remain shared; a compatibility hook keeps both course types attached after registration. These categories and tags are not separate catalogs of terms.

Give each LMS its own course catalog, profile, checkout, and membership pages, with distinct page URLs. WordPress users are shared, while LMS enrollment and commerce records remain separate. Do not configure both plugins to use the same checkout page.

## Commerce API migration

OhMyLMS's canonical commerce API is `/wp-json/ohmylms-commerce/v1/`. The authored coupon UI and the build adapter for recovered order/subscription clients use this namespace. Payload fields and permission callbacks are unchanged.

When CreatorLMS is inactive, OhMyLMS also registers its previous `/wp-json/cx-ecommerce/v1/` routes for existing API clients. When both plugins run, `cx-ecommerce/v1` belongs to CreatorLMS, and custom OhMyLMS clients must use `ohmylms-commerce/v1`. One shared URL cannot select two independent order stores.

The main `/wp-json/ohmylms/v1/` API, extension hooks, and versioned assessment contracts are unchanged.

## Custom PHP extensions

Update custom OhMyLMS extensions and theme overrides to the new names before installing CreatorLMS:

| Previous OhMyLMS reference | Current reference |
| --- | --- |
| `CodeRex\Ecommerce\…` | `OhMyLMS\Commerce\…` |
| `CodeRex\Ecommerce\ecommerce()` | `OhMyLMS\Commerce\ecommerce()` |
| `Package` | `OhMyLMS_Commerce_Package` |
| `GatewayOffline` | `OhMyLMS_Gateway_Offline` |
| `GatewayStripeIntents` | `OhMyLMS_Gateway_Stripe_Intents` |
| `TaxCalculator` | `OhMyLMS_Tax_Calculator` |
| `EuVatApi` / `EuVatApiResponse` | `OhMyLMS_EU_VAT_API` / `OhMyLMS_EU_VAT_API_Response` |
| `ECOMMERCE_MODULE_PREFIX` | `OHMYLMS_ECOMMERCE_MODULE_PREFIX` |
| Global `ecommerce_*()` helpers | Global `ohmylms_ecommerce_*()` helpers |
| `get_best_selling_course_ids()` | `ohmylms_get_best_selling_course_ids()` |
| `get_top_rated_course_ids()` | `ohmylms_get_top_rated_course_ids()` |
| `get_top_reviewed_course_ids()` | `ohmylms_get_top_reviewed_course_ids()` |
| `get_free_course_ids()` / `get_paid_course_ids()` | `ohmylms_get_free_course_ids()` / `ohmylms_get_paid_course_ids()` |
| `get_recent_course_ids()` | `ohmylms_get_recent_course_ids()` |
| `is_crlm_admin_page()` | `ohmylms_is_admin_page()` |
| `if_theme_support_available()` | `ohmylms_if_theme_support_available()` |
| `crator_lms_get_question_ans_by_question_id()` | `ohmylms_get_question_ans_by_question_id()` |
| `creatot_lms_create_new_student_username()` | `ohmylms_create_new_student_username()` |

On installations without CreatorLMS installed, deprecated wrappers and lazy class aliases preserve the previous PHP API, including original parameter names for named arguments. The bridge is deliberately disabled as soon as CreatorLMS is installed, even if inactive. This prevents existing aliases from causing a fatal error when CreatorLMS is activated later in the same request. Do not include the bridge files directly.

**Narrow compatibility exception:** the copied, shared PHP identifiers and commerce REST namespace cannot continue to identify OhMyLMS when CreatorLMS owns them. The bridge supports standalone upgrades; parallel installations require the explicit migration above. Existing PSR-4 directory-to-class mappings, entrypoint filenames, and public method names are retained, except for the commerce namespace and the six colliding global classes. Their authored consumers and Composer mappings are updated together. Existing CamelCase filenames remain necessary for those established mappings; remaining filename-style reports are not suppressed. The bridge retains the previous `$return` parameter name for compatibility, despite the standards warning.

## Rebuilding and verification

Regenerate dependency loaders from the two authored Composer manifests:

```text
composer dump-autoload --no-plugins --no-scripts
composer dump-autoload --no-plugins --no-scripts --working-dir=packages/e-commerce
npm run build
```

Do not edit Composer-generated loaders directly. The recovered admin bundles and the `tools/commerce-adapters.mjs` path rewriting they needed were deleted at the cutover ([CUTOVER.md](CUTOVER.md)); the typed app calls the commerce routes directly.

Run `tests/php/creatorlms-coexistence.php` in fresh PHP processes with `OHMYLMS_TEST_CREDENTIALS` pointing to the isolated fixture site's credentials, using these scenarios: `activate-creatorlms`, `activate-ohmylms`, `ohmylms-first`, `creatorlms-first`, `ohmylms-only`, and `ohmylms-legacy`. The fixture must use the `ohmylms_source_test` database and contain both plugin checkouts. The legacy-only case uses `wp-content/standalone-plugins` containing only OhMyLMS. Test credential access uses local CLI file streams; the two filesystem standards warnings are limited to that fixture input.

Validated on the isolated WordPress installation with PHP 8.2.30 and PHP 8.5.4:

- 78 coexistence checks: real activation in either direction, both load orders, separate carts/gateways/session cookies/coupon lookups, route ownership, administrator access, anonymous access denial, shared taxonomy attachment, and standalone legacy compatibility.
- 58 native QPay integration checks while both LMS plugins are loaded. The absent optional legacy QPay add-on compatibility check is explicitly skipped.
- 43 assessment integration checks while both LMS plugins are loaded.
- Extension authoring integration and the commerce API path adapter test pass.
- Production assets build successfully, with two existing asset-size warnings.

Repository-wide checks are not clean. When this was validated the JavaScript suite had three failures (the quick-prompt/full-editor check, quiz autosave and recovered Elementor asset parity) and the legacy PHP unit fixture lacked the question object's `get_settings()` method; both were fixed later (the October 16, 2026 run passes 425 JavaScript tests with 0 failures and 1 skip, see `docs/learner-experience/RELEASE-READINESS.md`). Standards checks report extensive existing PHP, JavaScript, CSS, and formatting violations; these are not hidden with a baseline or disabled rules. CreatorLMS Pro, all third-party gateways, and arbitrary external extensions are outside this verification.
