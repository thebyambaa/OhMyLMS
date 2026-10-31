# Frontend Interactivity API and add-on contract (v1)

OhMyLMS and SmartScore require WordPress 6.8 or newer. The frontend modules are ordinary ES modules;
they are loaded on demand by WordPress and do not require the opt-in React source build.

## Migrated interfaces

- Default quiz: individual/grouped/all-question navigation, required-answer validation, character
  limits, exit dialog, countdown and timeout submission. Form fields, nonces and the server submission
  service retain their existing format. Matching requires every pair and text questions require every
  rendered answer field. The server remains the authority for deadlines and grades.
- Matching/reorder: scoped drag-and-drop, tap/click selection for matching, keyboard matching
  (Enter/Space to select/place, Delete to clear) and reorder (Up/Down). Each question owns its state.
- SmartScore: server-rendered shell, scoped practice state, REST loading/grading, score/progress,
  explanations, milestones, errors/retry and per-instance timer cleanup.
- Gamification: reactive reward notification state and reduced-motion support. The existing
  `window.OhMyLMSCelebrate.show(message)` facade queues notifications until the store is mounted.
  Canvas drawing remains an animation helper.
- Course tabs (layouts 1/2), layout-2 chapter expansion, lesson navigation drawer, lesson chapter
  disclosures, filter/assignment accordions and mobile table details.
