# Learner front end: script map and Interactivity migration plan

Status: batch L1 (foundation and map) and batch L2 (shared helpers and page chrome) done on
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
public `ohmylms_lesson_access_nonce` (nonce `lesson_access_nonce`). Remove them, with their localized nonces,
in batch L4 and L6 if nothing outside the plugin uses them.

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
| Load more courses | `button.ohmylms-course-loadmore-btn` (`data-total`, `data-posts-per-page`), appends to `.ohmylms-course-cards` | `loop/load-more.php` | `ohmylms_loadmore` → `Ajax::loadmore()` (nonce `load_more_nonce`), returns HTML | Live |
| Filter, search, sort, category | `.ohmylms-checkbox input[name="ohmylms-filter-checkbox"]` (`data-type`, `data-slug`), `.clear-filter`, `input[name="course-search"]` (every keyup, no debounce), `.course-sort`, `.ohmylms-category-filter` | `filters/filters.php`, `filter-header.php`, `course-search.php`, `course-sort.php`, `search-sort.php`, `category-type-button.php` | `ohmylms_search_filter` → `Ajax::search_filter()` (nonce `search_filter_nonce`), returns HTML; the category branch re-creates the carousel (carousel owner) | Live |
| Filter checkbox keyboard, category button active state | `.ohmylms-filter-accordion .checkedbox` (Enter), `.ohmylms-category-type-button li` | `filters/filters.php`, `filters/category-type-button.php` | — | Live |
| Filter drawer on mobile | `.filter-hamburger`, `.ohmylms-course-sidebar`, `.ohmylms-close-filter` | `filters/search-sort.php`, `filters/filter-header.php` | — | Live |
| Course card hover popup (grid style 3) | `.grid-style3 .ohmylms-course-cards-carousel .course-card`, `.ohmylms-course-card-popup[data-item-id]` | `course-popup-content.php`, `layouts/grid-style3.php` | — | Live (bound once at load; cards added by load more get no popup) |
| Archive carousels | `.ohmylms-course-cards-carousel` | `loop/loop-start.php` | — | Carousel owner (Slick replacement) |
| Accordions | `.ohmylms-filter-accordion`, `.ohmylms-lesson-sidebar-accordion` `.ohmylms-accordion-head` (+ keyboard) | `filters/filters.php`, `single-lesson/sidebar/sidebar.php` | — | Superseded by `ohmylms/ui` when rendered by `ohmylms_get_template()` |
| Responsive tables | `.ohmylms-table .ohmylms-td-handle` (bound only if the window is under 768 px at load), `.dashboard-table-tr .table-accordion-handler` | `single-lesson/content-quiz.php`; `profile/membership.php`, `membership-details.php`, `invoice-details.php`, `transactions-history.php` | — | Superseded by `ohmylms/ui` (same rule) |
| Lesson sidebar tabs | `.ohmylms-lesson-tab-nav li`, `.ohmylms-lesson-single-tab-content` | `single-lesson/sidebar/sidebar.php` | — | Live (vanilla DOM) |
| My courses tabs | `.ohmylms-my-courses-tab ul li button` (arrow keys) | `profile/my-courses.php`, `Shortcodes\ShortCodeMyCourses` | — | Live; `ohmylms/tabs` can replace it |
| Toast | `#ohmylms-toast`, `.ohmylms-message`, `#ohmylms-close-toast` | `global/toast.php` | — | **Migrated (L2)** to `ohmylms/ui`. The remaining jQuery callers (login, sign-up, lesson complete, assignment) go through a small shim that dispatches `ohmylms:toast` |
| Header avatar dropdown | `.ohmylms-header .ohmylms-user-avatar`, body click closes | `global/main-header.php` | — | **Migrated (L2)** to `ohmylms/ui` dropdown actions |
| Profile sidebar drawer | `.ohmylms-student-profile .ohmylms-hamburger`, `.ohmylms-student-profile-sidebar` | `profile/profile-layout.php`, `profile/navigation.php`, `Shortcodes\ShortCodeProfile`, `student-hub/workspace.php` | — | **Migrated (L2)** to `ohmylms/ui` drawer actions (the hub's legacy wrapper has no toggle and stays static) |
| Cover and profile photo | `#cover_image_input`, `#cover-image-delete`, `#profile_photo_input`, `.cover-edit`/`.edit-avatar` (keyboard) | `profile/profile-edit.php` | `ohmylms_student_cover_image_upload`, `ohmylms_student_cover_image_delete`, `ohmylms_student_profile_image_upload` → `Ajax::student_cover_image_upload/delete()`, `Ajax::student_profile_image_upload()` (nonces `student_profile_cover_*`, `student_profile_image_upload_nonce`, localized only on the profile shortcode and the hub) | Live |
| Notification switches | `.single-notification-settings .ohmylms-switcher input` (value on/off) | `profile/notifications.php` | form post | Live |
| Membership "View plan" dialog | `.ohmylms-dashboard-membership .view-plan`, `.ohmylms-membership-modal`, `.close-modal`, Escape | `profile/loop/membership-card.php` (from `profile/student-dashboard.php`) | — | **Broken**: the card sits in `section.oml-dashboard-card`, not `.ohmylms-dashboard-membership`, so the dialog never opens. Fix the selector (`.ohmylms-dashboard-single-membership .view-plan`) in L4 |
| Cancel membership | `.do-membership-cancel` → `.ohmylms-alert`; `.ohmylms-cancel-membership` (`data-subscription-id`, `data-membership-id`, `data-order-id`) | `profile/membership.php` | `ohmylms_cancel_membership` → `Ajax::cancel_membership()` (nonce `cancel_membership_nonce`), then reload | Live; see findings |
| Invoice print | `.ohmylms-membership-invoice .print-button` (replaces `<body>`, prints, reloads) | `profile/membership-details.php`, `profile/invoice-details.php` | — | Live; replace with `@media print` CSS and `window.print()` |
| Login and signup | `.ohmylms-form-login`, `.ohmylms-form-signup` (serialized, hidden `action`), `a.ohmylms-show-signup-form`, `a.ohmylms-show-login-form`, `#ohmylms-checkout-form` hidden on switch | `global/form-login.php`, `global/form-signup.php` (through `ohmylms_login_form()`, `ohmylms_signup_form()` and the checkout variants in `Utility/template-functions.php`) | `ohmylms_login` → `Ajax::login()`, `ohmylms_signup` → `Ajax::signup()` (form nonces); 8-character client check | Live |
| Form helpers | `.ohmylms-input-text` label folding, `.show-password-icon`, `.ohmylms-form-login-rememberme` and `.ohmylms-tnc-label` (Enter), `input[name='tnc-accept']` enables submit, `input[name="phone"]`/`[name="whatsapp"]` character filter | `global/form-login.php`, `global/form-signup.php`, `profile/profile-edit.php`, `profile/settings.php`, `checkout/terms.php`, `student-hub/settings.php`, checkout shortcode/block/widgets | — | **Migrated (L2)** to `ohmylms/forms`. Login/sign-up submission and the show-login/show-signup links stay in jQuery for L5 |
| Email verification | URL params `ohmylms_email_verified`, `ohmylms_verify_sent`, `ohmylms_verify_error` → toast; `a[href*="ohmylms_resend_verification"]` | `Services\EmailVerificationService` notices | GET `ohmylms_resend_verification` with `format=json` → `Ajax::resend_verification()` | **Migrated (L2)** to `ohmylms/ui` (`installChrome()`) |
| Review stars and validation | `.comment-form-rating .ohmylms-stars > a` → `select#rating`; `.ohmylms-course-reviews-form #submit` | `single-course/tabs/reviews.php` (from `student-hub/course.php`) | comment form post | Live |
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
`assignment_submission_nonce`, `save_remaining_time`, `lesson_access_nonce`, `quiz_exit_submission`,
`video_progress_nonce`; `course_drop_nonce`, the three profile image nonces and `cancel_membership_nonce` on the
pages listed in that method. `BlocksManager` and `ElementorManager` localize their own copies.

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
(inspector controls plus `ServerSideRender`); they run no learner behaviour. The front-end markup they preview
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

- **`Ajax::cancel_membership()` trusts the posted `student_id`, `subscription_id` and `order_id`.** Any
  logged-in user with the nonce (printed on dashboard pages) can cancel another learner's subscription and
  enrolment. Fix before or with L4: use `get_current_user_id()` and check that the subscription and order belong
  to that user.
- The membership "View plan" dialog never opens (selector mismatch above).
- When an assignment countdown reaches zero, `ohMyLmsCountdown()` calls `saveQuizExiSubmit()`, which posts
  `ohmylms_quiz_exit_submission` with no quiz id; the server rejects it. L6 should give assignments their own
  expiry path (or none: the server already stores the deadline).
- `video-progress-tracker.js` depends on `ohmylms_frontend_params` without declaring `ohmylms-frontend`.
- The public stores `ohmylms/tabs` and `ohmylms/curriculum` have no markup in the plugin since the old single
  course layouts went; keep them (documented add-on contract) and reuse them in L4.
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

### L3: catalogue search, filter, sort and load more

- Files: `OhMyLMS.load_more`, `OhMyLMS.filter`, filter keyboard, filter drawer, category buttons, card popup;
  `templates/filters/*.php`, `loop/load-more.php`, `course-popup-content.php`; `Ajax::loadmore()`,
  `Ajax::search_filter()`.
- Store: `ohmylms/catalog`. Prefer `@wordpress/interactivity-router` with real GET parameters
  (`?course_search=…&course_category[]=…&paged=…`) so filtered lists are linkable and work without JavaScript;
  keep the two AJAX actions as a fallback until the router region renders server-side.
- Directives: `data-wp-router-region` on the card list, `data-wp-on--change` on checkboxes and sort,
  debounced `data-wp-on--input` on search, `data-wp-bind--hidden` on the load-more area, `data-wp-on--mouseenter`
  on cards for the popup. Coordinate with the carousel owner for the category view.
- Tests: PHP test that the archive with query arguments renders the same cards as `Ajax::search_filter()`;
  `tests/js/catalog.test.mjs` for state (filters to query string, page reset, last page); Playwright for search
  debounce and load more.

### L4: learner dashboard and profile

- Files: cover and profile photo, notification switches, my-courses tabs, membership "View plan" dialog
  (fix), cancel membership confirm, invoice print, dashboard table rows; `templates/profile/*.php`;
  `Ajax::student_*_image_*()`, `Ajax::cancel_membership()` (fix ownership first), `Ajax::drop_course()`
  (remove if unused).
- Stores: `ohmylms/account`; reuse `ohmylms/tabs` for my-courses and native `<dialog>` with `ohmylms/ui`.
- Directives: `data-wp-on--change` on file inputs with `data-wp-bind--src` on images, `data-wp-context` per
  membership card, `data-wp-on--click` for print (CSS `@media print` instead of rewriting `<body>`).
- Tests: PHP integration test for `cancel_membership` ownership (another user's subscription is refused);
  `tests/js/account.test.mjs` for the image upload state; extend `tests/browser/account-blocks.spec.cjs`.

### L5: authentication forms and reviews

- Files: `OhMyLMS.login`, `OhMyLMS.signup`, show-login/show-signup links, review stars and validation;
  `global/form-login.php`, `global/form-signup.php`, `single-course/tabs/reviews.php`; `Ajax::login()`,
  `Ajax::signup()`. The checkout login reuses these forms: coordinate with the commerce owner.
- Stores: `ohmylms/forms` (from L2), `ohmylms/reviews`.
- Directives: `data-wp-on--submit` on the forms, `data-wp-class--is-busy`, `data-wp-text` for errors in an
  `aria-live` region instead of toasts; radio inputs for stars (no `<a href>`), `data-wp-bind--disabled` on submit.
- Tests: PHP tests for `Ajax::login()`/`signup()` JSON contracts (success, error, `pending_verification`);
  `tests/js/forms.test.mjs`; Playwright login, signup with verification required, review submit.

### L6: lesson and course player and progress

- Files: mark complete, assignment submission, attachments, timer and deadline, prerequisite dialog, lesson
  sidebar tabs, quiz text fields (into `ohmylms/questions`), content images (move to PHP), content protection;
  `single-lesson/content-navigation.php`, `content-assignment.php`, `sidebar/sidebar.php`, `content-lesson.php`;
  `Ajax::lesson_completed()`, `Ajax::save_assignment_submission_file()`, `Ajax::save_remaining_time()`,
  `Ajax::lesson_access_nonce()` (remove if unused).
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
