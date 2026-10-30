# Extension API version 1

Skills is a core feature with no Add-ons switch. The skill library (catalogue, prerequisites and lesson/course links) is the Skills tab of the Content Hub (`admin.php?page=ohmylms-content&tab=skills`; the old `#/extensions/skills` address opens it). See [CONTENT-HUB.md](CONTENT-HUB.md).

Feature code can live in `modules/<id>/` or a separate plugin. Internal modules load only via `OHMYLMS_ENABLED_MODULES` or `ohmylms_enabled_modules`. The examples module is disabled by default.

Communities is not part of OhMyLMS. A Community add-on keeps the course `has_community` flag, the
Integrations switch and the manual-enrolment hook, and adds its own screen with `registerAdminPage`.

## Load and register

Register PHP definitions on `ohmylms_register_extensions`. Enqueue editor scripts on `ohmylms_enqueue_extension_scripts`, depending on its SDK handle argument. Those scripts load before the admin app. The action fires only where extension surfaces exist: the LMS admin application, the native block editors (with their own bundle handle) and frontend pages that render a slot. Other wp-admin screens and ordinary frontend pages do not load the SDK or your editor scripts.

```php
add_action('ohmylms_enqueue_extension_scripts', function ($sdk) {
    wp_enqueue_script('my-lms-extension', plugins_url('editor.js', __FILE__), [$sdk], '1', true);
});
```

Use `window.ohmylms.extensions` (`version === 1`) after that dependency loads, or listen for `ohmylms:extensions-ready`. The SDK currently requires the opt-in source build. Register pages and editor types before app bootstrap; late page/menu registration is unsupported. Slots update reactively.

All registration methods accept `(id, definition)` and return an unregister function. Definitions require `label` and React `render`. IDs are lowercase slugs, unique within each category; prefix yours with your plugin name. Optional fields: `apiVersion: 1`, finite `priority` (default 10), `enabled`, `slot`, `when(context)`. Order is priority then ID. Duplicate IDs/unsupported versions throw; disabled entries do not render.

| Method | Surface |
| --- | --- |
| registerAdminPage | Admin screen `/extensions/<id>`, shown as tab `ext-<id>` (below) |
| registerEditorPanel | Existing route path in `slot`; context has route/hash/props |
| registerQuestionEditor | Matching PHP question ID; existing authoring store |
| registerLessonEditor | Matching PHP lesson ID; existing save callbacks |
| registerSlot | Named insertion point |
| registerMembershipSettings | Namespaced plan values and update callback |
| registerCheckoutField | Checkout UI guidance; register server fields separately |
| registerIntegration | Integrations screen panel |

Admin pages: `registerAdminPage( id, { label, render, group } )` adds a tab `ext-<id>` to one of the OhMyLMS admin
pages, at `admin.php?page=<page>&tab=ext-<id>`. The optional `group` is `content`, `accounts`, `commerce`,
`engagement` or `settings` (the default; also used for an unknown value). Pages registered without `group` keep
working unchanged. Old addresses (`admin.php?page=ohmylms#/extensions/<id>`) still open the page. A PHP add-on
can make its own links open the right page directly with the `ohmylms_extension_admin_page_group` filter
(`$group, $id`). Inside the app, `context.route` stays the original route pattern (`/quiz-edit/:id`, `/accounthub`,
…) and `context.hash` the screen's route as the hash router had it (`#/quizzes/5`), so editor-panel slot names and
`when( context )` checks written for v1 keep matching; do not parse `window.location` for the route. Move within
the app with links to app routes (`/courses/12`), not by building `admin.php` addresses yourself.

Read `modules/examples/editor.js` and the host components in `assets/src/app/extensions/` (`ExtensionSlot.tsx`, `ExtensionBoundary.tsx`, `host.ts`) for exact render props. `list`, `get`, `subscribe` and `getRevision` support custom consumers. `renderSlot(name,context,kind)` returns React; `mount(element,name,context,kind)` mounts outside admin. Rendering failures show a per-extension alert and emit `ohmylms:extension-error`. Async/event handlers and trusted PHP callbacks handle their own errors.

## Slots and settings

