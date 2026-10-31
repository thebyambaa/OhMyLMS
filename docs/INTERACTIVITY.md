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

Admin editors continue to use React. Static reports/leaderboards need no reactive runtime. Media
players, payment provider SDKs other than Stripe, uploads, course filtering, account forms, layout-3 scroll
navigation and remaining legacy presentation controls retain their existing integrations. Use the
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
`ohmylms/tabs`, `ohmylms/curriculum`, `ohmylms/reveal`, `ohmylms/cart`, `ohmylms/checkout` and
`ohmylms/stripe`.

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

## Verification

```sh
npm run lint
npx playwright test --config tests/interactivity.config.cjs
php tests/php/custom-question-registry.php
php tests/unit.php
php ../ohmylms-smartscore/tests/engine-test.php
```

Set `OHMYLMS_PHP_BIN` to the PHP executable if it is not on PATH. The browser fixture uses the actual
PHP templates and installed WordPress Interactivity module, with mocked data/REST responses, a
localhost-only PHP server and no database bootstrap. It does not verify full site/theme integration.
Full authenticated database browser acceptance still uses `OHMYLMS_TEST_CREDENTIALS` as described
in DEVELOPMENT.md. No grading schema, live enrollment or payment data is migrated by this change.
