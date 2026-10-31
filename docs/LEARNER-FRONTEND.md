# Learner front end: script map and Interactivity migration plan

Status: batch L1 (foundation and map), batch L2 (shared helpers and page chrome), batch L3 (course
catalogue), batch L4 (learner dashboard and profile) and batch L5 (login, sign-up and reviews) done on
31 October 2026. L3 to L7 build on the [shared helper API](#shared-helper-api-l2) below. Roadmap item 6 in
[MODULARIZATION-ROADMAP](MODULARIZATION-ROADMAP.md).

The admin is the typed app. The learner side still runs three jQuery scripts (`ohmylms.js`,
`video-player.js`, `video-progress-tracker.js`) next to vanilla scripts in `assets/js` and the
Interactivity modules in `assets/interactivity` ([INTERACTIVITY](INTERACTIVITY.md)). Carousels (Slick),
commerce scripts and the exam paper are migrated by their own owners and are only mentioned here where
`ohmylms.js` touches them.

## How learner scripts are served

| Handle | Canonical source (the only copy) | Served from | Build step |
| --- | --- | --- | --- |
| `ohmylms-frontend` | `assets/src/frontend/js/ohmylms.js` | the same file, in every mode | none |
| `ohmylms-video-player` | `assets/src/frontend/js/lesson/video-player.js` | the same file, in every mode | none |
| `ohmylms-video-progress-tracker` | `assets/src/frontend/js/video-progress-tracker.js` | the same file, in every mode | none |

`Assets\FrontendAssets::LEARNER_SCRIPTS` maps the handles to these paths and
`FrontendAssets::learner_script_version()` versions them as `OHMYLMS_VERSION-<filemtime>`, so an edit is
picked up without a version bump. `BlocksManager` (block editor) and `ElementorManager` register
`ohmylms.js` through the same constant. `Extensions\Interactivity::legacy_url()` still rewrites the removed
`assets/dist/frontend/{ohmylms,video-player,video-progress-tracker}.js` URLs (and their `build/` twins) to the
canonical files for any add-on or theme that hard-coded them.

What it was before L1:

- Each script existed twice: the authored file in `assets/src/frontend/js/` and a prettified reprint of the
  original webpack/Babel output in `assets/src/runtimes/dist/frontend/`. `assets/src/manifest.json` mapped
  the reprints to `assets/dist/frontend/*.js`; `tools/build-assets.mjs` regenerated those only into
  `build/`, and `tools/sync-admin-assets.mjs` never copied them back, so the checked-in
  `assets/dist/frontend/*.js` were the untouched original minified bundles.
- Production loaded `assets/dist/frontend/video-*.js`; source mode (`OHMYLMS_SOURCE_ASSETS`, through
  `Extensions\SourceAssets::url()`) loaded the `build/` reprints. Neither contained the authored changes, so the
  lesson's own "watch N %" threshold and the "lesson completed by the video" update in
  `video-progress-tracker.js` (commit f53f82e0) never ran in the browser. They run now.
- `ohmylms.js` was already rewritten to the authored file by `Interactivity::legacy_url()` in both modes.
- A string-literal diff of each pair (Babel helpers excluded) showed the authored files are a superset of the
  reprints, so the reprints and the minified bundles were deleted rather than merged.

Block editor scripts: `assets/src/runtimes/blocks/js/*.js` are copied by the manifest to `assets/blocks/js/`.
`dashboard` and `profile` now always load the unminified file (`BlocksManager::enqueue_individual_block_scripts()`
used `.min.js` unless `SCRIPT_DEBUG`); the string sets of each `.js`/`.min.js` pair were identical. The
commerce owner handles the checkout, buy-now, offer-button and membership-list pairs.

## Removed in L1

Files (all listed in `retiredAssets` where they were manifest outputs, so the next build and sync delete any
stale copy):

| File | Bytes | Evidence |
| --- | --- | --- |
| `assets/src/runtimes/dist/frontend/ohmylms.js` | 122,264 | Reprint of the original bundle; never served (the authored file was) |
| `assets/src/runtimes/dist/frontend/video-player.js` | 66,256 | Reprint; superseded by the authored file |
| `assets/src/runtimes/dist/frontend/video-progress-tracker.js` | 28,019 | Reprint; missing the f53f82e0 changes |
| `assets/dist/frontend/ohmylms.js` + `.map` | 131,241 + 241,794 | Original bundle, rewritten away by `legacy_url()` |
| `assets/dist/frontend/video-player.js` | 42,996 | Original bundle |
| `assets/dist/frontend/video-progress-tracker.js` | 16,732 | Original bundle |
| `assets/src/runtimes/blocks/js/blocks.min.js`, `assets/blocks/js/blocks.min.js` | 1,104 + 887 | `rg "blocks\.min"` finds only the manifest; nothing enqueues it |
| `assets/src/runtimes/blocks/js/dashboard.min.js`, `assets/blocks/js/dashboard.min.js` | 16,445 + 9,098 | Same strings as `dashboard.js`, now always loaded |
| `assets/src/runtimes/blocks/js/profile.min.js`, `assets/blocks/js/profile.min.js` | 33,651 + 18,111 | Same strings as `profile.js`, now always loaded |
| `assets/src/runtimes/blocks/js/blocks-frontend.js`, `assets/blocks/js/blocks-frontend.js` | 254 + 248 | An empty `jQuery(document).ready()` callback; its enqueue in `BlocksManager::enqueue_frontend_assets()` only pulled jQuery onto block pages. The `ohmylms-blocks-frontend` style handle stays |
| `includes/Assets/packages/html2pdf.bundle.min.js` | 942,854 | `rg html2pdf` finds only docs; certificates are rendered by `Certificates\Download` |
| `templates/page-template/ohmylms-certificate.php` | 103 | Only `CommonHook::load_custom_template()` loaded it, for `?ohmylms-certificate-data`, which `Certificates\Download::route()` redirects on `template_redirect` (priority 1) before `template_include` runs |

Code:

- `CommonHook::load_custom_template()`: the `?ohmylms-certificate-data` branch. The redirect in
  `Certificates\Download::route()` is unchanged and still tested by `issued-certificates-integration.php`.
- `CommonHook::register_custom_template()`: the "Certificate template" page template. No template file or
  `template_include` branch handled the slug and no page uses it (`_wp_page_template` query on the local site).
- `ohmylms.js` went from 111,887 to 72,682 bytes. Each removed block, with the evidence (`rg` over `templates/`,
  `includes/`, `assets/src/blocks`, `assets/interactivity` and `assets/js`; "no markup" means no hit):

| Removed from `ohmylms.js` | Evidence |
| --- | --- |
| FAQ and assignment accordions (`.ohmylms-single-course-faq`, `.ohmylms-assignment-accordion`) | No markup. The single course page renders `student-hub/course.php` |
| Course layout 1/2 chapters: `courseContentReadMore()`, `.ohmylms-chapter-layout-1 … .readmore`, `checkAllChapterActive()`, `.ohmylms-chapter-layout-2` title/expand/collapse/show-more | No markup for `chapter-layout`, `chapter-expand`, `show-more-chapter` |
| Course detail tabs and their Slick tab carousel (`.ohmylms-course-tab-nav`, `.ohmylms-course-details-tab`) | No markup; the public `ohmylms/tabs` store remains for add-ons |
| Sticky price box (`.ohmylms-course-header .ohmylms-widget-pricebox`) | No `.ohmylms-course-header` markup; `single-course/widgets/pricebox.php` is not included by any template |
| `ohmylms-header .ohmylms-user .ohmylms-user-dropdown` handler | Selector typo (no leading dot) never matched |
| Mobile header search (`.ohmylms-mobile-search-btn`, `.ohmylms-search-close`) | Only inside an HTML comment in `global/main-header.php` |
| Password strength meter (`#ohmylms-password-meter`) | No element with that id; the `.ohmylms-password-strength` markup is commented out in `profile/profile-edit.php` and `profile/settings.php` |
| `.ohmylms-student-profile-sidebar-close` | No markup |
| Drop course (`.ohmylms-drop-course-confirm`, `.ohmylms-drop-course`) and its duplicate `.ohmylms-alert` close handlers | No markup (only SCSS). The identical alert handlers for the membership cancel dialog remain |
| Quiz exit dialog, next/previous (single and grouped), submit validation and quiz timer start | `single-lesson/quiz-form.php` is the only template with these controls and always returns through `Interactivity::quiz()`, which binds them to the `ohmylms/quiz` store; its `next`, `previous`, `openExit`, `closeExit` and `submitClick` actions call `stopPropagation()`, so the delegated jQuery handlers never ran. The timer start was already skipped inside `[data-wp-interactive="ohmylms/quiz"]` |
| Lesson sidebar drawer (`.ohmylms-lesson-details-hamburger`, body click) | Its only template, `single-lesson/lesson-content.php`, drives it with `ohmylms/ui` (`openSidebar`, `outside`, `escape`) |
| Assignment feedback "Read more" (`.score-comment-details-text .read-more-toggle`) | No markup |
| Review "show more" (`.ohmylms-course-reviews .show-more-review`) | No markup |
| Course layout 2 header overlay (`.ohmylms-single-course-layout-2`) | No markup |
| `checkLessonPrerequisite()` and its commented-out caller | Never called |
| Course layout 3 section nav, scroll spy, "Show more" toggles and sticky price bar (`.ohmylms-single-course-layout3…`, `.layout3-*`) | No markup; three window `scroll` handlers ran on every page for nothing |

Nothing removed called an AJAX action without a PHP handler: every `action` in the learner scripts has a
`wp_ajax_*` registration in `includes/Ajax.php`. Two handlers now have no in-repo caller and were kept because
admin-ajax actions are reachable by third parties: `ohmylms_drop_course` (nonce `course_drop_nonce`) and the
public `ohmylms_lesson_access_nonce` (nonce `lesson_access_nonce`). L4 removed both, with their localized nonces:
nothing in the plugin, its templates, built assets or the other plugin on the site (`ohmylms-graded-reader`) calls them.

## Behaviour map

Columns: markup source (PHP that renders the selectors), server call (AJAX action → `includes/Ajax.php` method,
or REST route), localized data, jQuery. "Superseded" means an Interactivity store already handles the same
markup and the jQuery copy is a fallback for markup rendered outside `ohmylms_get_template()` (whose
`ohmylms_template_html` filter lets `Interactivity::disclosures()` add `ohmylms/ui` directives).

### `ohmylms.js` (handle `ohmylms-frontend`, global `ohmylms_frontend_params`, needs jQuery and `wp-i18n`)

Enqueued by `FrontendAssets::load_scripts()` on every OhMyLMS page (`is_ohmylms()`), archive, shortcode,
dashboard, checkout and buy-now page, and directly by `Exams\Player`, `Student\Hub`, the quiz-player block,
previews, and Elementor/Bricks widgets.

| Behaviour | Selectors | Markup source | Server call | Status |
| --- | --- | --- | --- | --- |
| Load more courses | `button.ohmylms-course-loadmore-btn` (`data-total`, `data-posts-per-page`), appends to `.ohmylms-course-cards` | `loop/load-more.php` | `ohmylms_loadmore` → `Ajax::loadmore()` (nonce `load_more_nonce`), returns HTML | **Migrated (L3)** to `ohmylms/catalog` (`?paged=N`, router region); the action is kept for third parties |
| Filter, search, sort, category | `.ohmylms-checkbox input[name="ohmylms-filter-checkbox"]` (`data-type`, `data-slug`), `.clear-filter`, `input[name="course-search"]` (every keyup, no debounce), `.course-sort`, `.ohmylms-category-filter` | `filters/filters.php`, `filter-header.php`, `course-search.php`, `course-sort.php`, `search-sort.php`, `category-type-button.php` | `ohmylms_search_filter` → `Ajax::search_filter()` (nonce `search_filter_nonce`), returns HTML; the category branch re-creates the carousel (carousel owner) | **Migrated (L3)**: GET parameters rendered by the server, swapped by the router; the action is kept for third parties |
| Filter checkbox keyboard, category button active state | `.ohmylms-filter-accordion .checkedbox` (Enter), `.ohmylms-category-type-button li` | `filters/filters.php`, `filters/category-type-button.php` | — | **Migrated (L3)** (`actions.keydown`, `state.categoryActive`) |
| Filter drawer on mobile | `.filter-hamburger`, `.ohmylms-course-sidebar`, `.ohmylms-close-filter` | `filters/search-sort.php`, `filters/filter-header.php` | — | **Migrated (L3)** to the `ohmylms/ui` drawer |
| Course card hover popup (grid style 3) | `.grid-style3 .ohmylms-course-cards-carousel .course-card`, `.ohmylms-course-card-popup[data-item-id]` | `course-popup-content.php`, `layouts/grid-style3.php` | — | **Migrated (L3)** (delegated `actions.cardOver`/`cardOut`, so cards from later navigations get it too) |
| Archive carousels | `.ohmylms-course-cards-carousel` | `loop/loop-start.php` | — | Carousel owner (Slick replacement) |
| Accordions | `.ohmylms-filter-accordion`, `.ohmylms-lesson-sidebar-accordion` `.ohmylms-accordion-head` (+ keyboard) | `filters/filters.php`, `single-lesson/sidebar/sidebar.php` | — | Superseded by `ohmylms/ui` when rendered by `ohmylms_get_template()` |
| Responsive tables | `.ohmylms-table .ohmylms-td-handle` (bound only if the window is under 768 px at load), `.dashboard-table-tr .table-accordion-handler` | `single-lesson/content-quiz.php`; `profile/membership.php`, `membership-details.php`, `invoice-details.php`, `transactions-history.php` | — | Superseded by `ohmylms/ui` (same rule). The `.dashboard-table-tr` jQuery copy was **removed in L4** (every profile table is rendered by `ohmylms_get_template()`); the quiz `.ohmylms-td-handle` copy stays for L6 |
| Lesson sidebar tabs | `.ohmylms-lesson-tab-nav li`, `.ohmylms-lesson-single-tab-content` | `single-lesson/sidebar/sidebar.php` | — | Live (vanilla DOM) |
| My courses tabs | `.ohmylms-my-courses-tab ul li button` (arrow keys) | `profile/my-courses.php`, `Shortcodes\ShortCodeMyCourses` | — | **Migrated (L4)** to the public `ohmylms/tabs` store (arrow keys, Home, End) |
| Toast | `#ohmylms-toast`, `.ohmylms-message`, `#ohmylms-close-toast` | `global/toast.php` | — | **Migrated (L2)** to `ohmylms/ui`. The remaining jQuery callers (login, sign-up, lesson complete, assignment) go through a small shim that dispatches `ohmylms:toast` |
| Header avatar dropdown | `.ohmylms-header .ohmylms-user-avatar`, body click closes | `global/main-header.php` | — | **Migrated (L2)** to `ohmylms/ui` dropdown actions |
| Profile sidebar drawer | `.ohmylms-student-profile .ohmylms-hamburger`, `.ohmylms-student-profile-sidebar` | `profile/profile-layout.php`, `profile/navigation.php`, `Shortcodes\ShortCodeProfile`, `student-hub/workspace.php` | — | **Migrated (L2)** to `ohmylms/ui` drawer actions (the hub's legacy wrapper has no toggle and stays static) |
| Cover and profile photo | `#cover_image_input`, `#cover-image-delete`, `#profile_photo_input`, `.cover-edit`/`.edit-avatar` (keyboard) | `profile/profile-edit.php` | `ohmylms_student_cover_image_upload`, `ohmylms_student_cover_image_delete`, `ohmylms_student_profile_image_upload` → `Ajax::student_cover_image_upload/delete()`, `Ajax::student_profile_image_upload()` (nonces `student_profile_cover_*`, `student_profile_image_upload_nonce`, localized only on the profile shortcode and the hub) | **Migrated (L4)** to `ohmylms/account`; the nonces are in its state |
| Notification switches | `.single-notification-settings .ohmylms-switcher input` (value on/off) | `profile/notifications.php` | form post | **Removed (L4)**: the switches post `value="on"` (the server stores "off" when a box is missing), so no script is needed |
| Membership "View plan" dialog | `.ohmylms-dashboard-membership .view-plan`, `.ohmylms-membership-modal`, `.close-modal`, Escape | `profile/loop/membership-card.php` (from `profile/student-dashboard.php`) | — | **Fixed and migrated (L4)**: the selector never matched (the card sits in `section.oml-dashboard-card`, not `.ohmylms-dashboard-membership`); each `.ohmylms-dashboard-single-membership` card is now an `ohmylms/account` island |
| Cancel membership | `.do-membership-cancel` → `.ohmylms-alert`; `.ohmylms-cancel-membership` (`data-subscription-id`, `data-membership-id`, `data-order-id`) | `profile/membership.php` | `ohmylms_cancel_membership` → `Ajax::cancel_membership()` (nonce `cancel_membership_nonce`), then reload | **Migrated (L4)**: `confirmDialog()`, then only `subscription_id` and `order_id` |
| Invoice print | `.ohmylms-membership-invoice .print-button` (replaces `<body>`, prints, reloads) | `profile/membership-details.php`, `profile/invoice-details.php` | — | **Migrated (L4)**: `@media print` CSS and `window.print()` |
| Login and signup | `.ohmylms-form-login`, `.ohmylms-form-signup` (serialized, hidden `action`), `a.ohmylms-show-signup-form`, `a.ohmylms-show-login-form`, `#ohmylms-checkout-form` hidden on switch | `global/form-login.php`, `global/form-signup.php` (through `ohmylms_login_form()`, `ohmylms_signup_form()` and the checkout variants in `Utility/template-functions.php`) | `ohmylms_login` → `Ajax::login()`, `ohmylms_signup` → `Ajax::signup()` (form nonces); 8-character client check | **Migrated (L5)** to `ohmylms/forms` (submission, inline errors, pending verification, login/sign-up switch); the forms also work without JavaScript through `Extensions\AuthForms::handle_post()` |
| Form helpers | `.ohmylms-input-text` label folding, `.show-password-icon`, `.ohmylms-form-login-rememberme` and `.ohmylms-tnc-label` (Enter), `input[name='tnc-accept']` enables submit, `input[name="phone"]`/`[name="whatsapp"]` character filter | `global/form-login.php`, `global/form-signup.php`, `profile/profile-edit.php`, `profile/settings.php`, `checkout/terms.php`, `student-hub/settings.php`, checkout shortcode/block/widgets | — | **Migrated (L2)** to `ohmylms/forms`; submission and the show-login/show-signup links followed in L5 |
| Email verification | URL params `ohmylms_email_verified`, `ohmylms_verify_sent`, `ohmylms_verify_error` → toast; `a[href*="ohmylms_resend_verification"]` | `Services\EmailVerificationService` notices | GET `ohmylms_resend_verification` with `format=json` → `Ajax::resend_verification()` | **Migrated (L2)** to `ohmylms/ui` (`installChrome()`) |
| Review stars and validation | `.comment-form-rating .ohmylms-stars > a` → `select#rating`; `.ohmylms-course-reviews-form #submit` | `single-course/tabs/reviews.php` (from `student-hub/course.php`) | comment form post | **Migrated (L5)** to `ohmylms/reviews`; the stars are now radio inputs (`name="rating"`) and `select#rating` is gone |
| Mark lesson complete | `#ohmylms-completed-lesson`, `#ohmylms-lesson-id`; `.ohmylms-lesson-navigation .ohmylms-checkbox` (Enter) | `single-lesson/content-navigation.php` | `ohmylms_lesson_completed` → `Ajax::lesson_completed()` (nonce `lesson_completed_nonce`), then next link or reload | Live |
| Assignment submission | `.ohmylms-assignment-form`, `#submission-file`, `#ohmylms-add-new-attachment`, `.remove-resource-action` (built by the script), `.ohmylms-file-upload` (keyboard), `.assignment-submit-alert` | `single-lesson/content-assignment.php` | `ohmylms_save_assignment_submission_file` → `Ajax::save_assignment_submission_file()` (nonce `assignment_submission_nonce`) | Live |
| Assignment timer and deadline | `.ohmylms-lesson-navigation .start-submit-assignment`, `input[name="ohmylms_assignment_deadline"]`, `.submission-time-limit .timer-display[data-timer]`, `.deadline` | `single-lesson/content-navigation.php`, `content-assignment.php` | `ohmylms_save_remaining_time` → `Ajax::save_remaining_time()`; at zero `ohmylms_quiz_exit_submission` (see findings) | Live |
| Quiz text fields | `.textarea-auto-resize`, `.ohmylms-has-character-limit[data-limit]` | `quiz-loop/long-text.php`, `quiz-loop/short-text.php` | — | Live, inside the `ohmylms/quiz` region; move into `ohmylms/questions` |
| Lesson video cover | `.ohmylms-video-player`, `.ohmylms-video-player-play`, `.ohmylms-video-player-cover`, `.the-video` (three overlapping handlers) | `single-lesson/content-lesson.php` | — | Live |
| Lesson content images | `.ohmylms-wysiwyg-content img[data-width][data-align]` wrapped in `.image-wrapper` | `single-lesson/content-{lesson,quiz,assignment,zoom,googlemeet}.php` | — | Live; do it on the server (`the_content` filter) |
| Prerequisite dialog close | `.ohmylms-access-denied-inner`, `.ohmylms-access-denied-close`, `.ohmylms-access-denied-modal-content` | `Hooks\CommonHook::restrict_access()`, `single-lesson.php` | — | Live |
| Content protection | whole page when `is_creator_page` and `content_protection === 'yes'` (blur on window blur, context menu, copy/cut, shortcut keys, DevTools size check, Loom element poll) | option `ohmylms_content_protection` | — | Live; deterrent only |
| Block-theme navigation fix | `.ohmylms-page.theme-twentytwenty{two,three,five} .wp-block-navigation__responsive-container-{open,close}` | core navigation block; body class from `Utility/template-functions.php` | — | **Migrated (L2)**: a delegated listener in `ohmylms/ui` that acts only when core's `core/navigation` store did not open or close the menu within a frame |
| Scroll to top | `.ohmylms-scroll-to-top button` | `global/scrollto-top.php` | — | **Migrated (L2)** to `ohmylms/ui` (passive async window scroll, smooth only without reduced motion) |

Localized data (`FrontendAssets::get_script_data( 'ohmylms-frontend' )`): `ajax_url`, `current_student_id`,
`nonce`, `load_more_nonce`, `search_filter_nonce`, `video_completion_threshold`, `is_creator_page`,
`content_protection`, `email_verification_required`; on content pages `lesson_completed_nonce`,
`assignment_submission_nonce`, `save_remaining_time`, `quiz_exit_submission`, `video_progress_nonce`.
`BlocksManager` and `ElementorManager` localize their own copies. L4 removed `lesson_access_nonce`,
`course_drop_nonce`, the three profile image nonces and `cancel_membership_nonce`: the account nonces are now in
`wp_interactivity_state( 'ohmylms/account' )`, printed only when a template that needs them renders.
`load_more_nonce` and `search_filter_nonce` are no longer used by the plugin (L3) and stay only for third-party
callers of the two catalogue admin-ajax actions.

### `lesson/video-player.js` (handle `ohmylms-video-player`, needs jQuery, content pages)

Custom controls for YouTube (iframe API), Vimeo (`player.vimeo.com/api/player.js`) and native video on
`.ohmylms-custom-video-player[data-video-url]` from `single-lesson/content-lesson.php`, `content-zoom.php` and
`content-googlemeet.php`: play/pause, progress and seek, volume, speed menu, fullscreen, keyboard shortcuts,
branding blockers, logo overlay. Globals: `window.OhMyLMSVideoPlayer`, `window.initOhMyLMSVideoPlayers`,
`window.onYouTubeIframeAPIReady`. No server calls. Live.

### `video-progress-tracker.js` (handle `ohmylms-video-progress-tracker`, needs jQuery, content pages)

Watch progress for the same players and `video.the-video`: resumes the last position, saves progress, and
completes the lesson at the threshold. Reads `[data-lesson-id]`, `[data-course-id]`,
`[data-ohmylms-watch-percent]` (`content-lesson.php`) and updates `#ohmylms-completed-lesson[data-ohmylms-video-pending]`
and `#ohmylms-video-completion-hint` (`content-navigation.php`). Calls `ohmylms_save_video_progress`,
`ohmylms_get_video_progress`, `ohmylms_clear_video_progress` → `Ajax::save/get/clear_video_progress()` (nonce
`video_progress_nonce`). Reads `ohmylms_frontend_params` (`ajax_url`, `video_progress_nonce`,
`video_completion_threshold`) although its handle depends only on `jquery`; it works because
`ohmylms-frontend` is enqueued on every content page. Live.

### Block editor scripts (`assets/src/runtimes/blocks/js`)

`dashboard.js` and `profile.js` register the `ohmylms/dashboard` and `ohmylms/profile` blocks for the editor
(inspector controls plus `ServerSideRender`); they run no learner behaviour (re-checked in L4, both the source and
the served `assets/blocks/js` copies: only `registerBlockType` and an editor `<style>`), so they stay as they are. The front-end markup they preview
is the dashboard and profile templates above. There is no `blocks.js` in that folder; the only `blocks*`
files were the unloaded `blocks.min.js` (an editor-canvas body-class helper) and the empty
`blocks-frontend.js`, both removed.

### `assets/js` learner scripts (vanilla, no jQuery)

| Script (handle) | Enqueued by | Markup and selectors | Server call | Data |
| --- | --- | --- | --- | --- |
| `practice.js` (`ohmylms-practice`) | `Practice\Frontend` | practice sessions page | REST `ohmylms/v1/practice/sessions…` | `ohmylmsPractice` (`Inline::client_config()` + i18n) |
| `inline-check.js` (`ohmylms-inline-check`) | `Practice\Inline`, `Practice\Frontend` | `form.ohmylms-inline-check` from `[ohmylms_question]` | REST `practice/inline`, `practice/guest`, `practice/claim` | `ohmylmsInlineCheck`; exposes `window.ohmylmsPracticeRequest`, `ohmylmsGuestToken`, `ohmylmsRememberPractice` |
| `quiz-autosave.js` (`ohmylms-quiz-autosave`) | `Assessment\Delivery` | `.ohmylms-quiz[data-attempt-engine="versioned"][data-autosave]` in `quiz-form.php` | REST attempt `responses` and resume URL from `data-autosave` | `ohmylmsQuizAutosave` (REST nonce, i18n) |
| `interactive-controls.js` (`ohmylms-interactive`), `interactive-visual.js`, `puzzle-loader.js` | `Assessment\Interactive` | `[data-ohmylms-interactive]` question widgets | none (answers posted with the form) | — |
| `inline-blank-drag.js` | `Assessment\InlineBlanks` | `.ohmylms-drag-blanks`, `.ohmylms-blank-token` | none | — |
| `import-figures.js` (`ohmylms-figures`) | `ExamImport\FigureContent` | `.ohmylms-import-figure[data-figure-spec]` | none | needs `ohmylms-jsxgraph` |
| `performance-note.js` | `Practice\Frontend` | `[data-ohmylms-note]` on "My skills" | `data-url` (REST `reports/skills/students/…`) | — |
| `practice-guidance.js` | `Student\PracticeGuidance` | `.oml-practice-guidance[data-guidance-endpoint]` (`student-hub/practice-guidance.php`) | REST `courses/<id>/…` | — |
| `learning-program.js` (`ohmylms-learning`) | `Learning\Frontend` | `[data-course]`, `[data-complete-placement]` | REST `courses/<id>/learning/activities/…` | `ohmylmsLearning` |
| `syllabus-directory.js` | `Curriculum\Directory` | `[data-directory]`, `[data-skill-search]` | none (local filter) | — |
| `student-hub.js` | `Student\Hub` | `.ohmylms-student-hub`, `[data-hub-*]`, `[data-family-*]` | `form.dataset.endpoint` (hub REST) | — |
| `student-dashboard.js` | `Practice\Dashboard` | `.oml-student-dashboard`, `.oml-xp-card[data-xp-endpoint]`, `[data-select-theme]` | REST `engagement/xp`, `student/dashboard-theme` | — |
| `streak.js` | `templates/profile/streak.php` | `[data-ohmylms-streak-timezone]` | REST `engagement/streak` | — |
| `player-context.js` | `Student\PlayerContext` | `.oml-player`, `[data-player-outline-toggle]` | none | — |
| `explore-menu.js` | `Student\Subjects` | `.oml-explore-menu` (`details`) | none | — |
| `ui.js` (`ohmylms-ui`) | `Design\Ui` | `[data-oml-tabs]`, `dialog[data-oml-dialog]` | none | — |

None of these is dead. Outside the learner scope: `assets/js/admin-notices.js` has no loader (only its manifest
entry); leave it to the admin owner.

## Findings to fix in the batches

- Fixed: **`Ajax::cancel_membership()` trusted the posted `student_id`, `subscription_id` and `order_id`.** It now
  derives the learner, plan and order from the subscription and refuses anyone but its owner (or an administrator);
  since L4 the client posts only `subscription_id` and `order_id`.
- Fixed in L4: the membership "View plan" dialog never opened (selector mismatch above).
- When an assignment countdown reaches zero, `ohMyLmsCountdown()` calls `saveQuizExiSubmit()`, which posts
  `ohmylms_quiz_exit_submission` with no quiz id; the server rejects it. L6 should give assignments their own
  expiry path (or none: the server already stores the deadline).
- `video-progress-tracker.js` depends on `ohmylms_frontend_params` without declaring `ohmylms-frontend`.
- The public stores `ohmylms/tabs` and `ohmylms/curriculum` had no markup in the plugin since the old single
  course layouts went; keep them (documented add-on contract). L4 drives the my-courses tabs with `ohmylms/tabs`.
- Template overrides: a theme that copied an old `single-lesson/quiz-form.php` or course layout would have
  relied on the removed handlers. Those overrides were already incompatible (the templates they include are
  gone); mention it in the release notes.

## Migration batches (lowest risk first)

Each batch replaces the jQuery code with server-rendered directives and a script module registered in
`Extensions\Interactivity::register()`, deletes the jQuery code in the same change, and keeps public markup
classes so theme CSS keeps working. Write stores as `createX( api )` factories like
`assets/interactivity/quiz-player/player.js`, so `node:test` can drive them without a browser.

### L2: shared helpers and page chrome (done)

Done on 31 October 2026. `ohmylms.js` went from 69,251 to 59,830 bytes.

| Behaviour | Now | Markup (directives written in the template; `Extensions\Chrome` adds the same ones to theme overrides and to templates not listed) |
| --- | --- | --- |
| Toast (both jQuery copies) | `ohmylms/ui`: `state.toast`, `callbacks.toastRegion`, `actions.dismissToast`, `pauseToast`, `resumeToast`, `toastKeydown` | `global/toast.php` (close is now a `<button>`; the region is `role="status"`) |
| Header account menu | `ohmylms/ui`: `actions.dropdownToggle`, `dropdownKeydown`, `dropdownOutside`, `dropdownEscape`, `dropdownFocusout` | `global/main-header.php` |
| Profile drawer | `ohmylms/ui`: `actions.drawerToggle`, `drawerKey`, `drawerOutside`, `drawerEscape` (focus trapped while open) | `profile/profile-layout.php`, `profile/navigation.php` (panel id), `Shortcodes\ShortCodeProfile` |
| Scroll to top | `ohmylms/ui`: `callbacks.trackScroll`, `actions.scrollToTop` | `global/scrollto-top.php` |
| Block-theme menu fix, verification toasts, resend link | `installChrome()` in `assets/interactivity/ui/chrome.js` (document listeners, no markup) | core navigation block; `CommonHook` notices |
| Label folding, show password, Enter/Space on checkbox labels, terms gate, phone filter | `ohmylms/forms` (`assets/interactivity/forms.js`) | `global/form-login.php`, `global/form-signup.php`; decorated: `profile/profile-edit.php`, `profile/settings.php`, `checkout/terms.php`, `student-hub/settings.php` |

Removed from `ohmylms.js`: `OhMyLMS.ohMyLMSshowToast` and the inner `ohMyLMSshowToast` (replaced by one shim
that dispatches `ohmylms:toast`), `#ohmylms-close-toast`, the `.ohmylms-user-avatar` toggle and its body click,
the `.ohmylms-hamburger` drawer with its two body/sidebar click handlers, the block-theme menu open/close handlers,
the checkout terms, remember-me and sign-up terms keydown handlers, the `tnc-accept` change handler, both phone
filter handlers, the three label-folding handlers, the show-password handler, scroll to top, the verification
URL-parameter toast and the resend-link handler. None of them triggered a jQuery event, so there is nothing to
re-dispatch; the new code emits DOM events instead (see [INTERACTIVITY](INTERACTIVITY.md#public-events-and-slots)).

Behaviour changes, all deliberate:

- One toast region per page: a second `global/toast.php` renders nothing, and pages that load `ohmylms-frontend`
  without rendering one get it in `wp_footer` (`Chrome::footer()`). Toasts queue instead of overwriting each other,
  pause on hover and focus, close on Escape, strip markup (the `.html()` copy rendered server HTML), and no longer
  keep the previous type's colour.
- Labels fold only their own row (`closest()`, not `parents()`), so the first/last name pair no longer folds together.
- The sign-up terms label is focusable; Enter and Space now go through `input.click()`, so the terms gate updates
  (setting `.checked` did not fire `change`).
- The password toggle is `role="button"` with `aria-pressed`; the sign-up password field uses
  `autocomplete="new-password"`.
- Phone fields filter on `input` only (paste and IME included) and keep the caret; there is no keydown filter.

Not done: the Playwright check with jQuery absent. The browser fixture (`tests/fixtures/interactivity.php`, used
by `tests/interactivity.config.cjs`) was already broken, so all 9 of its tests fail before reaching any store:
`/fixture` stops with `Class "OhMyLMS\Assessment\Schema" not found`, the SmartScore practice template it includes no
longer exists, and there is no route for `/quiz-player/*` (imported by `quiz.js`). L2 added the `/ui/chrome.js`
route. Repair the fixture before relying on it. Instead, L2 was checked in the live site: the toast,
`request()`, `confirmDialog()` and the phone filter were driven in the browser on the hub settings page.

Tests: `tests/js/sdk.test.mjs` (request normalisation, nonce renewal, REST to admin-ajax fallback, uploads,
abort/timeout, `apiFetch`/`ajaxPost` compatibility, encoding, toasts, focus trap, debounce),
`tests/js/interactivity-ui.test.mjs` (toast queue timing, pause, hurry and duplicates, dropdown, drawer, scroll,
verification, menu fallback, password toggle, checkbox keys, terms gate, phone filter, folding) and
`tests/php/learner-chrome-integration.php` (`OHMYLMS_CHROME_TEST=1`: rendered templates, decorated pre-L2 overrides,
module registration, SDK data, state, footer region).

## Shared helper API (L2)

Stable for L3 to L7. Add to it rather than around it; change a signature only with every caller in the same change.

### SDK: `ohmylms/interactivity` (`assets/interactivity/sdk.js`)

Import from the bare specifier, never relatively (a relative URL loads a second copy with its own config and
toast queue). The module has no imports of its own. Store factories that need it take the functions as
parameters, like `createChrome( api )`, so node tests can pass fakes.

| Export | Signature | Notes |
| --- | --- | --- |
| `request` | `request( { path?, action?, url?, method?, data?, query?, nonce?, nonceField?, restRoot?, ajaxUrl?, headers?, signal?, timeout?, parse? } ) → Promise<{ ok, status, data, message, error, raw, transport }>` | Never rejects for HTTP, WordPress or network errors (only a call without `path`/`action`/`url` throws). `error` is `null` or `{ code, message, status }`. `path` is REST with the server nonce and one automatic nonce renewal; `action` is an admin-ajax POST, using `FormData` when a value is a `Blob`, with `nonce` posted as `nonceField` (default `nonce`); `path` + `action` falls back to admin-ajax on 404/`rest_no_route` or non-JSON; `url` alone fetches that URL. `data` for `wp_send_json_*` is the unwrapped `data`; legacy `{ status, message, … }` bodies are kept whole. `parse: 'text'` returns HTML. Error codes: server `code`, `http_<status>`, `ajax_error`, `invalid_nonce` (`-1`), `invalid_action` (`0`), `invalid_json`, `network_error`, `aborted`, `timeout` |
| `toast` | `toast( message, { type?: 'success'\|'warning'\|'danger'\|'error'\|'info', duration? } ) → { id, dismiss() } \| null` | Plain text only. Duration defaults to 5000 ms (8000 for danger); `0` stays until dismissed. Dispatches the cancelable `ohmylms:toast` on `document`. Use it for transient confirmations; show form validation inline in an `aria-live` element (L5) |
| `dismissToast` | `dismissToast( id? )` | Current toast when `id` is empty |
| `confirmDialog` | `confirmDialog( message \| { message, title?, confirmLabel?, cancelLabel?, tone?: 'danger' } ) → Promise<boolean>` | Native modal `<dialog>`; Escape and backdrop cancel; `danger` focuses Cancel; focus returns to the opener. For L4's membership cancel |
| `trapFocus` | `trapFocus( container, { initialFocus?, returnFocus? = true, onEscape? } ) → release()` | For drawers and non-`<dialog>` overlays |
| `focusable` | `focusable( container ) → HTMLElement[]` | Visible, enabled tab stops |
| `announce` | `announce( message, 'polite'\|'assertive' )` | Screen-reader-only live region (result counts in L3, "saved" in L4) |
| `serializeForm` | `serializeForm( form, { exclude?, extra?, submitter?, as?: 'formdata'\|'params'\|'object' } )` | What the browser would submit; `exclude: [ 'action' ]` replaces jQuery `serializeArray().filter()` |
| `toSearchParams`, `toFormData`, `entriesToObject` | PHP-style `name[]` / `name[key]` encoding | `request()` uses them; L3 filters send arrays this way |
| `debounce` | `debounce( callback, ms ) → fn` with `.cancel()`, `.flush()` | L3 search |
| `prefersReducedMotion` | `() → boolean` | |
| `jqueryTrigger` | `jqueryTrigger( name, args?, target = document.body ) → boolean` | Re-dispatch a documented jQuery event when jQuery is loaded |
| `getConfig`, `setConfig` | `{ restRoot, restNonce, ajaxUrl, messages }` | Printed by `Chrome::sdk_data()` through WordPress's `script_module_data_ohmylms/interactivity` filter on every page with an OhMyLMS module; `messages` holds translated fallbacks (`error`, `network`, `timeout`, `confirm`, `cancel`, `dismiss`, …) |
| `apiFetch`, `ajaxPost` | Unchanged contracts (commerce) | Now thin wrappers over `request()`; prefer `request()` in new code |
| `emit`, `toastItem`, `plainText`, `normalizeResponse`, `trapIndex`, `takePendingToasts` | Helpers | `normalizeResponse` and `toastItem` are exported for tests; `takePendingToasts` is internal to `ohmylms/ui` |

Page-specific nonces (`load_more_nonce`, `lesson_completed_nonce`, …) belong in each store's
`wp_interactivity_state()`, not in the SDK config.

### Stores and markup

- `ohmylms/ui`, generic contexts any batch can reuse:
  - dropdown: root context `{ "open": false }` with `data-wp-class--<open-class>="context.open"`,
    `data-wp-on-document--click="actions.dropdownOutside"`, `data-wp-on-document--keydown="actions.dropdownEscape"`,
    `data-wp-on--focusout="actions.dropdownFocusout"`; trigger with `aria-controls`, `data-wp-bind--aria-expanded="context.open"`,
    `data-wp-on--click="actions.dropdownToggle"`, `data-wp-on--keydown="actions.dropdownKeydown"`.
  - drawer: root context `{ "drawerOpen": false, "drawerPanel": "<selector>", "drawerInside": "<panel and toggle selectors>" }`
    with `actions.drawerOutside`/`drawerEscape` on the document; toggle with `actions.drawerToggle` and
    `actions.drawerKey`. L3's mobile filter drawer is this pattern with `.ohmylms-course-sidebar,.filter-hamburger`.
  - disclosures (`actions.toggle`, `keyToggle`, context `open`) and the lesson sidebar actions are unchanged.
- `ohmylms/forms`: auth-form root (`actions.fieldFocus`, `fieldBlur`, `fieldInput`, `callbacks.foldFilled`),
  password wrapper context `{ "revealed": false }`, `actions.checkboxKey`, sign-up context `{ "consent": false }`
  with `actions.consentChange` and `state.consentMissing`, and `actions.filterPhone`. L5 adds `submit`, busy and
  error state to this store and keeps these names.
- PHP: `Extensions\Interactivity::enqueue( $module )` for any module; `Extensions\Chrome::enqueue_ui()` when a store
  calls `toast()` on a page that may not load `ohmylms-frontend` (it also guarantees the region).
  `Chrome::template()` runs on `ohmylms_template_html` at priority 15. Batch decorators should do the same:
  match classes, skip elements that already carry the directive, and enqueue only when something matched.
- Classic scripts that remain (until L7) raise toasts with
  `document.dispatchEvent( new CustomEvent( 'ohmylms:toast', { cancelable: true, detail: { message, type } } ) )`.

### L3: catalogue search, filter, sort and load more (done)

Done on 31 October 2026. The L3 handlers took `ohmylms.js` from 45,818 to 35,739 bytes (measured around the L3
edits; other batches edited the file at the same time).

Every catalogue list is a GET URL that the server renders, with or without JavaScript. `ohmylms/catalog`
(`assets/interactivity/catalog.js`, factory and pure helpers in `assets/interactivity/catalog/catalog.js`) only builds
those URLs and asks `@wordpress/interactivity-router` (loaded on the first navigation) to swap two router regions.
`OhMyLMS\Extensions\Catalog` owns the contract: it reads the parameters, applies them to the archive's main query
(`pre_get_posts`, priority 20) and to the course list shortcode (`ShortcodeCourseList`, so the block, Elementor,
Bricks and WPBakery lists too), prints the state and decorates theme overrides.

URL contract (also the field names, so the filter form submits it as is):

| Parameter | Values | Notes |
| --- | --- | --- |
| `course_search` | text, at most 100 characters | WordPress search on the course query (`s`) |
| `course_curriculum[]` | `c<id>` | Curriculum item and everything below it (the "Category" filter and the layout 3/4 buttons) |
| `course_bundle[]` | `t<id>` | Course Bundle (the "Tag" filter; not listed by `filters.php` today) |
| `course_level[]` | `beginner`, `experience`, `expert`, `all` | `all` does not filter |
| `course_price[]` | `paid`, `free` | `_price_type` |
| `course_sort` | `date` (default, left out), `max_price`, `min_price`, `rating`, `review` | Courses without the meta (no rating yet) are listed last instead of being left out |
| `paged` (or `/page/N/`) | 1 to 50 | Load more: lists pages 1..N in one page, so reload, a shared link and Back show the same cards |

Names avoid the `course_category` / `course_tag` taxonomy query vars. Values are sorted and defaults dropped by both
`toParams()` (JS) and `Catalog::query_string()` (PHP), so links printed by the server and URLs built in the browser are
the same string (and hit the router's cache). `Catalog::canonical()` turns off WordPress's canonical redirect for these
URLs: it rewrote `name[]` to `name[0]` (an extra request per navigation) and dropped `paged` on the archive.

| Behaviour | Now | Markup (written in the template; `Catalog::template()` adds the same to overrides) |
| --- | --- | --- |
| Filter form | `<form id="ohmylms-catalog-form" method="get">` with hidden fields for the base URL's own query (`page_id` with plain permalinks) and a `<noscript>` "Apply filters" button; every control is associated with `form="…"`, wherever it sits | `archive-course.php` |
| Checkboxes, sort | `actions.change` (delegated from the root: the boxes sit inside the `ohmylms/ui` accordions), immediate navigation; focus stays on the control | `filters/filters.php` (`name="course_level[]" value="expert"`, server `checked`), `filters/course-sort.php` (labelled, server `selected`) |
| Search | `actions.input`, debounced 350 ms; typing refines one history entry (`replace`); Enter submits and moves focus to the results | `filters/course-search.php` (labelled `type="search"`) |
| Clear all | `actions.clear`: unticks the boxes, keeps search and sort (as before) | `filters/filter-header.php` |
| Curriculum buttons (layouts 3/4) | real links (`?course_curriculum[]=c27`), `actions.category`, `state.categoryActive` / `categoryCurrent` (`aria-current`) | `filters/category-type-button.php` |
| Load more | `actions.loadMore` navigates to `paged=N+1`; the server lists pages 1..N+1, Preact keeps the cards already shown and focus moves to the first new card. Without JavaScript the button submits the form with `paged=N+1` | `loop/load-more.php`: always prints `.ohmylms-course-loadmore-area` (router region `ohmylms/catalog-more`, `hidden` on the last page) |
| Results | router region `ohmylms/catalog-results` (`aria-busy` / `.is-loading` while fetching) holding a visually hidden `<h2 tabindex="-1">` focus target and a `role="status"` count ("Showing 4 of 9 courses."); an empty filtered list keeps the filters and says so | `archive-course.php` |
| Back/Forward | the router swaps the regions; `actions.popstate` syncs the controls and focuses the heading | root `data-wp-on-window--popstate` |
| Mobile filter drawer | `ohmylms/ui` drawer on `.ohmylms-course-outer` (`drawerPanel` `.ohmylms-course-sidebar`); the toggle uses `ohmylms/ui::actions.drawerToggle`, the close button `actions.closeFilters` | `archive-course.php`, `filters/search-sort.php`, `filters/filter-header.php` |
| Card popup (layout 3) | delegated `actions.cardOver` / `cardOut`, `callbacks.reposition` on resize, position from `popupPosition()` | popups render inside the results region |
| Carousels (layouts 3/4) | the rows render inside the results region; `.ohmylms-course-carousel-wrapper` carries `data-wp-key="rows-<item>"`, so a new curriculum item mounts fresh `ohmylms/carousel` instances (their `callbacks.mount` measures the new slides) | `content-course-carousel.php` |

Focus: moves to the results heading after a submit, "Clear all", a curriculum button and Back/Forward (without
scrolling); stays on the control for checkboxes, sort and typing, where the live count announces the result. Theme
overrides of `archive-course.php` from before L3 have no regions: the module then loads the filtered URL in full, and
the decorated templates become their own `ohmylms/catalog` islands (no mobile drawer).

AJAX actions kept, flagged for removal once nothing outside the plugin calls them: `ohmylms_loadmore`
(`load_more_nonce`) and `ohmylms_search_filter` (`search_filter_nonce`), still localized in `ohmylms_frontend_params`.
Both now build their query with `Catalog::query_args()` (from the legacy `search_term`, `category_slug[]`,
`tag_slug[]`, `level_slug[]`, `price_slug[]`, `sort_by` fields), so they return the same courses as the URL; their
POST fields and HTML answers are unchanged. Nothing in the plugin calls them any more.

Removed from `ohmylms.js`: the page/filter variables, `OhMyLMS.load_more`, `OhMyLMS.filter` and their six `init`
bindings, the `.checkedbox` Enter handler, the `.ohmylms-filter-accordion` accordion init (the `ohmylms/ui`
disclosures already handle `filters/filters.php`), the three mobile drawer handlers, the
`.ohmylms-category-type-button li` handler, and `positionPopup()` with its card, popup and window resize handlers.
None triggered a jQuery event and `docs/EXTENSIONS.md` documents none for the catalogue, so nothing is re-dispatched;
the store emits `ohmylms:catalog-before-navigate` (cancelable) and `ohmylms:catalog-navigated` instead.

Behaviour changes, all deliberate:

- Filtered lists, sort and load more are shareable URLs that work without JavaScript; Back/Forward work.
- Search waits for a pause in typing (it sent a request per key) and counts every course matching the term.
- Load more respects the filters, the shortcode's own `curriculum`/`bundle` restriction and its page size (the jQuery
  version posted the option's page size and lost the shortcode restriction).
- Sorting by price, rating or reviews keeps courses that have no value (they were dropped by `meta_key`).
- The layout 3 popup works for every card, including those from later navigations; popups are refreshed with the cards.
- The filter header ("Clear all", the drawer's close button) now follows the course list's `show_filter` attribute,
  not only the archive option.
- Curriculum buttons show the selected item after a reload (the server marks it), and are real links.

Live check (temporary options and courses, restored afterwards): filters, search typing, sort, "Clear all", load more,
Back/Forward, curriculum buttons with the carousel re-measuring at 700 px, the card popup and the drawer were driven in
the browser without a page reload; `/course/` with and without each parameter answers 200 with no PHP notices,
anonymously and logged in. Not done: a Playwright spec (the browser fixture is still broken, see L2).

Tests: `tests/js/catalog.test.mjs` (parameter names, parsing, clamping, `/page/N/`, the canonical query string shared
with PHP, URL building, page reset, clear, last page, history mode, popup position; the store factory: checkbox
navigation and loading flag, debounced search with `replace`, submit focus, clear, no duplicate fetch, load more and
its last page, Back/Forward sync, cancelled navigation, curriculum state) and `tests/php/catalog-integration.php`
(`OHMYLMS_CATALOG_TEST=1`: URL contract, module registration with the router as a dynamic dependency, server-side
search/level/price/sort/curriculum filtering and cumulative pages through the course list, directives, regions, form
controls and state, the carousel layout, decorated pre-L3 overrides, the two admin-ajax actions returning the same
courses, and the live archive filtering for the same URL without redirects; its three test courses are deleted).

### L4: learner dashboard and profile (done)

Done on 31 October 2026. The L4 handlers took `ohmylms.js` from 56,189 to 45,818 bytes (measured around the L4
edits; other batches edited the file at the same time).

| Behaviour | Now | Markup (written in the template; `Extensions\Account` adds the same to theme overrides) |
| --- | --- | --- |
| Cover and profile photo | `ohmylms/account` (`assets/interactivity/account/store.js`): `actions.uploadCover`, `uploadAvatar`, `deleteCover`, `pickKey` (Enter/Space on the labels), `state.coverDeleteDisplay`, `avatarHidden`, `initialsHidden`, `isBusy`; root context `{ cover, hasCover, avatar, busy }` from `Account::profile_context()` | `profile/profile-edit.php` (`.student-profile-edit`) |
| Repeatable skill and link rows | `actions.addRow` (clones `<template id="skills-template">` / `links-template`, focuses the new field), `actions.removeRow` (delegated on `.ohmylms-dynamic-fields`; the last row is cleared, focus moves to the neighbour) | `profile/profile-edit.php`; the inline `<script>` is gone. Overrides keep their own inline script and are not decorated for this |
| My courses tabs | public `ohmylms/tabs`: root context `{ activeTab }`, per-tab `{ tabKey }` on the `<li>` and the panel, `actions.select`, `actions.key`, `state.selected`, `state.tabIndex` | `profile/my-courses.php` (`Account::tabs()` for overrides); the panel is now labelled by its tab |
| Membership "View plan" dialog | per-card context `{ planOpen }`, `actions.openPlan`, `closePlan`, `planBackdrop`, `callbacks.planFocus` (`trapFocus()`, Escape closes, focus returns to "View plan"), `state.planDisplay` | `profile/loop/membership-card.php`: `role="dialog"`, `aria-modal`, labelled by its title, unique `id`; "View plan" links to `#id`, so `:target` opens it without JavaScript |
| Cancel membership | `actions.cancelMembership`: `confirmDialog( { tone: 'danger' } )`, then `request( { action: 'ohmylms_cancel_membership', nonce, data: { subscription_id, order_id } } )`, toast, reload | `profile/membership.php`: link context `{ subscriptionId, orderId, busy }`; the inline `.ohmylms-alert` (and its `data-student-id`) is gone. Pre-L4 overrides: the ids are read from their alert's `.ohmylms-cancel-membership` button |
| Invoice print | `actions.printInvoice`: marks `<html>` and the invoice, `window.print()`, unmarks on `afterprint`; `@media print` rules in `assets/interactivity/frontend.css` | `profile/invoice-details.php` (`.print-button`) |
| Notification switches | no script: every switch posts `value="on"` | `profile/notifications.php`; `Account::decorate()` rewrites the value in overrides (only the four `notification_*` names inside `.single-notification-settings`) |
| Dashboard table rows | `ohmylms/ui` disclosures (L1), unchanged | `Interactivity::disclosures()` |

Endpoints: no learner REST route exists for these, so they stay on admin-ajax through `request( { action } )`:
`ohmylms_student_cover_image_upload`, `ohmylms_student_profile_image_upload` (`FormData` with `file`),
`ohmylms_student_cover_image_delete` and `ohmylms_cancel_membership`. Nonces come from
`wp_interactivity_state( 'ohmylms/account' ).nonces` (`coverUpload`, `coverDelete`, `avatarUpload`, `cancelMembership`),
added by `Account::enqueue( [ 'profile' | 'cancel' ] )` only when those templates render, with `maxUpload`
(`wp_max_upload_size()`) and the messages. The profile form, password change and notification form keep posting to
`FormHandler` without JavaScript. The Elementor and Bricks dashboard/profile widgets and the blocks render through
`ShortCodeDashboard` / `ShortCodeProfile` and `ohmylms_get_template()`, so they get the same markup.

Server-side changes:

- `Ajax::upload_image()` accepts only JPEG, PNG, GIF and WebP (it accepted any allowed upload type) and answers the
  `wp_handle_upload()` error message.
- Removing a cover stored the placeholder URL (`Student::get_cover_image( 'edit' )` returned it), so the delete button
  came back on reload; the `edit` context now returns the raw value, and `StudentStore::has_cover_image()` ignores a
  stored placeholder.
- `Ajax::drop_course()` and `Ajax::lesson_access_nonce()` and their localized nonces were removed (see
  [Removed in L1](#removed-in-l1)).

Removed from `ohmylms.js`: `upload_cover_image`, `delete_cover_image`, `upload_profile_photo`, `cancel_membership`
and their `init` bindings, the `.cover-edit` / `.edit-avatar` keydown handlers, `ohMyLMSJQueryTab()` and its my-courses
call, the notification switch handler, the `.dashboard-table-tr` accordion copy, the membership dialog handlers
(`.view-plan`, inner click, body / `.close-modal` click, document Escape), the invoice print handler and the
`.do-membership-cancel` / `.ohmylms-alert` handlers. None triggered a jQuery event (and `docs/EXTENSIONS.md` documents
none for these screens), so nothing is re-dispatched; the store emits `ohmylms:account-before-upload` (cancelable),
`ohmylms:account-image-changed`, `ohmylms:account-error`, `ohmylms:membership-before-cancel` (cancelable),
`ohmylms:membership-cancelled` and `ohmylms:membership-cancel-error` instead. The block editor scripts had nothing to
remove (see [Block editor scripts](#block-editor-scripts-assetssrcruntimesblocksjs)).

Behaviour changes, all deliberate:

- Uploads check the file type and size first, show a busy state, report failures in a danger toast (they went to the
  console) and confirm success with a toast. Picking the same file twice works (the input is reset).
- Cancelling asks with the shared native dialog instead of the inline alert, and a refused cancel shows the server's
  message instead of reloading silently.
- Printing no longer replaces `<body>` and reloads the page.
- `ShortCodeProfile::add_navigation_handler()` (an inline vanilla script for the shortcode's view links) is untouched.

Live note: on the local site the legacy shortcodes print a link to the student hub and the hub has its own membership
screens, so these templates are reached through the blocks, widgets, themes and overrides. They were checked over HTTP
as a temporary learner on temporary `ohmylms/dashboard` and `ohmylms/profile` block pages (dashboard card, profile edit,
membership table, invoice, state nonces) and the cancel request was sent as the module sends it (refused with a bad
nonce and anonymously, accepted for the owner); the learner, its session and the fixtures were deleted. Not done: a
browser run of the dialog and uploads (`tests/browser/account-blocks.spec.cjs` was not extended).

Tests: `tests/js/account.test.mjs` (image checks, cancel fields, messages, row templates, upload success, refusal,
failure and veto, pre-L4 avatar, cover removal, label keys, rows, cancel confirm, keep, refusal and pre-L4 ids, plan
dialog focus and closing, print) and `tests/php/account-frontend-integration.php` (`OHMYLMS_ACCOUNT_FRONTEND_TEST=1`:
module registration, rendered directives and context for every template above, state nonces, decorated pre-L4
overrides, hub switches left alone, the upload/delete handlers with the state nonces, and cancelling with only the
subscription and order: refused for another learner, accepted for the owner; fixture users, plan, order and
subscription are deleted).

### L5: authentication forms and reviews (done)

Done on 31 October 2026. The L5 handlers took `ohmylms.js` from 59,830 to 56,189 bytes (other batches edited
the file at the same time; this is the L5 share).

| Behaviour | Now | Markup (written in the template; the decorator adds the same to theme overrides) |
| --- | --- | --- |
| Login and sign-up submission | `ohmylms/forms` (`assets/interactivity/forms/auth.js`, merged into the L2 store): `actions.submit`, `state.submitDisabled`, `state.loaderDisplay`, `state.errorHidden`; form context `{ authForm, authHidden, busy, error }` (sign-up also `consent`) | `global/form-login.php`, `global/form-signup.php`; `Extensions\AuthForms::decorate()` on `ohmylms_template_html` (priority 16) |
| Show sign-up / show login | `actions.showSignup`, `actions.showLogin`, `state.authView`, `state.authDisplay` (focus moves to the first field of the shown form) | `a.ohmylms-show-signup-form`, `a.ohmylms-show-login-form` (`role="button"`) |
| Pending email verification | `state.pendingMessage`, `state.pendingHidden` | `.ohmylms-email-pending-notice` island after the sign-up form (was built by jQuery) |
| Review stars and "rating and review both are required" | `ohmylms/reviews` (`assets/interactivity/reviews/rating.js`): `actions.change`, `validate`, `submit`, `reset` (on `pageshow`), `pick` (pre-L5 link stars), `state.rated`, `isStar`, `noticeDisplay`, `callbacks.restore` | `single-course/tabs/reviews.php`; `Extensions\Reviews::decorate()` (priority 16) |

Endpoints: unchanged. The forms post their own fields (nonce, hook fields from `ohmylms_login_form` /
`ohmylms_signup_form`, captcha fields) with `request( { action: 'ohmylms_login' | 'ohmylms_signup', data } )`.
`Ajax::login()` and `Ajax::signup()` now wrap `Ajax::login_response()` / `Ajax::signup_response()`, which return the
same `{ status, message, redirect_url }` array. Without JavaScript the form posts to its page and
`AuthForms::handle_post()` (on `wp_loaded`, front end only, not AJAX) runs the same method: success redirects with
`wp_safe_redirect()`, an error or a pending verification re-renders the form with the message. Reviews keep posting to
`wp-comments-post.php`; `CourseComment::add_comment_rating()` reads `rating` from the radios as it did from the select.

Server-side changes, all in the two shared methods:

- `redirect_to` goes through `wp_validate_redirect()` (it was only `esc_url_raw()`, an open redirect), for the login
  answer, the sign-up answer and the stored post-verification redirect.
- A stale or missing form nonce answers `{ status: 'error', message }` instead of an empty `0`, so a cached page tells
  the learner to reload. A failed automatic sign-in after sign-up answers an error instead of `0`.
- Nonces, `wp_signon()` (so `authenticate` filters such as captcha and rate-limit plugins), `ohmylms_login_failed`,
  `ohmylms_signup_failed` and the email verification flow are unchanged. There is no `ohmylms_before_login` filter.

Removed from `ohmylms.js`: the `submit` handlers for `.ohmylms-form-login` and `.ohmylms-form-signup`
(`OhMyLMS.login`, `OhMyLMS.signup`, including the jQuery-built pending notice), the
`a.ohmylms-show-signup-form` / `a.ohmylms-show-login-form` click handlers (`show_signup_form`,
`show_login_form_again`), and the review star click and `#submit` validation handlers. None triggered a jQuery event, so
nothing is re-dispatched; the stores emit `ohmylms:auth-before-submit` (cancelable), `ohmylms:auth-complete`,
`ohmylms:auth-error`, `ohmylms:auth-view-changed`, `ohmylms:review-rated` and `ohmylms:review-before-submit`
(cancelable) instead ([INTERACTIVITY](INTERACTIVITY.md#public-events-and-slots)). `const { __ } = wp.i18n` in
`ohmylms.js` has no caller left; it stays until L7 deletes the file.

Behaviour changes, all deliberate:

- Errors show inline in the form's `role="alert"` region instead of a toast; a login error shows the server's message
  (as `wp-login.php` does) instead of the fixed "Invalid username or password". Forms from theme overrides without the
  region fall back to a danger toast; without the pending notice, to a sticky toast.
- The button is disabled and the loader shown while a request runs, so a double click no longer sends twice.
- The redirect is followed only when it is on the page's origin (otherwise the page reloads).
- The opt-in React sign-up fields (`OHMYLMS_SOURCE_ASSETS`) keep owning sign-up submission; the store steps aside when
  the form has `data-ohmylms-registration="react"` (they also stop the event first).
- Review stars are labelled radio inputs, so the rating posts and the stars fill (`:has(:checked)`) without JavaScript
  and work with arrow keys; `select#rating` and the `<a href="#">` stars are gone from the plugin template. The star CSS
  is in `assets/interactivity/frontend.css` (no build step). The required notice is `role="alert"`, and the submit
  button is disabled after a valid submit until the page is shown again.
- Checkout login: the form is the same `global/form-login.php`; `ohmylms/checkout`'s log-in toggle still sets its
  inline `display`, and the login store never overrides it (its display only changes when a switch link is used).

Not done here: review edit, delete and "load more reviews" (no such UI in the plugin), lost/reset password (core
`wp-login.php`), social sign-in (plain links to the REST routes, no script) and Q&A (not in the plugin). Live note: on
the local site the profile and checkout pages render the student hub's own sign-in and learners with access are sent to
the hub, so these templates are reached only through themes, shortcodes and overrides; the module was checked on a
temporary page in a clean headless browser (login with and without JavaScript, view switch, review stars and notice).

Tests: `tests/js/forms.test.mjs` (form kind, validation, same-origin redirects, answer mapping, display rules, submit,
errors, override toast fallback, pending notice, terms gate, React and cancel opt-outs, view switch),
`tests/js/reviews.test.mjs` (rating parsing, missing parts, stars, link stars, notice, double submit, cancel, restore) and
`tests/php/auth-reviews-integration.php` (`OHMYLMS_AUTH_REVIEWS_TEST=1`: rendered directives and state, decorated pre-L5
overrides, login and sign-up answers for the module's request shape including stale nonces and external redirects,
pending verification, the no-JavaScript post, the review form and a radio-rated review; fixture users and the review are
deleted). `learner-chrome-integration.php` now expects the L5 sign-up context and `state.submitDisabled`.

### L6: lesson and course player and progress

- Files: mark complete, assignment submission, attachments, timer and deadline, prerequisite dialog, lesson
  sidebar tabs, quiz text fields (into `ohmylms/questions`), content images (move to PHP), content protection;
  `single-lesson/content-navigation.php`, `content-assignment.php`, `sidebar/sidebar.php`, `content-lesson.php`;
  `Ajax::lesson_completed()`, `Ajax::save_assignment_submission_file()`, `Ajax::save_remaining_time()`
  (`Ajax::lesson_access_nonce()` was removed in L4).
- Stores: `ohmylms/lesson` (completion, navigation, sidebar tabs), `ohmylms/assignment` (files, timer,
  submission), `ohmylms/protection` (only when the option is on, so other pages ship nothing).
- Directives: `data-wp-on--change` on `#ohmylms-completed-lesson` with `data-wp-bind--disabled`, a context with
  lesson id and next link; file list rendered from context with `data-wp-each`; timer via `data-wp-init` and
  `data-wp-text`; tab buttons with `ohmylms/tabs`.
- Tests: PHP: `lesson_completed` returns the next link and completes once (extend
  `lesson-settings-integration.php`); `assignment-delivery-integration.php` for uploads; `tests/js/lesson.test.mjs` and
  `assignment.test.mjs` (file limit, timer, double submit); Playwright `learner-journey.spec.cjs`.

### L7: video

- Files: `lesson/video-player.js`, `video-progress-tracker.js`, the video cover handlers in `ohmylms.js`;
  `content-lesson.php`, `content-zoom.php`, `content-googlemeet.php`; `Ajax::*_video_progress()`.
- Store: `ohmylms/video`, with provider adapters (YouTube, Vimeo, native) as separate modules loaded on demand,
  and progress saved through the SDK `request()` (or a REST route) with `navigator.sendBeacon` on page hide.
  Keep `window.OhMyLMSVideoPlayer` and `window.initOhMyLMSVideoPlayers` as thin shims for one release.
- Directives: `data-wp-interactive="ohmylms/video"` with a context (`url`, `lessonId`, `courseId`,
  `watchPercent`, saved position) on `.ohmylms-custom-video-player`; `data-wp-on--click` on controls;
  `data-wp-style--width` on the progress bar; `data-wp-bind--checked` on the completion checkbox.
- Tests: `tests/js/video-progress.test.mjs` (threshold, lesson rule overrides site setting, completion once,
  resume position); PHP tests for the server-side watch rule; Playwright with the native `<video>` provider.

After L7 the learner pages need no jQuery from OhMyLMS: drop `jquery` from the handles, delete `ohmylms.js`
and remove `ohmylms-frontend` from the Elementor/Bricks/block registrations (keep the handle as an alias that
loads the modules, since third-party code depends on it).

## Typing and shared code

- Add `// @ts-check` and JSDoc types to every new module and a `tsconfig` for `assets/interactivity`
  (`checkJs`, `noEmit`, `@wordpress/interactivity` types) run by `npm run typecheck`; there is still no build
  step for these modules.
- Done in L2: the request helper, toast and the config reader live in `ohmylms/interactivity`. The shared config
  comes from script module data, so the SDK keeps no dependency on `@wordpress/interactivity`; per-store values
  (page nonces, messages) go in `wp_interactivity_state()` for that store.
- Add the migrated modules to `lint:js` (it already covers `assets/interactivity`) and extend
  `tools/check-architecture.mjs` to fail on new jQuery use in learner code.