- Reveal activities continue to use their existing store and share the module-loading helper.
- Commerce (replacing the jQuery `add-to-cart`, `checkout`, `tax-calculation` and Stripe scripts):
  `ohmylms/cart` (buy, enroll, buy-with-points and membership buttons), `ohmylms/checkout` (gateway
  selection, gateway-required fields, coupons, tax/VAT and states, log-in toggle, order submission,
  notices, thank-you copy) and `ohmylms/stripe` (Payment Element; Stripe.js is loaded from
  js.stripe.com only when Stripe is chosen). See [Commerce](#commerce).
- Course carousels (layouts 3/4, replacing Slick): `ohmylms/carousel` adds arrows, optional dots and
  autoplay to a server-rendered CSS scroll-snap track that scrolls without JavaScript. Internal store.
- Page chrome (batch L2 of [LEARNER-FRONTEND](LEARNER-FRONTEND.md)): the page's toast live region, the header
  account menu, the profile drawer, scroll to top, the block-theme menu fallback and the email-verification
  notices (`ohmylms/ui`), and the shared form helpers: floating labels, password visibility, keyboard toggling of
  checkbox labels, the sign-up terms gate and the phone filter (`ohmylms/forms`). See
  [Shared helpers and page chrome](#shared-helpers-and-page-chrome).
- Login, sign-up and course reviews (batch L5): login and sign-up submission with inline errors, the pending
  email-verification notice and the login/sign-up switch (`ohmylms/forms`), and the review star rating and
  required-fields check (`ohmylms/reviews`). All of them also work without JavaScript. See
  [Login, sign-up and reviews](#login-sign-up-and-reviews).
- Learner dashboard and profile (batch L4): cover and profile photo upload and removal, the profile form's
  repeatable skill and link rows, the dashboard membership "View plan" dialog, membership cancellation and
  invoice printing (`ohmylms/account`), and the my-courses tabs (`ohmylms/tabs`). See
  [Learner account](#learner-account).
- Course catalogue (batch L3): search, filters, sort, curriculum buttons, load more, the mobile filter drawer and
  the layout-3 card popup on the course archive and the course list shortcode/block/elements (`ohmylms/catalog`,
  with `@wordpress/interactivity-router`). Every list is a GET URL rendered by the server. See
  [Course catalogue](#course-catalogue).

Admin editors continue to use React. Static reports/leaderboards need no reactive runtime. Media
players, payment provider SDKs other than Stripe, assignment uploads, layout-3
scroll navigation and remaining legacy presentation controls retain their existing integrations. Use the
module contract below for new student interfaces; migration does not require replacing those SDKs.

## Register and load a module

```php
add_action('ohmylms_register_interactivity_modules', static function ($api_version) {
    wp_register_script_module(
        'my-addon/questions',
        plugins_url('questions.js', __FILE__),
        ['@wordpress/interactivity', 'ohmylms/interactivity'],
        '1.0.0'
    );
});

// Ensure validators/mounts register before a core store needs them.
add_filter('ohmylms_interactivity_module_dependencies', static function ($deps, $module) {
    if (in_array($module, ['ohmylms/quiz', 'ohmylms/smartscore'], true)) $deps[] = 'my-addon/questions';
    return $deps;
}, 10, 2);

// In the add-on's trusted PHP renderer:
ohmylms_enqueue_interactivity_module('my-addon/questions');
```

The dependency filter also applies to SmartScore. New modules should use their own store namespace. Core stores:
`ohmylms/quiz`, `ohmylms/questions`, `ohmylms/smartscore`, `ohmylms/gamification`, `ohmylms/ui`,
`ohmylms/tabs`, `ohmylms/curriculum`, `ohmylms/reveal`, `ohmylms/cart`, `ohmylms/checkout`,
`ohmylms/stripe`, `ohmylms/forms`, `ohmylms/reviews`, `ohmylms/account` and `ohmylms/catalog`.

The enqueue helper handles modules discovered after `wp_head`, including shortcodes. It also loads
the shared hidden/focus/reduced-motion stylesheet. Enqueue dependencies before footer modules print.

## Register a question type

Use `ohmylms_register_question_type` on `ohmylms_register_extensions` with `label`, `render`,
`validate`, `grade`, and optional `manual`/`editor` metadata. The custom-question companion plugin
is a working example. It now uses core rendering/grading instead of replacing the quiz template
and adjusting scores after submission. Its old copied quiz-form template is unused.

Frontend checks improve usability; PHP validation and grading must independently enforce the
answer format and correctness. Never ship answer keys in initial context, HTML or module config.

```js
import { registerAnswerValidator, registerQuestionMount } from 'ohmylms/interactivity';

const unregisterValidation = registerAnswerValidator('my-question', (root) => {
  return root.querySelector('input[name]')?.value.trim().length > 0;
});

const unregisterMount = registerQuestionMount('my-question', (root, { questionId }) => {
  // This hook initializes fetched SmartScore HTML inside its data-wp-ignore boundary.
  // Attach behavior only inside root and return cleanup for listeners/resources.
  const input = root.querySelector('input[name]');
  const changed = () => { /* synchronize your own controls */ };
  input.addEventListener('change', changed);
  return () => input.removeEventListener('change', changed);
});
```

Registration rejects duplicate type callbacks and returns an unregister function. A mount callback
may return a cleanup function or omit it when no cleanup is needed. Fetched question HTML is an explicit imperative
boundary because injecting HTML does not hydrate Interactivity directives. Built-in matching/reorder
share operations between their directive handlers and this mount adapter. Legacy add-on scripts
remain a compatibility fallback when no mount callback exists.

## Public events and slots

Events bubble from the relevant component and include `detail.apiVersion = 1`. Subscribe to:

| Event | Additional detail |
| --- | --- |
| `ohmylms:quiz-mounted`, `ohmylms:quiz-unmounted`, `ohmylms:quiz-expired` | `quizId` |
| `ohmylms:quiz-before-navigate`, `ohmylms:quiz-navigated` | `quizId`, `page` |
| `ohmylms:quiz-before-submit` | `quizId`, `exit` |
| `ohmylms:quiz-answer-changed` | `quizId`, `questionNumber` |
| `ohmylms:quiz-error` | `quizId`, `operation` |
| `ohmylms:question-answer-changed` | `questionId` |
| `ohmylms:practice-question-mounted` | `quizId`, `questionId`, `questionType` |
| `ohmylms:practice-before-submit` | `quizId` |
| `ohmylms:practice-answer-graded` | `quizId`, `questionId`, `correct`, `state`, `milestone` |
| `ohmylms:practice-error` | `quizId`, `code` |
| `ohmylms:reward-presented` | `message` |
| `ohmylms:disclosure-changed` | `open` |
| `ohmylms:tab-before-change`, `ohmylms:tab-changed` | `tab` |
| `ohmylms:chapter-toggled` | `chapterId`, `open` |
| `ohmylms:cart-before-add` (cancelable), `ohmylms:cart-added`, `ohmylms:cart-error` | `courseId`, `membershipId`, `purchaseBy`, plus `redirectUrl` or `message` |
| `ohmylms:checkout-gateway-changed` | `gateway`, `previous` |
| `ohmylms:checkout-before-submit` (cancelable) | `gateway` |
| `ohmylms:checkout-pending`, `ohmylms:checkout-complete`, `ohmylms:checkout-error` | `gateway`, plus `redirect` or `message` |
| `ohmylms:checkout-totals-updated` | `reason` (`coupon-applied`, `coupon-removed`, `tax`) |
| `ohmylms:toast` (cancelable, on `document`) | `id`, `message`, `type` (`success`, `warning`, `danger`), `duration`; cancel it to render the notice yourself |
| `ohmylms:toast-dismiss` (on `document`) | `id` (empty for the current toast) |
| `ohmylms:toast-shown`, `ohmylms:toast-hidden` (on `#ohmylms-toast`) | `id`, plus `type` and `message` when shown |
| `ohmylms:dropdown-changed`, `ohmylms:drawer-changed` | `open` |
| `ohmylms:auth-before-submit` (cancelable, on the form) | `form` (`login` or `signup`) |
| `ohmylms:auth-complete` | `form`, `status` (`success` or `pending_verification`), plus `redirect` or `message` |
| `ohmylms:auth-error` | `form`, `message` |
| `ohmylms:auth-view-changed` | `view` (`login` or `signup`) |
| `ohmylms:review-rated` (pre-L5 link stars only; radio stars fire `change`) | `rating` |
| `ohmylms:review-before-submit` (cancelable, on the comment form) | `rating` |
| `ohmylms:account-before-upload` (cancelable, on the profile edit root) | `kind` (`cover` or `avatar`) |
| `ohmylms:account-image-changed` | `kind`, `url` (after an upload, or the default cover after removal) |
| `ohmylms:account-error` | `operation` (`upload-cover`, `upload-avatar`, `delete-cover`), `message` |
| `ohmylms:membership-before-cancel` (cancelable, on the cancel link, before the confirmation) | `subscriptionId` |
| `ohmylms:membership-cancelled`, `ohmylms:membership-cancel-error` | `subscriptionId`, plus `message` on error |
| `ohmylms:catalog-before-navigate` (cancelable, on the catalogue root) | `url`, `filters`, `reason` (`filter`, `sort`, `search`, `submit`, `clear`, `category`, `more`) |
| `ohmylms:catalog-navigated` | `url`, `filters`, `reason` (after the result regions are swapped) |

The `before` events are cancelable. Call `event.preventDefault()` to block that UI action. They do
not replace server permissions, validation or audit hooks. Presentation events are not proof that
data was saved; subscribe to existing PHP grading/reward hooks for authoritative records.

Use `ohmylms_render_slot` / `ohmylms_render_slot` action for `student.quiz.before`, `student.quiz.after`,
`student.practice.before`, `student.practice.after`, and `student.practice.stats`. Context includes
`quizId`, plus `attemptId` for quiz slots. Do not nest forms inside the quiz form.

Filters: `ohmylms_quiz_interactivity_context($context, $api_version)` and
`ohmylms_smartscore_interactivity_context($context, $quiz_id)` add presentation context. Reserve core
fields and put add-on state under an add-on key. `ohmylms_template_html($html, $template_name, $args)`
is a trusted HTML decoration boundary; preserve existing directives and namespaces.

Treat undocumented store fields/actions as internal. Version 1 callbacks/events/slots remain stable;
breaking contracts require a new API version and migration notes.

## Commerce

`OhMyLMS\Extensions\Commerce` decorates trusted templates through `ohmylms_template_html`: every
`.add_to_cart_button` / `.add-to-cart-using-point-button` link becomes an `ohmylms/cart` island (the
link still works without JavaScript), `checkout/form-checkout.php` gets one `ohmylms/checkout` root on
`.ohmylms-checkout-form-outer` with delegated handlers (the order review is replaced by server
fragments), and filled fields render folded. Theme template overrides keep working because only class
names are matched. The buy-now shortcode, block, Elementor widget and Bricks element all go through
`ShortCodeBuyNow`, which calls `Commerce::cart_buttons()`.

Endpoints: `POST ohmylms-commerce/v1/cart/items` (`course_id` or `membership_id`, `quantity`,
`purchase_by: point`) and `GET ohmylms-commerce/v1/countries/{code}/states`, called with `apiFetch()`
from `ohmylms/interactivity`. Coupons, tax and order submission keep their admin-ajax actions
(`ohmylms_apply_coupon`, `ohmylms_remove_coupon`, `ohmylms_calculate_tax`, `ohmylms_checkout`) through
`ajaxPost()`, because they return server-rendered fragments and gateways rely on `wp_doing_ajax()`.
The server computes prices, tax, coupons and validation; the modules only display its answers.

Payment gateways register with `window.registerPaymentGatewayHandler( id, handler )` (or
`registerGateway` exported by `ohmylms/checkout`). Classic scripts depend on the script-less
`ohmylms-checkout` handle, which defines the registry before the module runs. Hooks, all optional:
`select( form )`, `prepare( form )` (resolve extra POST fields, throw to abort with a message),
`handle( response, form )` (resolve a replacement answer or `false` when handled), and the legacy
`resume( $form )` / `open( response, $form )`, which receive a jQuery object when jQuery is loaded.
The legacy jQuery body events `course_adding_to_cart`, `course_added_to_cart` and
`payment_method_selected` are still triggered when jQuery is on the page.

## Shared helpers and page chrome

`ohmylms/interactivity` (`assets/interactivity/sdk.js`) is the shared learner toolkit, with no dependencies.
Besides the question API above it exports `request()` (REST or admin-ajax, never rejects, errors normalised to
`{ code, message, status }`, `AbortSignal` and `timeout` support, one automatic REST nonce renewal), `toast()` /
`dismissToast()`, `confirmDialog()` (native `<dialog>`), `trapFocus()`, `focusable()`, `announce()`,
`serializeForm()`, `toSearchParams()`, `debounce()`, `prefersReducedMotion()`, `jqueryTrigger()` and
`getConfig()`. `apiFetch()` and `ajaxPost()` keep their contracts and are built on `request()`. Signatures and
rules for the learner batches are in [LEARNER-FRONTEND](LEARNER-FRONTEND.md#shared-helper-api-l2).

The SDK reads `{ restRoot, restNonce, ajaxUrl, messages }` from WordPress's script module data
(`script_module_data_ohmylms/interactivity`, filled by `OhMyLMS\Extensions\Chrome::sdk_data()`), so a page
needs no `wp_localize_script` global. Add-ons can raise notices without importing anything:

```js
document.dispatchEvent( new CustomEvent( 'ohmylms:toast', {
  cancelable: true,
  detail: { message: 'Saved.', type: 'success' },
} ) );
```

`OhMyLMS\Extensions\Chrome` adds the `ohmylms/ui` and `ohmylms/forms` directives through `ohmylms_template_html`
(priority 15) by class name, so theme overrides of `global/toast.php`, `global/main-header.php`,
`global/scrollto-top.php`, `global/form-login.php`, `global/form-signup.php` and `profile/profile-layout.php`
copied before this change keep their behaviour. Only the first rendered toast region is output; pages that load
the legacy `ohmylms-frontend` script without one get a region in `wp_footer`. PHP that renders a store calling
`toast()` on other pages calls `Chrome::enqueue_ui()`.

## Login, sign-up and reviews

`global/form-login.php` and `global/form-signup.php` are `ohmylms/forms` roots with `data-wp-on--submit`. The store
posts the form's own fields (nonce, fields added on `ohmylms_login_form` / `ohmylms_signup_form`, captcha fields) to
the `ohmylms_login` / `ohmylms_signup` admin-ajax actions with `request()`, shows errors as text in the form's
`role="alert"` region, keeps the button disabled while it waits, follows a same-origin `redirect_url`, and shows the
`.ohmylms-email-pending-notice` island for `pending_verification`. Without JavaScript the form posts to its own page
and `OhMyLMS\Extensions\AuthForms::handle_post()` runs the same server code (`Ajax::login_response()` /
`Ajax::signup_response()`). Authentication stays in `wp_signon()`, so plugins on the `authenticate` filter (captcha,
rate limits) keep working; `redirect_to` is limited to the site by `wp_validate_redirect()`. Add-ons that add fields
need no script: the store sends every named field. Listen to `ohmylms:auth-before-submit` (cancelable) to block a
submission, for example until a captcha is solved.

The review form (`single-course/tabs/reviews.php`) is an `ohmylms/reviews` root around WordPress's comment form. The
stars are radio inputs named `rating` (read by `CourseComment::add_comment_rating()`), so the form works without
JavaScript; the store keeps the `.selected` / `.active` classes that theme CSS used, shows the required notice and
prevents double submission. `OhMyLMS\Extensions\AuthForms` and `OhMyLMS\Extensions\Reviews` decorate theme overrides
copied before L5 through `ohmylms_template_html` (priority 16): forms get the submit and switch directives and a
context, and old `<a data-rating>` stars get `actions.pick`, which still fills `select#rating`.

## Learner account

`profile/profile-edit.php`, `profile/loop/membership-card.php`, `profile/membership.php` and
`profile/invoice-details.php` carry `ohmylms/account` islands; `profile/my-courses.php` is an `ohmylms/tabs` root.
`OhMyLMS\Extensions\Account` adds the same directives to theme overrides copied before L4 (`ohmylms_template_html`,
priority 15), loads the module and puts the page's nonces in `wp_interactivity_state( 'ohmylms/account' )`. The
uploads, cover removal and cancellation post to their existing admin-ajax actions with `request()`
(`ohmylms_student_cover_image_upload`, `ohmylms_student_profile_image_upload`, `ohmylms_student_cover_image_delete`,
`ohmylms_cancel_membership`); cancellation sends only `subscription_id` and `order_id` after `confirmDialog()`, and the
server decides whose subscription it is. The profile, password and notification forms still post without JavaScript;
"View plan" opens its dialog through `:target` when the module is not running. Listen to
`ohmylms:account-before-upload` or `ohmylms:membership-before-cancel` (both cancelable) to add checks.

## Course catalogue

The course archive (`archive-course.php`) and the course list shortcode, which the block and the Elementor, Bricks and
WPBakery elements render through, are `ohmylms/catalog` roots. Search, filters, sort, curriculum buttons and load
more are GET parameters (`course_search`, `course_curriculum[]`, `course_bundle[]`, `course_level[]`,
`course_price[]`, `course_sort`, `paged`; the full contract is in
[LEARNER-FRONTEND](LEARNER-FRONTEND.md#l3-catalogue-search-filter-sort-and-load-more-done)), and
`OhMyLMS\Extensions\Catalog` applies them on the server: to the archive's main query (`pre_get_posts`) and to the
shortcode's query. The controls are real fields of a GET form, so everything works without JavaScript. With it, the
store builds the same URL and `@wordpress/interactivity-router` (imported on the first navigation) swaps two regions,
`ohmylms/catalog-results` (cards, carousels, popups, a `role="status"` count and a focusable results heading) and
`ohmylms/catalog-more` (the load-more area). Interactive markup inside the regions, such as `ohmylms/carousel` and
`ohmylms/cart`, hydrates after each swap. Back/Forward work and every list is linkable.

`Catalog::template()` (`ohmylms_template_html`, priority 25) converts theme overrides of the filter and load-more
templates copied before L3: legacy field names become the URL contract, controls get their state, links and buttons
their actions. An `archive-course.php` override without the regions still filters, by loading the URL in full.
`Ajax::loadmore()` and `Ajax::search_filter()` remain for third-party callers and use the same query builder,
`Catalog::query_args()`. Listen to `ohmylms:catalog-before-navigate` (cancelable) to veto or track a change.

## Verification

```sh
npm run lint
npx playwright test --config tests/interactivity.config.cjs
php tests/php/custom-question-registry.php
OHMYLMS_CHROME_TEST=1 php tests/php/learner-chrome-integration.php
OHMYLMS_AUTH_REVIEWS_TEST=1 php tests/php/auth-reviews-integration.php
OHMYLMS_ACCOUNT_FRONTEND_TEST=1 php tests/php/account-frontend-integration.php
OHMYLMS_CATALOG_TEST=1 php tests/php/catalog-integration.php
php tests/unit.php
php ../ohmylms-smartscore/tests/engine-test.php
```

Set `OHMYLMS_PHP_BIN` to the PHP executable if it is not on PATH. The browser fixture uses the actual
PHP templates and installed WordPress Interactivity module, with mocked data/REST responses, a
localhost-only PHP server and no database bootstrap. It does not verify full site/theme integration.
Full authenticated database browser acceptance still uses `OHMYLMS_TEST_CREDENTIALS` as described
in DEVELOPMENT.md. No grading schema, live enrollment or payment data is migrated by this change.