Named slots: `admin.screen.before`, `admin.screen.after`, `student.course.before`, `student.course.after`, `checkout.fields.after`, `checkout.summary.after`, and `syllabus.skill.resources`, which the syllabus editor renders in a skill's pane under what the skill owns (lessons and questions; context `{skill, syllabus}`), so a module such as practice games can add its own section for a skill. Editor-panel slots use actual route paths listed in the recovery manifest. PHP `ohmylms_render_slot($name,$context)` also emits a server-rendering hook. Context must be public, JSON-safe data.

```php
ohmylms_register_extension_settings('my-benefit', [
    'contexts' => ['membership'],
    'schema' => ['type'=>'object', 'properties'=>[
        'label'=>['type'=>'string', 'maxLength'=>200]
    ]]
]);
```

Supported contexts: course, lesson, quiz, question, membership, assignment, certificate, session. Root unknown keys are rejected; constrain nested objects explicitly. Core single-object authoring endpoints accept `extension_settings: {"my-benefit":{"label":"..."}}`. Invalid settings reject before core fields save. Omitted namespaces are retained. GET exposes settings only to editors.

GET/PUT `/ohmylms/v1/extension-settings/<type>/<id>` accepts `{settings:{...}}`, requires `edit_post` and exact post type. The SDK `api` uses WordPress apiFetch/nonce middleware. `/ohmylms/v1/extensions` exposes definitions, not saved private data. Client visibility is not authorization.

## Question/lesson lifecycle

In the new admin app the built-in question editors (numerical, structured and the interactive types) register after extension scripts and skip IDs that are already registered, so an extension may supply the editor of a built-in type there; the existing admin registers its own first.

`ohmylms_register_question_type` requires label, render, validate and grade callbacks. Optional `editor.schema` validates saved settings; optional `validate_settings($settings)` returns `true` or an error message for checks a schema cannot express. JS editor IDs must match server manifest IDs. Updates go through the existing question store/save API.

Versioned assessments grade frozen question versions. A type joins them by declaring `'snapshot' => true`, promising that `grade()` and `validate()` read only the question object they receive (`get_settings()`, `get_questions()`), which may be an `OhMyLMS\Assessment\QuestionSnapshot`. Quizzes containing a type without the flag cannot be published (`quiz_type_unversioned`) instead of silently grading current data. Types whose answers are option IDs should declare `'answers' => 'options'` (versioned deliveries send opaque per-attempt option tokens). Learner-facing settings must be listed in `'public_settings'`; all other settings are withheld from delivery markup. `grade()` may return `parts => [part_id => fraction|null]` for multi-part questions.

`validate($answer,$question)` returns bool and should accept empty answer shape for timeouts; required-answer enforcement is separate. `grade` returns `correct`, finite `fraction` (clamped 0..1), and `manual`. The server checks enrollment, ownership, timer, question membership and repeated submissions. Scores are server-derived: attempts started before the decimal migration award whole points; later attempts award marks × fraction to four decimal places.

The learner result screen shows a readable "Your answer" and "Correct answer" for each question. A type that stores answers by ID can supply them with the optional `review_given($given,$settings)` and `review_expected($settings)` callbacks, each returning a list of strings built only from the settings passed in (so frozen versions review correctly). Without them the generic lines are used; the built-in ID-based types (categorize, dropdown and multi-blank, hot-text, match grid, labeling, hotspot, interactive video, graphing) already provide theirs through `AssessmentReviewLines`.

`ohmylms_register_lesson_type` requires label/render. Its matching JS editor uses supplied authoring callbacks. The example persists normal WordPress content. Existing `ohmylms_register_layout` and `ohmylms_register_activity` remain; layouts accept course/chapter/lesson/quiz contexts. Avoid recursively invoking a layout hook from its renderer.

## Checkout/integrations

`ohmylms_register_checkout_field` accepts label, type (text/textarea/email/number/select), required and scalar JSON schema. It adds `ohmylms_extension_<id>` to billing, validates data and persists `_ohmylms_extension_fields` after the order gets its ID. JS registration alone is not a trusted server field.

Prices, coupons, payment verification and enrollment remain server-side. Integration panels need permission-checked endpoints; never expose provider secrets. Examples demonstrate every category without core edits. Disabling a module hides UI but retains saved metadata. A quiz with an unavailable question extension fails safely.
