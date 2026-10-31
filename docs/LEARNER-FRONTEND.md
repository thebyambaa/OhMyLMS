# Learner front end: script map and Interactivity migration

Status: batches L1–L7 done on 31 October 2026 (foundation through lesson/assignment player and video).
L3–L7 build on the [shared helper API](#shared-helper-api-l2) below. Roadmap item 6 in
[MODULARIZATION-ROADMAP](MODULARIZATION-ROADMAP.md).

The admin is the typed app. Learner UI is implemented with Interactivity script modules in
`assets/interactivity` ([INTERACTIVITY](INTERACTIVITY.md)), plus a few vanilla helpers in `assets/js`.
Carousels, commerce, the exam paper, catalogue, account pages, auth forms, the lesson player and video
each have their own store. L7 deleted `ohmylms.js`. The `ohmylms-frontend` handle stays with no source
file and no jQuery, so Chrome, Elementor, Bricks, blocks, previews, the hub and the exam player can
still enqueue it and receive `ohmylms_frontend_params`.

## How learner scripts are served

Since L7, `Assets\FrontendAssets::LEARNER_SCRIPTS` is empty. `ohmylms-frontend` is registered with
`src` false and no dependencies; localizing it still prints `ohmylms_frontend_params`. `BlocksManager`
registers the same kind of handle (`ohmylms-frontend-editor`) for the block editor, and `ElementorManager`
registers `ohmylms-frontend` the same way when FrontendAssets has not. `Interactivity::legacy_url()` no
longer rewrites `assets/dist/frontend/{ohmylms,video-player,video-progress-tracker}.js`: those files are
not on disk (they stay in `retiredAssets`), and there is no authored file left to point them at.

Video uses the `ohmylms/video` module and provider adapters under `assets/interactivity/video/`. The
old `ohmylms-video-player` and `ohmylms-video-progress-tracker` files were removed in L7, and `ohmylms.js`
was deleted in the same batch.

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
| Accordions | `.ohmylms-filter-accordion`, `.ohmylms-lesson-sidebar-accordion` `.ohmylms-accordion-head` (+ keyboard) | `filters/filters.php`, `single-lesson/sidebar/sidebar.php` | — | `ohmylms/ui` disclosures; the jQuery copies (click and keyboard) were **removed in L6** |
| Responsive tables | `.ohmylms-table .ohmylms-td-handle` (bound only if the window is under 768 px at load), `.dashboard-table-tr .table-accordion-handler` | `single-lesson/content-quiz.php`; `profile/membership.php`, `membership-details.php`, `invoice-details.php`, `transactions-history.php` | — | `ohmylms/ui` disclosures. The `.dashboard-table-tr` jQuery copy was **removed in L4**, the quiz `.ohmylms-td-handle` copy **in L6** (`content-quiz.php` is rendered by `ohmylms_get_template()`) |
| Lesson sidebar tabs | `.ohmylms-lesson-tab-nav li`, `.ohmylms-lesson-single-tab-content` | `single-lesson/sidebar/sidebar.php` | — | **Migrated (L6)** to the public `ohmylms/tabs` store (`role="tab"`, arrow keys) |
| My courses tabs | `.ohmylms-my-courses-tab ul li button` (arrow keys) | `profile/my-courses.php`, `Shortcodes\ShortCodeMyCourses` | — | **Migrated (L4)** to the public `ohmylms/tabs` store (arrow keys, Home, End) |
| Toast | `#ohmylms-toast`, `.ohmylms-message`, `#ohmylms-close-toast` | `global/toast.php` | — | **Migrated (L2)** to `ohmylms/ui`. The jQuery shim that dispatched `ohmylms:toast` lost its last callers (lesson complete, assignment) and was **removed in L6** |
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
| Mark lesson complete | `#ohmylms-completed-lesson`, `#ohmylms-lesson-id`; `.ohmylms-lesson-navigation .ohmylms-checkbox` (Enter) | `single-lesson/content-navigation.php` | `ohmylms_lesson_completed` → `Ajax::lesson_completed()` (nonce `lesson_completed_nonce`), then next link or reload | **Migrated (L6)** to `ohmylms/lesson`; the server now applies enrolment, drip, prerequisites and the sequential lock (`Learning\LessonGate`) |
| Assignment submission | `.ohmylms-assignment-form`, `#submission-file`, `#ohmylms-add-new-attachment`, `.remove-resource-action` (built by the script), `.ohmylms-file-upload` (keyboard), `.assignment-submit-alert` | `single-lesson/content-assignment.php` | `ohmylms_save_assignment_submission_file` → `Ajax::save_assignment_submission_file()` (nonce `assignment_submission_nonce`) | **Migrated (L6)** to `ohmylms/assignment` (file list rendered with `data-wp-each`) |
| Assignment timer and deadline | `.ohmylms-lesson-navigation .start-submit-assignment`, `input[name="ohmylms_assignment_deadline"]`, `.submission-time-limit .timer-display[data-timer]`, `.deadline` | `single-lesson/content-navigation.php`, `content-assignment.php` | `ohmylms_save_remaining_time` → `Ajax::save_remaining_time()`; at zero `ohmylms_quiz_exit_submission` (see findings) | **Fixed and migrated (L6)**: the server answers the deadline and refuses late submissions; at zero the module submits through the assignment action or locks |
| Quiz text fields | `.textarea-auto-resize`, `.ohmylms-has-character-limit[data-limit]` | `quiz-loop/long-text.php`, `quiz-loop/short-text.php` | — | **Removed (L6)**: `ohmylms/quiz::actions.answerChanged` (added to every field by `Interactivity::quiz()`) already limits, counts and resizes them |
| Lesson video cover | `.ohmylms-video-player`, `.ohmylms-video-player-play`, `.ohmylms-video-player-cover`, `.the-video` (three overlapping handlers) | `single-lesson/content-lesson.php` | — | **Migrated (L7)** to `ohmylms/video` (`actions.coverPlay`, `state.coverHidden`). `ohmylms.js` was then deleted |
| Lesson content images | `.ohmylms-wysiwyg-content img[data-width][data-align]` wrapped in `.image-wrapper` | `single-lesson/content-{lesson,quiz,assignment,zoom,googlemeet}.php` | — | **Moved to PHP (L6)**: `Extensions\Lesson::images()` styles the `<img>` (no wrapper; no CSS used `.image-wrapper` in lesson content) |
| Prerequisite dialog close | `.ohmylms-access-denied-inner`, `.ohmylms-access-denied-close`, `.ohmylms-access-denied-modal-content` | `Hooks\CommonHook::restrict_access()`, `single-lesson.php` | — | **Removed (L6)**: every modal is printed by `wp_die()` or before `wp_footer()` in `single-lesson.php`, so the script never ran next to it |
| Content protection | whole page when `is_creator_page` and `content_protection === 'yes'` (blur on window blur, context menu, copy/cut, shortcut keys, DevTools size check, Loom element poll) | option `ohmylms_content_protection` | — | **Migrated (L6)** to `ohmylms/protection`, loaded only when the option is on; deterrent only |
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
callers of the two catalogue admin-ajax actions. L6 removed `assignment_submission_nonce`, `save_remaining_time` and
`quiz_exit_submission` (the quiz player has its own `expiryNonce`); the lesson and assignment nonces are in
`wp_interactivity_state( 'ohmylms/lesson' | 'ohmylms/assignment' )`. `lesson_completed_nonce` and
`video_progress_nonce` stay for third-party callers of those admin-ajax actions.

### `lesson/video-player.js` (handle `ohmylms-video-player`, needs jQuery, content pages)

Custom controls for YouTube (iframe API), Vimeo (`player.vimeo.com/api/player.js`) and native video on
`.ohmylms-custom-video-player[data-video-url]` from `single-lesson/content-lesson.php`, `content-zoom.php` and
`content-googlemeet.php`: play/pause, progress and seek, volume, speed menu, fullscreen, keyboard shortcuts,
branding blockers, logo overlay. Globals: `window.OhMyLMSVideoPlayer`, `window.initOhMyLMSVideoPlayers`,
`window.onYouTubeIframeAPIReady`. No server calls. **Migrated (L7)** to `ohmylms/video` and one adapter
module per provider; the two globals remain as thin shims for one release (`onYouTubeIframeAPIReady` is
chained inside the YouTube adapter and is no longer a page global of ours).

### `video-progress-tracker.js` (handle `ohmylms-video-progress-tracker`, needs jQuery, content pages)

Watch progress for the same players and `video.the-video`: resumes the last position, saves progress, and
completes the lesson at the threshold. Reads `[data-lesson-id]`, `[data-course-id]`,
`[data-ohmylms-watch-percent]` (`content-lesson.php`) and updates `#ohmylms-completed-lesson[data-ohmylms-video-pending]`
and `#ohmylms-video-completion-hint` (`content-navigation.php`). Calls `ohmylms_save_video_progress`,
`ohmylms_get_video_progress`, `ohmylms_clear_video_progress` → `Ajax::save/get/clear_video_progress()` (nonce
`video_progress_nonce`). Reads `ohmylms_frontend_params` (`ajax_url`, `video_progress_nonce`,
`video_completion_threshold`) although its handle depends only on `jquery`; it works because
`ohmylms-frontend` is enqueued on every content page. **Migrated (L7)**: progress config and nonces come
from `wp_interactivity_state( 'ohmylms/video' )`, not from `ohmylms_frontend_params`.

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
- Fixed in L6: when an assignment countdown reached zero, `ohMyLmsCountdown()` called `saveQuizExiSubmit()`, which
  posted `ohmylms_quiz_exit_submission` with no quiz id; the server rejected it. The deadline was never enforced on
  the server either. See [L6](#l6-lesson-and-assignment-player-done).
- Fixed in L7: `video-progress-tracker.js` read `ohmylms_frontend_params` without declaring `ohmylms-frontend`.
  The module reads `wp_interactivity_state( 'ohmylms/video' )` instead, and the jQuery file is deleted.
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
- L7 removed the last classic learner script. Code that still wants the toast region dispatches
  `document.dispatchEvent( new CustomEvent( 'ohmylms:toast', { cancelable: true, detail: { message, type } } ) )`
  or calls `toast()` from `ohmylms/interactivity`.

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
`ohmylms.js` has no caller left; L7 deleted the file.

Behaviour changes, all deliberate:

- Errors show inline in the form's `role="alert"` region instead of a toast; a login error shows the server's message
  (as `wp-login.php` does) instead of the fixed "Invalid username or password". Forms from theme overrides without the
  region fall back to a danger toast; without the pending notice, to a sticky toast.
- The button is disabled and the loader shown while a request runs, so a double click no longer sends twice.
- The redirect is followed only when it is on the page's origin (otherwise the page reloads).
- The opt-in React sign-up fields (`OHMYLMS_SOURCE_ASSETS`) keep owning sign-up submission; the store steps aside when
  the form has `data-ohmylms-registration="react"` (they also stop the event first). Fixed in L6: they posted to
  `ohmylms_frontend_params.ajax_url || ohmylms_checkout_params.ajax_url`, so on pages with neither (most pages now)
  the request went to a relative `…/undefined`. `registrationUrl()` (`assets/src/features/students/registration-api.mjs`)
  reads `ohmylmsRegistration.ajaxUrl` (localized on the `ohmylms-app-registration` handle by
  `SourceAssets::enqueue_registration()`), then the `ohmylms/interactivity` module config, then the legacy globals,
  and `submitRegistration()` refuses to post without a URL. The built bundle picks this up at the next `npm run build`.
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

### L6: lesson and assignment player (done)

Done on 31 October 2026. The L6 handlers took `ohmylms.js` from 35,739 to 1,574 bytes; what is left are the three
video cover handlers (`.ohmylms-video-player-play` click, `.ohmylms-video-player` focus/keydown,
`.ohmylms-video-player-play` click/keydown), owned by the video batch, whose `Extensions\Video` drives the same markup
through `ohmylms/video`. L7 deleted the file. `ohmylms-frontend` stays as a script-less handle that carries
`ohmylms_frontend_params`, because `Chrome::footer()`, Elementor, Bricks, blocks, previews, the hub and the
exam player enqueue it (see [L7](#l7-video-done)).

`OhMyLMS\Extensions\Lesson` (`ohmylms_template_html`, priority 17) adds the directives below to theme overrides of
`single-lesson/content-navigation.php`, `content-assignment.php` and `sidebar/sidebar.php` copied before L6, loads
the modules and prints their state; the current templates carry the same directives themselves.

| Behaviour | Now | Markup |
| --- | --- | --- |
| Mark as complete | `ohmylms/lesson` (`assets/interactivity/lesson.js`, factory and helpers in `lesson/store.js`): root context `{ lessonId, completed, busy, videoPending, nextUrl }` from `Lesson::navigation_context()`; `actions.complete` (change), `actions.checkboxKey` (Enter/Space on the focusable label), `state.completeDisabled`, `state.completeLabel`, `callbacks.listen` | `.ohmylms-lesson-navigation` root; `#ohmylms-completed-lesson` gets `data-wp-on--change`, `data-wp-bind--checked`, `data-wp-bind--disabled`; the label text is a `span.ohmylms-checkbox-label`; `#ohmylms-video-completion-hint` is bound to `!context.videoPending`. The input lost `aria-labelledby` (it pointed at the hidden tick, so the box had no name) and the bogus `aria-required` |
| Next / previous | The next link stays a plain `a.next-lesson` (works without JavaScript). After a completion the store follows the server's `next_content_link` (same origin only) or reloads, 500 ms after the toast. The templates have no "previous" control; the hub player's outline (`assets/js/player-context.js`) is unchanged | — |
| Sidebar tabs | public `ohmylms/tabs` (root `{ activeTab }`, per-tab and per-panel `{ tabKey }`) | `aside.ohmylms-lesson-sidebar`; `li[data-target]` gets `role="tab"`, `aria-controls`, `tabindex`; panels `role="tabpanel"`. The sidebar is not rendered inside the hub player (`PlayerContext::active()`) |
| Start, timer, deadline | `ohmylms/assignment` (`assets/interactivity/assignment.js`, `assignment/store.js`): `actions.start` asks the server to start the timer and counts down to the deadline it answers, corrected by `state.serverNow` for the device clock; `state.timeLabel` (same `05m 09s` / `01h 05m 09s` / `02d …` format), `state.timerWidth`, `state.deadlineLabel` | the Start / Try Again buttons in `content-navigation.php` use `ohmylms/assignment::actions.start` and hide themselves; the skip link hides too; `.ohmylms-assignment-submit` (`state.panelDisplay`), `.submission-time-limit` (`role="timer"`, `state.timerDisplay`); `li.deadline` now shows the stored deadline from the server (it showed the duration, then the script overwrote it with "now + remaining") |
| Files | `actions.pick`, `actions.remove`, `actions.pickKey`; `state.files` rendered by `<template data-wp-each--file="state.files">` in `#ohmylms-submission-file-list`; `state.tooLarge` against `state.maxBytes` (the assignment limit when it is on, else `wp_max_upload_size()`, as the server checks) | the remove control is a `<button>`; `#submission-max-file-limit` now exists (the input referenced it); overrides keep their picker visible, since they have no list template |
| Submission | `actions.submit`: `request( { action: 'ohmylms_save_assignment_submission_file', nonce, data: FormData( form ) } )` (text and file), busy state, errors in the form's `role="alert"` region, then the server's `redirect_url` | `.ohmylms-assignment-form`; the text field is labelled (`#ohmylms-submission-body`); the submit button is bound to `state.submitDisabled` |
| Expiry at zero | a picked file is submitted with `reason: 'timeout'` through the same action; without one the form locks and shows the message of `GET ohmylms/v1/assignments/{id}/learner-state` (or "Time is up…") | — |
| Content protection | `ohmylms/protection` (`protection.js`, rules in `protection/rules.js`): context menu, copy and cut blocked; print, save, view source, developer tools and screenshot shortcuts blocked (most blur the page for 2 s); blurred while the window is in the background (not when focus moved into an embedded player) or while the Loom recorder element is present | `Lesson::protection()` prints the overlay `.ohmylms-screenshot-protection` with `data-wp-init="callbacks.install"` in `wp_footer`, only on content pages with the option on; `body.ohmylms-content-protected` prints nothing (`@media print`), also without JavaScript |
| Lesson images | `Lesson::images()` styles `img[data-width][data-align]` in `single-lesson/content-*.php` output (`display:block`, the width, margins for the alignment) | — |

Contract for the video module (`ohmylms/video`, batch L7). The lesson-complete endpoint is unchanged:
`ohmylms_lesson_completed` with `lesson_id` and nonce `lesson_completed_nonce` (in
`wp_interactivity_state( 'ohmylms/lesson' ).nonces.complete`, and still in `ohmylms_frontend_params`), answering
`{ success, data: { status, next_content_link, message, already_completed? } }` or
`{ success: false, data: { code, message } }`. Do not call it directly; dispatch the request on `document` and let
the lesson store send it, keep the checkbox, label and hint in sync and emit the events:

```js
// The tracker's save_video_progress answer says the server completed the lesson: update the page only.
document.dispatchEvent( new CustomEvent( 'ohmylms:lesson-complete-request', {
  detail: { lessonId, source: 'video', completed: true },
} ) );
// Ask the store to complete the lesson (same request as the checkbox); add navigate: true to go to the next content.
document.dispatchEvent( new CustomEvent( 'ohmylms:lesson-complete-request', {
  detail: { lessonId, source: 'video' },
} ) );
```

`lessonId` may be left out (the page's lesson); a request for another lesson is ignored. The store answers with
`ohmylms:lesson-before-complete` (cancelable), `ohmylms:lesson-completed` (`lessonId`, `source`, `nextUrl`) or
`ohmylms:lesson-complete-error` (`code`, `message`; `ohmylms_lesson_video_not_watched` shows the hint again). For
code that still triggers the legacy tracker's jQuery `ohmylms:video:completed`, the store treats it (when jQuery is
loaded) as a `completed: true` request.

Endpoints. No learner REST route exists for completion, submission or starting the timer, so they stay on
admin-ajax through `request( { action } )`: `ohmylms_lesson_completed`, `ohmylms_save_assignment_submission_file`
and `ohmylms_save_remaining_time`. The lock at zero reads the typed REST route
`GET /ohmylms/v1/assignments/{id}/learner-state` ([assignments API](api/assignments.md)).

Server-side changes:

- **"Mark as complete" is gated** (`Learning\LessonGate::check_completion()`, called by `Ajax::lesson_completed()`
  before anything is written). It refused nothing before: a direct request completed any content of an enrolled
  course. Now only published lessons and live sessions complete this way (quizzes and assignments answer
  `ohmylms_lesson_not_completable`), and the learner must be enrolled (`_not_enrolled`), past the drip release
  (`_not_open`, with the "available on" message), done with the lesson's prerequisites (`_prerequisites`) and not
  held by the sequential lock or another `ohmylms_is_lesson_locked` rule such as email verification (`_locked`).
  Errors answer 4xx with `{ code, message }`. People who can edit the lesson skip the release, prerequisite and lock
  rules, as they do on the page. Completing a completed lesson answers success with `already_completed: true` and
  fires no hook again (it answered "Lesson completion failed." before).
- **The assignment timer is enforced by the server.** `Assignment\Delivery::timer()` reads the stored deadline
  (option `_ohmylms_deadline_{user}_{assignment}_`, site time, as before) and `Delivery::state()` adds `timer`
  (`enabled`, `limit`, `deadline` UTC, `deadline_label`, `remaining`, `expired`, `closed`) and the reason
  `time_expired` once the deadline plus `Delivery::TIMER_GRACE` (60 s, so a submission sent at zero still counts)
  has passed. Every submission path goes through `Delivery::check_submission()`: the AJAX upload,
  `FormHandler::save_submission_form_data()` (the no-JavaScript handler had no gate at all) and starting the timer.
  Before, the deadline was only a client countdown: an expired learner could press Start again (the template
  checked `0 !== $deadline`, and the remaining time is `false` once it passed) and submit.
- `Ajax::save_remaining_time()` delegates to `Delivery::start_timer()`: it refuses learners the gate refuses
  (it started timers for anyone), stores the deadline once (starting again never adds time), understands
  `minute`/`hour` units (minutes became months, `P5M`, and hours failed), and answers `{ deadline, deadline_label, remaining }` instead of
  an empty `0` for assignments without a time limit (now 400 `ohmylms_assignment_no_time_limit`).
- The assignment gate also applies the sequential lock (`locked`): the submission nonce is the same for every
  assignment, so a learner could submit one whose page the lock hides.
- The start button is offered whenever `Delivery::state()` allows a submission (it was hidden for the last minute of
  a timer because the remaining minutes rounded down to 0).

Removed from `ohmylms.js`: the toast shim, the `OhMyLMS` object, `const { __ } = wp.i18n`, `ohMyLMSAccordion()` and
its sidebar call, the keyboard accordion handler, `ohMyLMSTab()` and its sidebar call, the mobile
`.ohmylms-td-handle` toggle, the completion click and Enter handlers, the assignment submit, start, file change,
add-attachment, remove and drop-zone keyboard handlers, the "Selected file" handler (its `#submission-max-file-limit`
selector never matched), `ohMyLmsCountdown()`, `saveQuizExiSubmit()`, `saveRemainingTime()`, the client-side
deadline text, the quiz text-field resize and character-limit handlers, the whole content protection block,
`wrapAndStyleImages()` and the access-denied modal handlers. None triggered a jQuery event and
`docs/EXTENSIONS.md` documents none for these screens, so nothing is re-dispatched; the stores emit the events in
[INTERACTIVITY](INTERACTIVITY.md#public-events-and-slots) instead.

Behaviour changes, all deliberate:

- The completion checkbox is unticked again with a danger toast when the server refuses (it stayed disabled and
  ticked), and a double click sends one request.
- Assignment errors keep the server's wording, also for network failures; double submission is impossible while a
  request runs; picking the same file after removing it works.
- Content protection no longer closes the tab or redirects to `about:blank` when the window is much larger than the
  viewport (it fired for zoomed pages and docked side panels, which made lessons unreadable for people who zoom),
  no longer blocks keys a page cannot block anyway (Alt+F4, Alt+Tab) or every Cmd+Shift shortcut (only the macOS
  screenshot keys), and a blocked shortcut blurs the page for 2 s instead of until the window loses and regains focus.
  Printing from the browser menu prints nothing.
- Lesson images are no longer wrapped in `div.image-wrapper[data-align]`; the `<img>` gets the same styles.

Text-only assignments are unchanged: the upload handler still requires a file, and assignments that disallow uploads
show only "Next Lesson", as before (a product decision, not a front-end one). Lesson comments are not part of the
learner templates (`#lesson-comment` is an empty panel without a tab), so there was nothing to migrate.

Live check (temporary course, lesson, timed assignment and enrolled learner with one session token, all deleted
afterwards): the lesson and assignment pages answer 200 with the islands, modules and state; in headless Chrome,
Enter on the label completed the lesson and followed the next link, Start showed the form, focused the text field and
counted down from the server deadline, a picked file was listed, removed and submitted with text; over curl the
timer started, text and file were accepted, a submission after the deadline was refused with
`ohmylms_assignment_time_expired`, the reloaded page showed the expiry message without a Start button, and completing
twice answered `already_completed`. On the local site the assignment rewrite rules use a `kept-assignments` base
that no longer matches the permalinks (left by another suite's settings test), so pretty assignment URLs answer 404
there; the checks used `?ohmylms-assignment=<slug>`.

Tests: `tests/js/lesson.test.mjs` (same-origin next link, outcome, disabled state, completion once and navigation,
last lesson, refusal and video hint, cancel, label keys, the complete-request event with and without `completed`,
`navigate`, the legacy jQuery event; protection shortcuts, blur rules and the installed listeners),
`tests/js/assignment.test.mjs` (countdown format, remaining time, bar width, deadline parsing, file limit, start with
the server deadline and clock correction, no time limit, refused start, pick and remove, submit once, refusal,
cancel, expiry with a file and without, the grace period, refused timeout submission, resume, drop-zone keys) and
`tests/php/lesson-player-integration.php` (`OHMYLMS_LESSON_PLAYER_TEST=1`: module registration, rendered directives,
context and state of the navigation, sidebar and assignment templates, decorated pre-L6 overrides, server-sized
images, completion refused for a prerequisite, the drip release, a quiz and a learner who is not enrolled, completed
once with the next link, the timer started once, refused for a stranger, accepted within the grace period and refused
after it (AJAX and REST), the sequential lock for lessons and assignments, content protection on and off, and over
HTTP as the learner: both pages, completion with the page's nonce, the timer, text-and-file submissions, a second
attempt and the late refusal).

### L7: video (done)

Done on 31 October 2026. `Extensions\Video` (`ohmylms_template_html`) renders every lesson and session-replay
player as an `ohmylms/video` island and decorates theme overrides of `content-lesson.php`, `content-zoom.php`
and `content-googlemeet.php` copied before L7. The store is `assets/interactivity/video.js` (factory and
helpers in `video/store.js`, `progress.js`, `session.js`, `sources.js`). One adapter module per platform is
a dynamic import, registered only so the import map can fetch it, and loaded only when that provider is on
the page:

| Provider | Module | Third-party API |
| --- | --- | --- |
| HTML5 (`<video>`, uploaded or a file URL) | `ohmylms/video-html5` | none (native controls, or the custom controls when the lesson uses them) |
| YouTube | `ohmylms/video-youtube` | IFrame API, `https://www.youtube.com/iframe_api`, loaded on first use; an existing `onYouTubeIframeAPIReady` is chained |
| Vimeo | `ohmylms/video-vimeo` | `https://player.vimeo.com/api/player.js`, including a privacy hash |
| Bunny Stream | `ohmylms/video-playerjs` | the player.js protocol over `postMessage` to the iframe (the iframe is the receiver, so the player.js script is not loaded) |
| Wistia | `ohmylms/video-wistia` | `https://fast.wistia.com/assets/external/E-v1.js` |
| Anything else | — | left as the provider's iframe; no API is loaded unless the lesson tracks it or autoplays |

Custom controls (play, seek, volume, speed, captions, fullscreen, keyboard) are offered for HTML5, YouTube
and Vimeo. Bunny and Wistia keep their own controls. A plain `<video>` with a poster uses the old cover
(`.ohmylms-video-player-cover`, `.ohmylms-video-player-play`); the cover is `hidden` without JavaScript.
Autoplay waits for `prefersReducedMotion()` and starts muted. Jumps are not counted as watched (the old
tracker did the same; it did not lock the scrubber). `window.OhMyLMSVideoPlayer` and
`window.initOhMyLMSVideoPlayers` remain as thin shims for one release.

Progress is `request( { action: 'ohmylms_save_video_progress' } )` with the spans actually played, debounced
(15 s while playing, 3 s within 10 points of the threshold, and shortly after pause, seek or end). Leaving
the page sends the same fields with `navigator.sendBeacon`, or `fetch` with `keepalive`. Config and nonces
come from `wp_interactivity_state( 'ohmylms/video' )`, never from `ohmylms_frontend_params`. When the server
answers `is_completed`, the module dispatches `ohmylms:lesson-complete-request` (`source: 'video'`, and
`completed: true` only when the lesson was already completed) and does not post `ohmylms_lesson_completed`
itself while `ohmylms/lesson` is on the page.

The server stays the authority. `VideoProgressTracker::save_progress()` merges the reported spans with the
spans already stored for that learner (`VideoCompletion::seen()` / `remember()`), ignores a client
`watched_duration` when spans are present, and `VideoCompletion::bounded_watch()` caps a required watch by
elapsed time (at most two minutes on the first report, then twice the time since the previous report plus
30 seconds). The threshold is the lesson's `watch_percent` when `completion_rule` is `watch_percent`, else
`ohmylms_video_completion_threshold`. Saving progress does not complete the lesson.
`Ajax::lesson_completed()` calls `VideoCompletion::satisfied()` and answers `ohmylms_lesson_video_not_watched`
until the stored percentage reaches the threshold.

`ohmylms.js` is deleted (it had no behaviour left once the cover handlers moved). `ohmylms-frontend` is a
script-less handle with no jQuery. The old dist URLs are not rewritten; see
[how scripts are served](#how-learner-scripts-are-served).

Tests: `tests/js/video.test.mjs` (intervals, resume, the watch log, every adapter, the session's saves,
beacon, completion once through the lesson event, the store and the shims) and
`tests/php/video-player-integration.php` (`OHMYLMS_VIDEO_TEST=1`: source parsing, module registration,
rendered islands for each provider, decorated pre-L7 overrides, merged spans, the elapsed-time bound, the
lesson threshold over the site setting, completion only through `ohmylms_lesson_completed`, and a forged
full-video report refused).

## Typing and shared code

- Add `// @ts-check` and JSDoc types to every new module and a `tsconfig` for `assets/interactivity`
  (`checkJs`, `noEmit`, `@wordpress/interactivity` types) run by `npm run typecheck`; there is still no build
  step for these modules.
- Done in L2: the request helper, toast and the config reader live in `ohmylms/interactivity`. The shared config
  comes from script module data, so the SDK keeps no dependency on `@wordpress/interactivity`; per-store values
  (page nonces, messages) go in `wp_interactivity_state()` for that store.
- Add the migrated modules to `lint:js` (it already covers `assets/interactivity`) and extend
  `tools/check-architecture.mjs` to fail on new jQuery use in learner code.
