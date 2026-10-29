# State and API modularization (roadmap step 4)

**Rebuild update, 30 October 2026:** this document analyzes the recovered store. Its W1/W2 adapter
proposal below is historical, not the active new-app plan. The new Webhooks screen in
`assets/src/app/features/webhooks` uses typed API calls and cancellable page-owned state directly;
it does not wire the recovered slice into the new application. See [ARCHITECTURE.md](ARCHITECTURE.md)
and [the current roadmap](MODULARIZATION-ROADMAP.md) for the rebuild and stored-data policy.

Status: analysis and design, plus a first slice (`webhooks`) that is **not wired in**. Nothing under
`assets/dist`, `tools/` or the existing feature files was changed.

Scope: the recovered application data store and REST client, webpack factory 86169
(`assets/src/modules/dist/admin/ohmylms/0-86169.js`, binding `T`). Every number below comes from a
static scan of the sources in this checkout (Babel AST plus grep), run on the date of writing. Items
that were not run in a browser or against a WordPress site are listed under
[Not verified](#9-not-verified).

## 1. Summary

- Factory 86169 has **one export, `default`**: a `@wordpress/data` store created with
  `createReduxStore( 'ohmylms/store', { reducer, actions, selectors } )` and registered as a side
  effect of loading the factory (lines 13642 and 13673). There are no named exports. (`getLmsUtilityData`
  is an *action* of that store, read by `assets/src/application/shared/automation-canvas-runtime.js`
  through `useDispatch( T.default )`.)
- The root reducer combines **21 slices (150 top-level state keys)**. The flat `actions` object merges
  22 namespaces (302 entries: 295 action creators and 7 exported action-type strings) and the flat
  `selectors` object merges 22 namespaces (159 entries). There are no resolvers, no controls and no
  store-level thunk dependencies: thunks only use the `{ dispatch }` argument.
- **112 `apiFetch` call sites** (111 in the factory, 1 in factory 26456) reach `ohmylms/v1/*` and, for
  orders and subscriptions, the commerce namespace. 17 of them target routes that no PHP controller in
  `includes/` or `packages/e-commerce/` registers (section 3.2).
- **116 authored JSX files in 17 features** read the store (`commerce-reference` is ignored). They use
  203 distinct members (of 449 non-constant members); a further 54 members are called only from the
  recovered application fragments, and 192 members are not called from outside the store at all
  (they may still be dispatched by other thunks).
- Cross-slice coupling is the main obstacle to splitting the store: 13 slices dispatch the
  `notification` action, 7 dispatch the `loader` actions, and the course/chapter/lesson/assignment/quiz/question
  slices call each other (section 2.4). `webhooks` is one of the most isolated slices: it only reaches
  `notification`.

## 2. Factory 86169, decomposed

### 2.1 Shape

| Item | Fact |
| --- | --- |
| Size | 13,675 lines, 417,027 bytes |
| Exports | `default: () => qh` where `qh = Uh`, the store descriptor. No other export |
| Store | `Uh = createReduxStore( 'ohmylms/store', { reducer, actions: Gh, selectors: Hh } )`, then `register( Uh )` |
| Reducer | One function returning `{ app, dashboard, course, membership, lesson, chapter, notification, loader, quiz, question, settings, order, assignment, emails, certificate, students, integrations, subscription, sessions, community, webhooks }`, each computed as `sliceReducer( state.slice, action )` |
| Actions `Gh` | Spread of 22 namespaces in this order: app, dashboard, course, membership, chapter, lesson, notification, loader, quiz, question, **settings (factory 26456)**, order, assignment, emails, certificate, students, (empty), integrations, subscription, sessions, community, webhooks |
| Selectors `Hh` | Spread of 22 namespaces: app, dashboard, course, membership, lesson, chapter, notification, loader, quiz, question, settings, order, assignment, emails, certificate, students, (empty), integrations, subscription, sessions, community, webhooks |
| Resolvers, controls | None |
| Thunks | Redux-thunk style functions returning `async ({ dispatch }) => ...`, compiled to regenerator state machines. No thunk reads `select`, `getState`, `registry` or `resolveSelect` |
| Requires | `wp.data` (37562), `wp.i18n` (12470), `wp.apiFetch` (12842), `wp.url` (24011), settings action creators (26456, which requires constants module 45050), question validation (5836), question ordering (16118), empty module 77558, and 93509 (imported as `fp`, never referenced; it contains sample student records) |
| Consumers | Authored features: `T.default` through `readRuntime()`. Recovered application: fragments under `assets/src/application/` (`T.default`), factories 13567 and 83193 (`i.default`), and the string name `'ohmylms/store'` in `course-builder-chapter-editor.js`, `course-preview-card.js`, `live-session-chapter-content-items.js`, `quiz-editor-header.js`, `quiz-question-option-settings.js`, `schedule-date-picker.js` and the authored `features/courses/CourseChapter.jsx` |

The store is addressed **by name** in places already, which is what makes a name-based migration
(section 5.4) possible.

### 2.2 Slices: state keys, members, requests and users

"Not called outside the store" counts members that no authored feature and no recovered application
fragment calls through the store API. Other thunks may still dispatch them, so it is *not* a list of
removable members. "Recovered application only" counts members called by `assets/src/application/**` or
factories 13567/83193 but by no authored feature.

| State key | Reducer (line) | Top-level state keys | Actions | Selectors | REST requests | Features reading it | Not called outside the store | Recovered application only |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `app` | `de` (L645) | 10: isShowProModal, proModalTitle, proModalContent, proModalButtonText, proModalButtonAction, automationFor, categories, integrations, currency_settings, setupWizard | 11 | 13 | 2 | setup, gamification, settings, taxonomies, courses, integrations, learning, webhooks | 8 | 4 |
| `dashboard` | `dt` (L1039) | 8: loader, overview, course, review, sales, students, allData, filter | 8 | 8 | 0 | analytics | 10 | 3 |
| `course` | `En` (L1311) | 20: courses, pagination, error, loading, course, chapters, contents, categories, tags, currentChapterId, currentContentID, perPage, course_map, settings, isValidSettings, totalCoursesNumber, courseSidebarOpen, courseChapterSidebarOpen, courseChapterContentSidebarOpen, courseInfoOpen | 59 | 25 | 24 | courses, certificates, learning, memberships, analytics, settings, setup, question-editor, quiz-editor | 33 | 29 |
| `membership` | `Ki` (L5728) | 4: memberships, membershipDetails, membershipPlan, pagination | 16 | 5 | 7 | memberships | 10 | 0 |
| `lesson` | `To` (L4196) | 6: lessons, pagination, selectedLessonId, lesson, error, loading | 24 | 6 | 17 | learning | 15 | 1 |
| `chapter` | `qa` (L3491) | 5: chapters, error, loading, chapter, chapter_map | 9 | 3 | 5 | courses | 11 | 0 |
| `notification` | `cl` (L5832) | 3: message, status, visible | 4 | 4 | 0 | analytics, commerce, courses, learning, question-editor, certificates, gamification, integrations, memberships, settings | 3 | 0 |
| `loader` | `yl` (L5911) | 1: isLoading | 4 | 1 | 0 | learning | 2 | 0 |
| `quiz` | `Dl` (L6022) | 10: quizTypes, interactiveQuizTypes, isOpenQuizBuilder, pagination, selectedQuizId, quiz, quizzes, contents, error, loading | 14 | 10 | 5 | learning, quiz-editor, question-editor, courses | 4 | 8 |
| `question` | `ru` (L6923) | 6: selectedQuestionId, question, questions, error, loading, errors | 13 | 5 | 2 | question-editor, quiz-editor | 3 | 3 |
| `settings` | `bu` (L7125) | 15: general, design, payment, currency, tax, accountPrivacy, permalink, advanced, migrationToolCourses, migrationCourses, isLoadingSetting, isMigrationModalOpen, migrationStatus, migratedCourses, migrationTool | 25 | 15 | 1 | settings, setup, gamification | 2 | 1 |
| `order` | `Xu` (L7358) | 11: orders, error, loading, order, page, statusCount, activeStatus, refund, pagination, note, currency | 16 | 8 | 7 | commerce | 10 | 0 |
| `assignment` | `rd` (L8226) | 6: assignments, pagination, selectedAssignmentId, assignment, error, loading | 18 | 6 | 10 | learning | 13 | 1 |
| `emails` | `em` (L9166) | 4: email, emails, settings, loading | 12 | 5 | 5 | emails | 5 | 0 |
| `certificate` | `Nm` (L9647) | 6: certificates, certificate, settings, isLoading, pagination, totalCertificates | 12 | 6 | 4 | certificates | 8 | 1 |
| `students` | `pp` (L10127) | 4: allStudents, student, loader, pagination | 7 | 4 | 2 | students | 6 | 3 |
| `integrations` | `af` (L10522) | 1: integrations | 3 | 1 | 3 | integrations | 1 | 0 |
| `subscription` | `Wh` (L13546) | 9: subscriptions, error, loading, subscription, page, statusCount, activeStatus, pagination, currency | 9 | 10 | 3 | commerce | 11 | 0 |
| `sessions` | `Mf` (L11030) | 6: sessions, error, loading, session, page, pagination | 9 | 7 | 3 | learning | 11 | 0 |
| `community` | `Ev` (L11657) | 8: communities, community, loading, error, total, totalPages, currentPage, perPage | 9 | 8 | 5 | communities | 13 | 0 |
| `webhooks` | `vg` (L12393) | 7: webhooks, webhook, triggers, loading, total, totalPages, formData | 17 | 7 | 7 | webhooks | 13 | 0 |

State keys per slice are the top-level keys of the reducer's default state (nested keys omitted).
Counts of actions include the exported action-type constants (7: `CLEAR_MEMBERSHIP_DETAILS`,
`SET_MEMBERSHIPS`, `SET_MEMBERSHIP_DETAILS`, `HIDE_NOTIFICATION`, `SHOW_NOTIFICATION`, `START_LOADING`,
`STOP_LOADING`) for `membership`, `notification` and `loader`. The `settings` slice has 25 action
creators in factory 26456: 24 plain ones and the thunk `migrateSingleCourse`, which makes the only REST request there
(`POST /ohmylms/v1/migrations/{id}`). Settings persistence is otherwise done outside the store (not traced here).

Reducer type strings: 177 `case` labels. Two type strings are handled by **two slices**, so one
dispatched action changes both: `SET_FEATURED_IMAGE` (course, chapter) and `CHANGE_PAGE` (order,
sessions). The 24 settings labels are imported constants (module 45050) and were not resolved to
strings.

### 2.3 Flat-namespace collisions and dead references

Because `actions` and `selectors` are flat spreads, later namespaces replace earlier ones with the same
name:

- Actions: `setPage` (order, **sessions wins**), `fetchCoursesFailure` (course, **chapter wins**),
  `setContentsToChapter` (lesson, **assignment wins**).
- Selectors: `getPage` (order, **sessions wins**), `getError` (course, **sessions wins**).
- `getWebhookTriggers` exists as both an action and a selector; `@wordpress/data` keeps those apart.
- Members used but not defined: `updateQuestion` (action; `question-editor/TrueFalseEditor.jsx` line 31
  and two recovered fragments) and `getQuestions` (recovered fragments). The store only defines
  `updateQuestionData`.
- The thunk `bulkWebhookAction` text map is keyed `activate`/`deactivate` while the UI and server use
  `active`/`inactive` (section 6.3).

A composed store must keep the merge order or the callers see different functions.

### 2.4 Cross-slice dependencies

Distinct "slice A's thunks reference slice B's action creators" edges (count of distinct references):

- `notification` is dispatched by course (13), lesson (10), membership (7), assignment (6), webhooks (6),
  community (5), chapter (3), certificate (3), emails (2), quiz (2), students (2), subscription (2),
  order (1).
- `loader` by course (10), lesson (10), assignment (10), community (10), membership (14), chapter (4),
  quiz (3).
- Between content slices: chapter -> course (3), lesson -> chapter (3), lesson -> course (1),
  assignment -> chapter (1), assignment -> course (1), quiz -> chapter (1), quiz -> question (6),
  integrations -> app (1).

A slice that only reaches `notification` (webhooks, certificate, emails, students, order, subscription)
can move on its own. The content slices have to move together or call each other through the
registry.

### 2.5 Other facts worth knowing

- Only the webhooks section calls `showNotification` with an **object** (`Tn( { message, type } )`, 13
  call sites). The other 96 `Tn(` call sites are in the form `Tn( message, status )`. The result is that a webhooks error is stored
  with `status: 'success'` and `message: { message, type }`. No webhooks file selects the notification
  state, so nothing in the webhooks feature shows these; whether the application shell shows them was not
  checked.
- `exportCoursesAsScorm` does not use `apiFetch`: it opens `/wp-json/ohmylms/v1/{entity}/export-scorm?...`
  with `window.open` and `_wpnonce: ohmylms_params.nonce` (hard-coded `/wp-json/` prefix, so it fails on
  plain-permalink sites).
- `saveSetup` posts with a custom `nonce` header (`ohmylms_params.setup_wizard_nonce`) and a pre-serialised
  `body`; most other thunks pass `data`, some pass `body: JSON.stringify(...)` with explicit headers.
- Orders and subscriptions call `/cx-ecommerce/v1/...` in the recovered source. `tools/commerce-adapters.mjs`
  rewrites those string literals to `ohmylms-commerce/v1` during the build (see
  `docs/CREATORLMS-COEXISTENCE.md`). Anything that replaces those sections must use the new namespace
  directly and must not depend on that rewrite running in a particular order.
- The webhooks thunks `console.error`, show a notification and rethrow the original error object; the
  authored slice keeps the notification and the rethrow (section 6.3).

## 3. REST requests

### 3.1 Request map

Every `apiFetch` call site in factory 86169 (plus the one in factory 26456), the route it requests, and the
PHP controller that registers it. The controller column was produced by matching the route against every
`register_rest_route()` call under `includes/` and `packages/e-commerce/`, resolving `$this->base` style
prefixes, and reading the permission callbacks named in the registration. The capability in brackets is
what `current_user_can()` checks in the callback body (or the helper it calls) as found by the scan;
object-level checks inside the request callbacks were not audited. Where the path is built from a variable
(`{}`), every route that matches is listed. Orders and subscriptions are shown with the namespace they
have after `tools/commerce-adapters.mjs` runs (the recovered source says `cx-ecommerce/v1`).

| Slice | Store member(s) | Request | Controller (file:line) and permission callback |
| --- | --- | --- | --- |
| app | `getLmsUtilityData` | `GET /ohmylms/v1/app/data` | `AppController.php:42` get_items_permissions_check (`edit_posts`) |
| app | `saveSetup` | `POST /ohmylms/v1/setup-wizard` | `SetupWizardController.php:74` check_setup_permission (`edit_posts`) |
| course | `fetchCourses` | `GET /ohmylms/v1/courses` | `CourseController.php:94` check_course_permission (`edit_posts`) |
| course | `saveCourseChapters`, `saveCourseChaptersWithoutNotice`, `updateChapterOrdersAPI` | `POST /ohmylms/v1/courses/{}/chapters` | `CourseController.php:301` check_course_permission (`edit_posts`) |
| course | `saveCourseCategory` | `POST /ohmylms/v1/categories` | `CategoryController.php:58` get_items_permissions_check (`edit_posts`) |
| course | `getTags` | `GET /ohmylms/v1/tags` | `TagController.php:56` get_items_permissions_check (`edit_posts`) |
| course | `saveCourseTag` | `POST /ohmylms/v1/tags` | `TagController.php:56` get_items_permissions_check (`edit_posts`) |
| course | `updateStatus` | `POST /ohmylms/v1/courses/{}/status` | `CourseController.php:147` check_course_permission (`edit_posts`) |
| course | `getCourse` | `GET /ohmylms/v1/courses/{}` | `CourseController.php:123` check_course_permission (`edit_posts`); check_course_delete_permission (`edit_posts`) |
| course | `fetchCourseChapters` | `GET /ohmylms/v1/courses/{}/chapters` | `CourseController.php:301` check_course_permission (`edit_posts`) |
| course | `createCourse` | `POST /ohmylms/v1/courses` | `CourseController.php:94` check_course_permission (`edit_posts`) |
| course | `updateCourse` | `POST /ohmylms/v1/courses/{}` | `CourseController.php:123` check_course_permission (`edit_posts`); check_course_delete_permission (`edit_posts`) |
| course | `updateCourseWithoutNotice` | `POST /ohmylms/v1/courses/{}` | `CourseController.php:123` check_course_permission (`edit_posts`); check_course_delete_permission (`edit_posts`) |
| course | `deleteCourse` | `DELETE /ohmylms/v1/courses/{}` | `CourseController.php:123` check_course_permission (`edit_posts`); check_course_delete_permission (`edit_posts`) |
| course | `getCourseMap` | `GET /ohmylms/v1/course/get-map/{}` | **no route registered** (static match) |
| course | `fetchCourseSettings` | `GET /ohmylms/v1/course/settings/{}` | **no route registered** (static match) |
| course | `saveCourseSettings` | `POST /ohmylms/v1/course/settings/{}` | **no route registered** (static match) |
| course | `setFeaturedImage` | `POST /ohmylms/v1/courses/featured_image/{}` | `CourseController.php:396` check_course_permission (`edit_posts`) |
| course | `removeFeaturedImage` | `DELETE /ohmylms/v1/courses/featured_image/{}` | `CourseController.php:396` check_course_permission (`edit_posts`) |
| course | `getCategories` | `GET /ohmylms/v1/categories` | `CategoryController.php:58` get_items_permissions_check (`edit_posts`) |
| course | `deleteCourseCategory` | `DELETE /ohmylms/v1/categories/{}` | `CategoryController.php:75` get_items_permissions_check (`edit_posts`) |
| course | `deleteCourseTag` | `DELETE /ohmylms/v1/tags/{}` | `TagController.php:73` get_items_permissions_check (`edit_posts`) |
| course | `handleBulkDelete` | `DELETE /ohmylms/v1/{}/trash-bulk` | `AssignmentController.php:128` check_assignment_permission (`edit_posts`)<br>`CertificateController.php:78` check_certificate_permission (`edit_posts`)<br>`CourseController.php:278` check_course_permission (`edit_posts`)<br>`LessonController.php:86` check_lesson_permission (`edit_posts`)<br>`MembershipController.php:131` check_membership_permission (`edit_posts`)<br>`QuizController.php:213` check_quiz_permission (see AccessPolicy) |
| course | `exportCourses` | `GET /ohmylms/v1/{}/export` | `CourseController.php:157` check_course_permission (`edit_posts`)<br>`QuestionBankController.php:59` author_permission (see AccessPolicy) |
| course | `importCourse` | `POST /ohmylms/v1/{}/import` | `CourseController.php:256` check_course_permission (`edit_posts`)<br>`QuestionImportController.php:47` can_author (`unresolved can_author`)<br>`QuizController.php:177` check_quiz_permission (see AccessPolicy)<br>`SyllabusController.php:55` closure/other: $admin |
| course | `importScormCourse` | `POST /ohmylms/v1/{}/import-scorm` | `CourseController.php:266` check_course_permission (`edit_posts`) |
| chapter | `fetchChapters` | `GET /ohmylms/v1/courses/{}/chapters` | `CourseController.php:301` check_course_permission (`edit_posts`) |
| chapter | `saveChapterContent` | `POST /ohmylms/v1/chapters/{}/content` | `ChapterController.php:119` check_chapter_permission (`edit_posts`) |
| chapter | `deleteChapter` | `DELETE /ohmylms/v1/chapters/{}` | `ChapterController.php:96` check_chapter_permission (`edit_posts`) |
| chapter | `addChapter` | `POST /ohmylms/v1/chapters` | `ChapterController.php:73` check_chapter_permission (`edit_posts`) |
| chapter | `editChapter` | `POST /ohmylms/v1/chapter/update/{}` | **no route registered** (static match) |
| lesson | `fetchLessons` | `GET /ohmylms/v1/lessons` | `LessonController.php:74` check_lesson_permission (`edit_posts`) |
| lesson | `addLesson` | `POST /ohmylms/v1/lessons` | `LessonController.php:74` check_lesson_permission (`edit_posts`) |
| lesson | `getLesson` | `GET /ohmylms/v1/lessons/{}` | `LessonController.php:116` check_lesson_permission (`edit_posts`) |
| lesson | `getLessonByChapterId` | `GET /ohmylms/v1/chapters/{}/content` | `ChapterController.php:119` check_chapter_permission (`edit_posts`) |
| lesson | `updateLesson` | `POST /ohmylms/v1/lessons/{}` | `LessonController.php:116` check_lesson_permission (`edit_posts`) |
| lesson | `updateLessonWithoutNotice` | `POST /ohmylms/v1/lessons/{}` | `LessonController.php:116` check_lesson_permission (`edit_posts`) |
| lesson | `editLesson` | `POST /ohmylms/v1/lesson/update/{}` | **no route registered** (static match) |
| lesson | `saveLessonContent` | `POST /ohmylms/v1/lesson/content/save/{}` | **no route registered** (static match) |
| lesson | `fetchLessonContent` | `GET /ohmylms/v1/lesson/content/{}` | **no route registered** (static match) |
| lesson | `deleteLesson` | `DELETE /ohmylms/v1/lessons/{}` | `LessonController.php:116` check_lesson_permission (`edit_posts`) |
| lesson | `deleteLessonFromChapter` | `DELETE /ohmylms/v1/chapters/{}/content/{}` | `ChapterController.php:130` check_content_delete_permission (`edit_posts`) |
| lesson | `addZoomClass` | `POST /ohmylms/v1/session` | `SessionController.php:102` check_session_permission (`edit_posts`) |
| lesson | `updateZoomClass` | `POST /ohmylms/v1/session/{}` | `SessionController.php:132` check_session_edit_permission (`edit_post(obj)`); check_session_delete_permission (`delete_post(obj)`); check_session_read_permission (`edit_posts, read_post(obj)`) |
| lesson | `getZoomClass` | `GET /ohmylms/v1/session/{}` | `SessionController.php:132` check_session_edit_permission (`edit_post(obj)`); check_session_delete_permission (`delete_post(obj)`); check_session_read_permission (`edit_posts, read_post(obj)`) |
| lesson | `addGoogleMeetClass` | `POST /ohmylms/v1/session` | `SessionController.php:102` check_session_permission (`edit_posts`) |
| lesson | `updateGoogleMeetClass` | `POST /ohmylms/v1/session/{}` | `SessionController.php:132` check_session_edit_permission (`edit_post(obj)`); check_session_delete_permission (`delete_post(obj)`); check_session_read_permission (`edit_posts, read_post(obj)`) |
| lesson | `getGoogleMeetClass` | `GET /ohmylms/v1/session/{}` | `SessionController.php:132` check_session_edit_permission (`edit_post(obj)`); check_session_delete_permission (`delete_post(obj)`); check_session_read_permission (`edit_posts, read_post(obj)`) |
| membership | `fetchMemberships` | `GET /ohmylms/v1/membership` | `MembershipController.php:119` check_membership_permission (`edit_posts`) |
| membership | `fetchMembershipDetails` | `GET /ohmylms/v1/membership/{}` | `MembershipController.php:160` check_membership_read_permission (`edit_posts, read_post(obj)`); check_membership_edit_permission (`edit_post(obj)`); check_membership_delete_permission (`delete_post(obj)`) |
| membership | `saveMembershipDetails` | `PUT or POST /ohmylms/v1/membership/save` | **no route registered** (static match) |
| membership | `deleteMembership` | `DELETE /ohmylms/v1/membership/delete/{}` | **no route registered** (static match) |
| membership | `addMembershipPlan` | `POST /ohmylms/v1/membership{}` | `MembershipController.php:119` check_membership_permission (`edit_posts`)<br>`MembershipController.php:160` check_membership_read_permission (`edit_posts, read_post(obj)`); check_membership_edit_permission (`edit_post(obj)`); check_membership_delete_permission (`delete_post(obj)`) |
| membership | `deleteMembershipPlan` | `DELETE /ohmylms/v1/membership/{}` | `MembershipController.php:160` check_membership_read_permission (`edit_posts, read_post(obj)`); check_membership_edit_permission (`edit_post(obj)`); check_membership_delete_permission (`delete_post(obj)`) |
| membership | `fetchMembershipPlans` | `GET /ohmylms/v1/membership` | `MembershipController.php:119` check_membership_permission (`edit_posts`) |
| question | `addQuestion` | `POST /ohmylms/v1/question` | `QuestionController.php:88` check_question_permission (see AccessPolicy) |
| question | `deleteQuestion` | `DELETE /ohmylms/v1/question/{}` | `QuestionController.php:117` check_item_permission (see AccessPolicy) |
| quiz | `addQuiz` | `POST /ohmylms/v1/quiz` | `QuizController.php:142` check_quiz_permission (see AccessPolicy) |
| quiz | `fetchQuizzes` | `GET /ohmylms/v1/quiz` | `QuizController.php:142` check_quiz_permission (see AccessPolicy) |
| quiz | `getQuiz` | `GET /ohmylms/v1/quiz/{}` | `QuizController.php:261` check_item_permission (see AccessPolicy) |
| quiz | `updateQuiz` | `POST /ohmylms/v1/quiz/{}` | `QuizController.php:261` check_item_permission (see AccessPolicy) |
| quiz | `deleteQuiz` | `DELETE /ohmylms/v1/quiz/{}` | `QuizController.php:261` check_item_permission (see AccessPolicy) |
| order | `fetchOrders` | `GET /ohmylms-commerce/v1/orders` | `packages/e-commerce/OrdersController.php:52` check_order_permission (`edit_posts`) |
| order | `fetchOrder` | `GET /ohmylms-commerce/v1/orders/{}` | `packages/e-commerce/OrdersController.php:104` check_order_permission (`edit_posts`) |
| order | `updateOrder` | `POST /ohmylms-commerce/v1/orders/{}` | `packages/e-commerce/OrdersController.php:104` check_order_permission (`edit_posts`) |
| order | `issueRefund` | `POST /ohmylms-commerce/v1/orders/{}/refunds` | `packages/e-commerce/OrderRefundController.php:62` get_items_permissions_check (`unresolved get_items_permissions_check`); create_item_permissions_check (`edit_posts`) |
| order | `saveOrderNote` | `POST /ohmylms-commerce/v1/orders/{}/notes` | `packages/e-commerce/OrdersController.php:123` check_order_permission (`edit_posts`) |
| order | `trashSingleOrderAction` | `DELETE /ohmylms-commerce/v1/orders/{}` | `packages/e-commerce/OrdersController.php:104` check_order_permission (`edit_posts`) |
| order | `trashBulkOrdersAction` | `DELETE /ohmylms-commerce/v1/orders/trash-bulk` | `packages/e-commerce/OrdersController.php:64` check_order_permission (`edit_posts`) |
| assignment | `fetchAssignments` | `GET /ohmylms/v1/assignment` | `AssignmentController.php:116` check_assignment_permission (`edit_posts`) |
| assignment | `addAssignment` | `POST /ohmylms/v1/assignment` | `AssignmentController.php:116` check_assignment_permission (`edit_posts`) |
| assignment | `getAssignment` | `GET /ohmylms/v1/assignment/{}` | `AssignmentController.php:158` check_assignment_edit_permission (`edit_post(obj)`); check_assignment_delete_permission (`delete_post(obj)`); check_assignment_read_permission (`edit_posts, read_post(obj)`) |
| assignment | `getAssignmentByChapterId` | `GET /ohmylms/v1/chapters/{}/content` | `ChapterController.php:119` check_chapter_permission (`edit_posts`) |
| assignment | `updateAssignment` | `POST /ohmylms/v1/assignment/{}` | `AssignmentController.php:158` check_assignment_edit_permission (`edit_post(obj)`); check_assignment_delete_permission (`delete_post(obj)`); check_assignment_read_permission (`edit_posts, read_post(obj)`) |
| assignment | `editAssignment` | `POST /ohmylms/v1/assignment/update/{}` | **no route registered** (static match) |
| assignment | `saveAssignmentContent` | `POST /ohmylms/v1/assignment/content/save/{}` | `AssignmentController.php:206` check_assignment_edit_permission (`edit_post(obj)`) |
| assignment | `fetchAssignmentContent` | `GET /ohmylms/v1/assignment/content/{}` | **no route registered** (static match) |
| assignment | `deleteAssignment` | `DELETE /ohmylms/v1/assignment/{}` | `AssignmentController.php:158` check_assignment_edit_permission (`edit_post(obj)`); check_assignment_delete_permission (`delete_post(obj)`); check_assignment_read_permission (`edit_posts, read_post(obj)`) |
| assignment | `deleteAssignmentFromChapter` | `DELETE /ohmylms/v1/chapters/{}/content/{}` | `ChapterController.php:130` check_content_delete_permission (`edit_posts`) |
| emails | `getEmailSettings` | `GET /ohmylms/v1/settings/email` | `SettingsController.php:65` get_items_permissions_check (`manage_options`); update_items_permissions_check (`manage_options`) |
| emails | `getAllEmails` | `GET /ohmylms/v1/all-emails` | `EmailController.php:77` get_items_permissions_check (`edit_posts`) |
| emails | `searchEmails` | `GET /ohmylms/v1/all-emails` | `EmailController.php:77` get_items_permissions_check (`edit_posts`) |
| emails | `updateEmailSettings` | `POST /ohmylms/v1/settings/email` | `SettingsController.php:65` get_items_permissions_check (`manage_options`); update_items_permissions_check (`manage_options`) |
| emails | `updateSingleEmail` | `POST /ohmylms/v1/email-settings/{}` | `EmailController.php:65` get_items_permissions_check (`edit_posts`) |
| certificate | `fetchCertificates` | `GET /ohmylms/v1/certificates` | `CertificateController.php:66` check_certificate_permission (`edit_posts`) |
| certificate | `deleteCertificate` | `DELETE /ohmylms/v1/certificates/{}` | `CertificateController.php:107` check_certificate_permission (`edit_posts`) |
| certificate | `getCertificateById` | `GET /ohmylms/v1/certificates/{}` | `CertificateController.php:107` check_certificate_permission (`edit_posts`) |
| certificate | `updateCertificate` | `PUT /ohmylms/v1/certificates/{}` | `CertificateController.php:107` check_certificate_permission (`edit_posts`) |
| students | `fetchStudents` | `GET /ohmylms/v1/students` | `StudentController.php:64` check_student_permission (`manage_options, manage_ohmylms`) |
| students | `fetchSingleStudent` | `GET /ohmylms/v1/students/{}` | `StudentProController.php:70` check_student_permission (`manage_options, manage_ohmylms`) |
| integrations | `getIntegrations` | `GET /ohmylms/v1/integrations` | `IntegrationsController.php:79` get_items_permissions_check (`manage_options`); update_items_permissions_check (`manage_options`) |
| integrations | `updateIntegrations` | `POST /ohmylms/v1/integrations` | `IntegrationsController.php:79` get_items_permissions_check (`manage_options`); update_items_permissions_check (`manage_options`) |
| integrations | `deleteIntegration` | `DELETE /ohmylms/v1/integrations/{}` | `IntegrationsController.php:98` delete_item_permissions_check (`manage_options`) |
| sessions | `fetchSessions` | `GET /ohmylms/v1/session` | `SessionController.php:102` check_session_permission (`edit_posts`) |
| sessions | `fetchSession` | `GET /ohmylms/v1/session/{}` | `SessionController.php:132` check_session_edit_permission (`edit_post(obj)`); check_session_delete_permission (`delete_post(obj)`); check_session_read_permission (`edit_posts, read_post(obj)`) |
| sessions | `updateSession` | `POST /ohmylms/v1/session/{}` | `SessionController.php:132` check_session_edit_permission (`edit_post(obj)`); check_session_delete_permission (`delete_post(obj)`); check_session_read_permission (`edit_posts, read_post(obj)`) |
| community | `fetchCommunities` | `GET /ohmylms/v1/communities` | **no route registered** (static match) |
| community | `fetchCommunitiesByCourseId` | `GET /ohmylms/v1/communities/space/course/{}` | **no route registered** (static match) |
| community | `fetchCommunityById` | `GET /ohmylms/v1/communities/{}` | **no route registered** (static match) |
| community | `deleteCommunities` | `DELETE /ohmylms/v1/community/spaces` | **no route registered** (static match) |
| community | `createCommunity` | `POST /ohmylms/v1/community/spaces{}` | **no route registered** (static match) |
| webhooks | `getWebhooks` | `GET /ohmylms/v1/webhooks` | `WebhookController.php:65` get_items_permissions_check (`manage_options`); create_item_permissions_check (`manage_options`) |
| webhooks | `createWebhook` | `POST /ohmylms/v1/webhooks` | `WebhookController.php:65` get_items_permissions_check (`manage_options`); create_item_permissions_check (`manage_options`) |
| webhooks | `updateWebhookById` | `PUT /ohmylms/v1/webhooks/{}` | `WebhookController.php:95` get_item_permissions_check (`manage_options`); update_item_permissions_check (`manage_options`); delete_item_permissions_check (`manage_options`) |
| webhooks | `deleteWebhookById` | `DELETE /ohmylms/v1/webhooks/{}` | `WebhookController.php:95` get_item_permissions_check (`manage_options`); update_item_permissions_check (`manage_options`); delete_item_permissions_check (`manage_options`) |
| webhooks | `bulkWebhookAction` | `POST /ohmylms/v1/webhooks/bulk` | `WebhookController.php:120` create_item_permissions_check (`manage_options`) |
| webhooks | `getWebhookTriggers` | `GET /ohmylms/v1/webhooks/triggers` | `WebhookController.php:133` get_items_permissions_check (`manage_options`) |
| webhooks | `testWebhook` | `POST /ohmylms/v1/webhooks/test` | **no route registered** (static match) |
| subscription | `fetchSubscriptions` | `GET /ohmylms-commerce/v1/subscriptions` | `packages/e-commerce/SubscriptionController.php:186` get_items_permissions_check (`manage_options`); create_item_permissions_check (`manage_options`) |
| subscription | `fetchSubscription` | `GET /ohmylms-commerce/v1/subscriptions/{}` | `packages/e-commerce/SubscriptionController.php:216` get_item_permissions_check (`manage_options`); update_item_permissions_check (`manage_options`) |
| subscription | `updateSubscription` | `POST /ohmylms-commerce/v1/subscriptions/{}` | `packages/e-commerce/SubscriptionController.php:216` get_item_permissions_check (`manage_options`); update_item_permissions_check (`manage_options`) |
| settings | `migrateSingleCourse` | `POST /ohmylms/v1/migrations/{}` | `MigrationController.php:59` check_migration_permission (`edit_posts`) |

### 3.2 Findings

- **17 requests have no registered route** (rows marked "no route registered"). This is a static match, so
  routes registered by code outside this repository (an add-on, the Pro plugin) would not be seen.
  - Community (5): there is no community controller in this checkout.
  - A similar route exists under the plural base for `course/get-map/{id}` (`courses/get-map/{id}`,
    `CourseController.php:344`) and for `lesson/content/save/{id}` (`lessons/content/save/{id}`,
    `LessonController.php:120`); the singular forms requested here are not registered. The other mismatches
    (`course/settings`, `chapter/update`, `lesson/update`, `lesson/content`, `membership/save`,
    `membership/delete`, `assignment/update`, `assignment/content`) were not investigated further.
  - `POST /ohmylms/v1/webhooks/test` (`testWebhook`) is not registered. `WebhookController` registers
    `/webhooks`, `/webhooks/{id}`, `/webhooks/bulk` and `/webhooks/triggers` only.
- **Capabilities.**
  - `manage_options`: `settings/email`, `integrations`, every webhooks route, subscriptions.
  - `manage_options` or `manage_ohmylms`: students.
  - `edit_posts` at route level: app data, setup wizard, courses, chapters, lessons, categories, tags,
    certificates, orders, migrations. Single sessions, memberships and assignments add per-verb object checks
    (`read_post`, `edit_post`, `delete_post`).
  - Quizzes and questions go through `AccessPolicy` (`can_author()` is `current_user_can( 'edit_posts' )`,
    plus per-object `can_edit_quiz`/`can_edit_question`).
  - The shared base `RestController::check_permission()` returns `true`; none of the routes above uses it
    (only `PluginController` does, which this store does not call).
  - The client must not widen any of this: it passes the request through and adds no capability logic, and the
    migration checks in section 7 test each role against each moved route.
- **Transport variants** the shared client has to cover: `parse: false` (list requests that read
  `X-WP-Total`), `body` plus explicit `headers` instead of `data` (course, lesson, assignment, membership and
  setup writes), a custom nonce header (setup wizard), `PUT`/`DELETE`, a `window.open` navigation for the
  SCORM export, and query strings built three ways (`addQueryArgs`, `URLSearchParams`, string concatenation).

## 4. Who uses what

### 4.1 Feature to store members

Static scan of `assets/src/features/**/*.jsx`: calls of `select( StoreModule.default ).member()`,
`useSelect( ( select ) => select( StoreModule.default ).member() )`, and members read from
`useDispatch( StoreModule.default )` (directly or through the variable it is assigned to; references in
dependency arrays are ignored). No feature reads a named export of the factory. `commerce-reference` is
ignored.

| Feature | Slices and members read (static scan of `assets/src/features/<feature>/*.jsx`) |
| --- | --- |
| analytics | **dashboard** (3): getDashboardFilter, getDashboardLoader, getDashboardOverview<br>**course** (2): importCourse, selectCourses<br>**notification** (3): getNotificationMessage, getNotificationStatus, showNotification |
| certificates | **course** (1): handleBulkDelete<br>**notification** (2): getNotificationMessage, getNotificationStatus<br>**certificate** (9): fetchCertificates, getCertificateById, selectCertificate, selectCertificatePagination, selectCertificates, selectTotalCertificatesNumber, updateCertificate, updateClassicData, updateContent |
| commerce | **notification** (3): getNotificationMessage, getNotificationStatus, showNotification<br>**order** (14): fetchOrder, fetchOrders, getCurrency, getOrder, getRefundState, issueRefund, saveOrderNote, selectOrders, selectOrdersPagination, setOrderNote, trashBulkOrdersAction, updateOrder, updateOrderState, updateRefund<br>**subscription** (8): fetchSubscription, fetchSubscriptions, selectSingleSubscription, selectSubscriptions, selectSubscriptionsLoading, selectSubscriptionsPagination, updateSubscription, updateSubscriptionState |
| communities | **community** (4): createCommunity, fetchCommunities, selectCommunity, setCommunity |
| courses | **app** (8): getAllCategories, getAllIntegrations, getGamificationSettings, isGamificationEnable, updateProModalButtonAction, updateProModalButtonText, updateProModalContent, updateProModalTitle<br>**course** (20): exportCourses, exportCoursesAsScorm, fetchCourses, getChapterLastIndex, getCourse, getCourseChapterSidebarOpen, getCourseChapters, getCourseInfoOpen, handleBulkDelete, importCourse, importScormCourse, selectCourses, selectCoursesLoading, selectCoursesPagination, setCourse, setCourseInfoOpen, setCourseLoading, setIsValidCourseSettings, updateChapterFields, updateChapterOrdersAPI<br>**chapter** (1): addChapter<br>**notification** (3): getNotificationMessage, getNotificationStatus, showNotification<br>**quiz** (1): getIsOpenQuizBuilder |
| emails | **emails** (12): getEmail, getEmailLoading, getEmailSettings, getEmails, searchEmails, selectSettingsMap, setEmail, updateEmail, updateEmailSettings, updateEmails, updateSettings, updateSingleEmail |
| gamification | **app** (2): getGamificationSettings, setGlobalDataViaKey<br>**notification** (2): getNotificationMessage, getNotificationStatus<br>**settings** (1): setLoadingSetting |
| integrations | **app** (3): updateProModalButtonText, updateProModalContent, updateProModalTitle<br>**notification** (2): getNotificationMessage, getNotificationStatus<br>**integrations** (3): getIntegrations, updateIntegrations |
| learning | **app** (1): getAllIntegrations<br>**course** (5): getCourse, getCourseChapters, getCourseChaptersContent, getCourseType, handleBulkDelete<br>**lesson** (14): addGoogleMeetClass, addZoomClass, deleteLessonFromChapter, geSelectedLessonId, getGoogleMeetClass, getLesson, getLessonByChapterId, getZoomClass, resetLessonState, setLesson, setSelectedLessonId, updateGoogleMeetClass, updateZoomClass<br>**notification** (3): getNotificationMessage, getNotificationStatus, showNotification<br>**loader** (1): isLoading<br>**quiz** (3): fetchQuizzes, getQuizzes, getQuizzesPagination<br>**assignment** (10): fetchAssignments, geSelectedAssignmentId, getAssignment, resetAssignmentState, selectAssignments, selectAssignmentsPagination, setAssignment, setContentsToChapter, updateAssignment<br>**sessions** (5): fetchSessions, getError, getLoading, selectSessions, selectSessionsPagination |
| memberships | **course** (1): handleBulkDelete<br>**membership** (8): addFullMembershipPlan, addMembershipPlan, clearMembershipPlan, fetchMembershipPlans, getMembershipPagination, getMemberships, selectMembershipPlanData, updateMembershipPlan<br>**notification** (2): getNotificationMessage, getNotificationStatus |
| question-editor | **course** (1): getCourseChaptersContent<br>**notification** (1): showNotification<br>**quiz** (5): getInteractiveQuizTypes, getQuiz, getQuizTypes, getSelectedQuizId, selectQuizzesError<br>**question** (12): addContentToQuestion, deleteQuestion, deleteTempQuestion, getAllQuestions, getQuestionContents, getQuestionErrors, selectQuestion, selectSelectedQuestionId, setQuestion, setQuestions, setSelectedQuestionId, updateQuestionData |
| quiz-editor | **course** (1): getCourseType<br>**quiz** (5): getQuiz, getQuizSettings, selectQuizzesError, setQuiz, setQuizError<br>**question** (5): getAllQuestions, getQuestionErrors, selectQuestion, setQuestions, setSelectedQuestionId |
| settings | **app** (1): setGlobalDataViaKey<br>**course** (2): importCourse, importScormCourse<br>**notification** (2): getNotificationMessage, getNotificationStatus<br>**settings** (37): getAccountPrivacySettings, getAdvancedSettings, getCurrencySettings, getDesignSettings, getGeneralSettings, getMigratedCourse, getMigrationCourses, getMigrationModalOpen, getMigrationStatus, getMigrationTool, getMigrationToolCourses, getPaymentSettings, getPermalinkSettings, getTaxSettings, isSettingsLoading, migrateSingleCourse, setAccountPrivacySettings, setAdvancedSettings, setCurrencySettings, setDesignSettings, setGeneralSettings, setLoadingSetting, setMigratedCourse, setMigrationCourses, setMigrationModalOpen, setMigrationStatus, setMigrationTool, setMigrationToolCourses, setPaymentSettings, setPermalinkSettings, setTaxSettings, updateAccountPrivacySettings, updateCurrencySettings, updateDesignSettings, updateGeneralSettings, updatePermalinkSettings, updateTaxSettings |
| setup | **app** (4): getSetupWizardData, saveSetup, setGlobalDataViaKey, setSetupWizardData<br>**course** (1): importScormCourse<br>**settings** (5): getCurrencySettings, getDesignSettings, setCurrencySettings, setDesignSettings, setLoadingSetting |
| students | **students** (2): fetchSingleStudent, getStudent |
| taxonomies | **app** (1): setGlobalDataViaKey |
| webhooks | **app** (1): getAllIntegrations<br>**webhooks** (11): bulkWebhookAction, clearWebhookFormData, createWebhook, deleteWebhookById, getWebhookTotal, getWebhooks, selectWebhookFormData, setWebhookFormData, updateWebhookById, updateWebhookFormData |

Members shared by several features (these need a single owner):

- `notification`: `showNotification` (analytics, commerce, courses, learning, question-editor),
  `getNotificationMessage` and `getNotificationStatus` (nine features). Owner: shared notices module.
- `app`: `getAllIntegrations` (courses, learning, webhooks), `setGlobalDataViaKey` (gamification, settings,
  setup, taxonomies), `updateProModal{Title,Content,ButtonText}` (courses, integrations),
  `getGamificationSettings` (courses, gamification). Owner: shared application state (`app`).
- `course`: `getCourse`, `getCourseChapters`, `getCourseType`, `getCourseChaptersContent`, `selectCourses`,
  `handleBulkDelete` (certificates, courses, learning, memberships), `importCourse` (analytics, courses,
  settings), `importScormCourse` (courses, settings, setup). Owner: courses.
- `quiz` and `question`: shared between `quiz-editor` and `question-editor` (`getQuiz`, `selectQuizzesError`,
  `setQuestions`, `setSelectedQuestionId`, `getAllQuestions`, `getQuestionErrors`, `selectQuestion`). Owner: the
  question bank / quiz modules together.
- `settings`: `setCurrencySettings`, `setDesignSettings`, `setLoadingSetting`, `getCurrencySettings`,
  `getDesignSettings` (settings, setup).

### 4.2 Recovered application only, and members nothing calls

Member lists per slice; `a:` is an action, `s:` a selector.

- **app**: recovered application only: a:getLmsUtilityData, a:setAutomationFor, a:setIsProModalOpen, s:getAutomationFor. Not called from outside the store: a:setGlobalData, s:getAllCurrencySettings, s:getAppAllData, s:getIsShowProModal, s:getProModalButtonAction, s:getProModalButtonText, s:getProModalContent, s:getProModalTitle.
- **dashboard**: recovered application only: a:setDashboardAll, a:setDashboardLoader, a:setDashboardOverview. Not called from outside the store: a:setDashboardCourse, a:setDashboardFilter, a:setDashboardReview, a:setDashboardSales, a:setDashboardStudents, s:getDashboardAllData, s:getDashboardCourse, s:getDashboardReview, s:getDashboardSales, s:getDashboardStudents.
- **course**: recovered application only: a:createCourse, a:deleteCourse, a:deleteCourseCategory, a:deleteCourseTag, a:getCategories, a:getCourse, a:getTags, a:removeCourseDownloadResource, a:removeCourseTag, a:removeFeaturedImage, a:resetCourseState, a:saveCourseCategory, a:saveCourseChapters, a:saveCourseChaptersWithoutNotice, a:saveCourseTag, a:setChapterContentTitle, a:setCourseCategories, a:setCourseChapterSidebarOpen, a:setCourseDownloadResource, a:setCourseTags, a:setFeaturedImage, a:setSlugAndURL, a:updateCourse, a:updateCourseWithoutNotice, s:getCategories, s:getCourseCategories, s:getCourseTags, s:getTags, s:isValidCourseSettings. Not called from outside the store: a:addChapterToCourse, a:attachFeatureImage, a:clearCourseMap, a:fetchChapterRequest, a:fetchCourseChapters, a:fetchCourseSettings, a:fetchCoursesRequest, a:fetchCoursesSuccess, a:getCourseMap, a:removeChapterFromCourse, a:removeContentFromChapter, a:saveCourseSettings, a:setChapterLastIndexContent, a:setCourseChapterContentSidebarOpen, a:setCourseDescription, a:setCourseMap, a:setCourseSettings, a:setCourseSidebarOpen, a:setCourseTitle, a:setCoursesPagination, a:setTotalCoursesNumber, a:updateChapterOrders, a:updateStatus, s:getCourseChapterContentSidebarOpen, s:getCourseMap, s:getCourseSettings, s:getCourseSidebarOpen, s:getCourses, s:getSingleCourse, s:getTotalCourses, s:selectCourseState, s:selectCoursesError, s:selectTotalCourses.
- **membership**: Not called from outside the store: a:clearMembershipDetails, a:deleteMembership, a:deleteMembershipPlan, a:fetchMembershipDetails, a:fetchMemberships, a:saveMembershipDetails, a:setMembershipDetails, a:setMemberships, s:getMembershipDetails, s:selectMembershipPlanKey.
- **chapter**: Not called from outside the store: a:fetchCoursesFailure, a:addChapterInStore, a:deleteChapter, a:editChapter, a:fetchChapters, a:fetchChaptersRequest, a:fetchChaptersSuccess, a:saveChapterContent, s:getChapterLessons, s:getChapterMap, s:getDefaultChapter.
- **lesson**: recovered application only: a:updateLesson. Not called from outside the store: a:addLesson, a:deleteLesson, a:editLesson, a:fetchLessonContent, a:fetchLessons, a:fetchLessonsSuccess, a:lessonCreateRequest, a:lessonsFetchRequest, a:saveLessonContent, a:setLessonsPagination, a:updateLessonWithoutNotice, s:selectLessonState, s:selectLessons, s:selectLessonsError, s:selectLessonsPagination.
- **notification**: Not called from outside the store: a:hideNotification, s:getNotification, s:isNotificationVisible.
- **loader**: Not called from outside the store: a:startLoading, a:stopLoading.
- **quiz**: recovered application only: a:getQuiz, a:registerInteractiveQuiz, a:registerQuiz, a:resetQuizState, a:setIsOpenQuizBuilder, a:setSelectedQuizId, a:updateQuiz, s:getQuizContents. Not called from outside the store: a:addQuiz, a:deleteQuiz, a:quizCreateRequest, a:setQuizzes.
- **question**: recovered application only: a:resetQuestionState, a:setAllQuestions, a:setQuestionErrors. Not called from outside the store: a:addQuestion, a:questionCreateRequest, a:removeQuestions.
- **settings**: recovered application only: a:updatePaymentSettings. Not called from outside the store: a:updateAdvancedSettings, a:updateMigrationCourses.
- **order**: Not called from outside the store: a:changeStatusFilter, a:fetchOrderSuccess, a:fetchOrdersRequest, a:fetchOrdersSuccess, a:setCurrency, a:setOrdersPagination, a:trashSingleOrderAction, s:selectActiveStatus, s:selectOrdersState, s:selectStatusCount.
- **assignment**: recovered application only: a:setSelectedAssignmentId. Not called from outside the store: a:AssignmentCreateRequest, a:AssignmentsFetchRequest, a:addAssignment, a:deleteAssignment, a:deleteAssignmentFromChapter, a:editAssignment, a:fetchAssignmentContent, a:fetchAssignmentsSuccess, a:getAssignmentByChapterId, a:saveAssignmentContent, a:setAssignmentsPagination, s:selectAssignmentState, s:selectAssignmentsError.
- **emails**: Not called from outside the store: a:getAllEmails, a:setEmailLoading, a:setEmails, a:setSettings, s:getSettings.
- **certificate**: recovered application only: a:setCertificate. Not called from outside the store: a:deleteCertificate, a:getCertificate, a:getCertificates, a:setCertificateLoader, a:setCertificateSettings, a:setCertificates, s:selectCertificateLoader, s:selectCertificateSettings.
- **students**: recovered application only: a:fetchStudents, s:getAllStudents, s:getStudentsPagination. Not called from outside the store: a:getAllStudents, a:getStudent, a:setAllStudents, a:setStudent, a:setStudentsLoader, s:getStudentsLoader.
- **integrations**: Not called from outside the store: a:deleteIntegration.
- **subscription**: Not called from outside the store: a:fetchSubscriptionSuccess, a:fetchSubscriptionsFailure, a:fetchSubscriptionsRequest, a:fetchSubscriptionsSuccess, a:setSubscriptionsPagination, s:selectActiveSubscriptionStatusFilter, s:selectCurrentSubscriptionPage, s:selectSubscriptionCurrency, s:selectSubscriptionState, s:selectSubscriptionStatusCount, s:selectSubscriptionsError.
- **sessions**: Not called from outside the store: a:setPage, a:fetchSession, a:fetchSessionSuccess, a:fetchSessionsFailure, a:fetchSessionsRequest, a:fetchSessionsSuccess, a:setSessionsPagination, a:updateSession, s:getPage, s:getSession, s:selectSessionsState.
- **community**: Not called from outside the store: a:deleteCommunities, a:fetchCommunitiesByCourseId, a:fetchCommunityById, a:setCommunities, a:setCommunityError, a:setCommunityLoading, s:getCommunities, s:getCommunitiesPagination, s:getCommunity, s:selectCommunities, s:selectCommunityError, s:selectCommunityLoading, s:selectCommunityState.
- **webhooks**: Not called from outside the store: a:addWebhook, a:deleteWebhook, a:getWebhookTriggers, a:setWebhook, a:setWebhookLoading, a:setWebhookTriggers, a:setWebhooks, a:testWebhook, a:updateWebhook, s:getWebhook, s:getWebhookLoading, s:getWebhookTotalPages, s:getWebhookTriggers.

"Recovered application only" members are called from `assets/src/application/**` or factories 13567 and
83193. Those fragments include the **pre-extraction originals** of components the adapters already replace
(for example `webhook-editor-modal.js` defines `A5`, `z5` and `$5`, and `screens/webhooks/WebhooksRoute.js`
defines `a4`, all four replaced by `tools/webhook-adapters.mjs`). Usage by those is therefore not proof of a
live consumer. Which of the 54 are live in the adapted build has to be read from the built output, not from
these sources.

Not reached by any scan: `/quiz-edit/:id` and `/students` still render recovered screens (roadmap step 6),
so their store members belong to the recovered application until those screens are rewritten.

## 5. Design

### 5.1 Target structure

```text
assets/src/shared/api/                 shared REST client (exists): client, errors, url, index
assets/src/shared/state/               store-name.mjs (OHMYLMS_STORE), notifications and loader slices,
                                       compose.mjs (composeStore) - to be added
assets/src/features/<feature>/api.mjs  the feature's requests: paths, methods, data, nothing else
assets/src/features/<feature>/store.mjs the feature's slice: initial state, reducer, plain action
                                       creators, thunks, root-state selectors - no @wordpress/data import
assets/src/extensions/index.jsx        publishes the slices as window.ohmylms.extensions.stateSlices
tools/state-adapters.mjs               build bridge between factory 86169 and the authored slices
```

A **slice** is `{ key, initialState, reducer, actions, selectors }`. Selectors keep the recovered shape
(`( rootState ) => rootState[ key ].x`) so they can be spread into the one registered store unchanged.
`store.mjs` files stay free of WordPress imports (the packages are not in `node_modules`, and the slices
can then be unit-tested against the recovered code).

### 5.2 Shared REST client (`assets/src/shared/api/`)

`createRestClient( { apiFetch, namespace, nonce, fallback } )` returns `request`, `get`, `post`, `put`,
`patch`, `remove`, `path` and `namespace`.

- `request( options )` hands the options object to `apiFetch` **untouched**, so a recovered request shape can
  be reproduced exactly (no added `method: 'GET'`, no copied object). The route helpers build options for the
  namespace; `GET` has no `method` key and `query` is folded into the path (`undefined`, `null` and `''` are
  skipped).
- `apiFetch` defaults to `wp.apiFetch`, resolved when a request is made. WordPress already installs the REST
  root and the `X-WP-Nonce` middleware on it, so features do not handle nonces. A `nonce` option (string or
  function) adds `X-WP-Nonce` only when the request does not set it, for pages that fetch outside the default
  middleware. Custom nonce headers (the setup wizard) stay in the feature's own request options.
- Errors: a rejection that already has a string `message` (the parsed REST error, or an `Error`) is rethrown
  **as the same object**, which is the shape `features/requestFeedback.mjs` `showRequestError()` reads. An
  unparsed `Response` (requests with `parse: false`) becomes a `RestError` with `message`, `code`, `status`,
  `data` and `cause` read from the JSON body; anything else becomes a `RestError` with the fallback message.
- `restUrl( root, path )` is the same convention as `features/restUrl.mjs` (plain-permalink roots join with
  `&`); the shared test asserts the two stay identical until that file re-exports this one.
- The export-SCORM navigation, which cannot use `apiFetch`, should use `restUrl( wpApiSettings.root, ... )`
  instead of the hard-coded `/wp-json/` prefix when it is migrated.

### 5.3 Per-feature modules

`api.mjs` has one exported function per request, each taking the client as its last argument (the pattern
`courses/api.mjs` and `content-hub/api.mjs` started). `store.mjs` builds the slice from an `api` object and a
`showNotification` function (`createWebhooksSlice( { api, showNotification } )`), both injected, so the slice
is tested with a scripted `apiFetch` and the same code runs against the real client.

Parity rule for every migrated slice: same action and selector names and payloads, same plain actions
dispatched in the same order, same request options, same rethrown errors. Fixes (wrong text maps, missing
routes, hard-coded prefixes) are separate commits after the parity test is green, each with its own test.

### 5.4 One store instance

Rules, in order of importance:

1. **`ohmylms/store` is created once**, by factory 86169, until the last slice has been migrated. No authored
   module calls `createReduxStore( 'ohmylms/store' )` or `register()` for that name. Registering a second
   store under the same name would replace the registry entry that the recovered consumers subscribed to.
2. **Features address the store by name**, not through the factory binding: `useSelect( ( select ) =>
   select( OHMYLMS_STORE ).getWebhooks() )` and `useDispatch( OHMYLMS_STORE )`, with
   `export const OHMYLMS_STORE = 'ohmylms/store';` in `assets/src/shared/state/store-name.mjs`.
   `@wordpress/data` resolves the name to the registered instance, so the recovered application and the
   authored features share one store without `T`. `features/courses/CourseChapter.jsx` and at least six recovered
   fragments already do this.
3. **Slices are swapped inside that one store** by the build bridge (5.5), never by registering a second store
   for the same state. A slice may get its own store (`ohmylms/<feature>`) only when no recovered code reads
   it and it dispatches nothing but `notification`; its thunks then reach the shared notifications through
   `registry.dispatch( OHMYLMS_STORE )`. This is a fallback, not the plan, because the roadmap asks for one
   instance.
4. **Keep the merge order and the colliding names** (2.3) until the callers are migrated; a composed store
   spreads the slices in the order of 2.1.
5. **Slices that dispatch into others** (2.4) move together, or the dependency is replaced by an injected
   function. `webhooks`, `certificate`, `emails`, `students`, `order` and `subscription` only need
   `notification`.
6. Notifications and the loader become authored slices early (they are small: 4 actions and 4 selectors, and
   4 actions and 1 selector). Until then the `showNotification` dependency defaults to
   `legacyShowNotification`, which dispatches the same plain `SHOW_NOTIFICATION`/`HIDE_NOTIFICATION` actions
   as the recovered thunk.

### 5.5 Bridge: `tools/state-adapters.mjs`

Same approach as `tools/vendor-package-adapters.mjs`: a Babel pass over the recovered AST, applied only to
`assets/dist/admin/ohmylms.js` in the `--extensions` build, **fail-closed** (any anchor missing or changed
throws and the build stops).

**Stage 1, per slice (the one needed for webhooks).** For each migrated slice, driven by a table
`{ key, reducerProperty, actionAnchor, selectorAnchor }` and the authored slice module:

1. Find factory 86169 (`ObjectProperty` key `86169`), the single `createReduxStore( 'ohmylms/store', ... )`
   call and its `reducer` function.
2. Replace the callee of the property `webhooks: vg( e.webhooks, t )` with
   `window.ohmylms.extensions.stateSlices.webhooks.reducer` (the argument list stays).
3. Find the action and selector namespace objects by export key (`n.d( V, { ..., bulkWebhookAction: ... } )`,
   `n.d( H, { ..., selectWebhookFormData: ... } )`) and point every getter at the authored slice
   (`() => slice.actions.name`, `() => slice.selectors.name`). The spreads that build `actions` and
   `selectors` are not touched, so merge order and collisions are preserved.
4. Assert that the key sets of both namespace objects equal `Object.keys( slice.actions )` and
   `Object.keys( slice.selectors )`; any difference fails the build.
5. Declare `const slice = window.ohmylms.extensions.stateSlices.webhooks` at the top of the factory and throw
   `OhMyLMS webhooks state slice must load before the application store` when it is missing (the message
   style of the vendor bridge). `webhookComponents` is read from the same object when factory 1841 is
   evaluated, so the load order is already required.
6. A second pass removes the now unreferenced recovered declarations (constants, helper functions, reducer,
   initial state, thunks) using scope analysis; a leftover reference fails the build.

`tools/commerce-adapters.mjs` rewrites string literals across the whole AST, including factory 86169. Run
`adaptState` **after** `adaptCommerce` in `tools/build-assets.mjs`, or make both order independent, and add a
test that builds both ways.

**Stage 2, when every slice is authored.** Replace the whole factory the way the vendor packages are
replaced: `( module, exports, require ) => { const state = window.ohmylms.extensions.state; if ( ! state )
throw ...; require.d( exports, { default: () => state.store } ); }` where `state.store` is created once by
`composeStore( { name: OHMYLMS_STORE, slices } )`. Factories 26456, 45050, 5836, 16118, 77558 and 93509 join
the retired list of the inventory. Features have not changed since the name-based move, so nothing else
moves.

### 5.6 Order of migration

Smallest and most isolated first; every row is "api.mjs + store.mjs + parity test", then the stage 1 patch,
then the name-based feature edit, then removal of the recovered code. Sizes are rough working estimates that
include browser verification (S about 1 day, M 2-3 days, L about a week, XL longer).

| # | Slice / feature | Authored files | Store members used | Requests | Depends on | Size |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `webhooks` | 4 | 12 | 7 | notification | slice done; wiring S |
| 2 | `notification`, `loader` | shared | 4+4, 4+1 members | 0 | - | S |
| 3 | `integrations` (+ `getAllIntegrations`, pro modal in `app`) | 1 | 8 | 3 | notification, app | S |
| 4 | `emails` | 9 | 12 | 5 | - | S-M |
| 5 | `students` | 2 | 2 | 2 | notification | S |
| 6 | `certificates` | 8 | 12 | 4 | notification, `course.handleBulkDelete` | M |
| 7 | `commerce` (order, subscription) | 9 | 25 | 10 | notification; commerce namespace | M |
| 8 | `memberships` | 6 | 11 | 7 (2 without routes) | notification, `course.handleBulkDelete` | M |
| 9 | `communities` | 4 | 4 | 5 (no routes in this repo) | notification; decide first whether the routes exist elsewhere | M |
| 10 | `settings`, `setup`, `gamification`, `taxonomies` (+ `app`) | 15+9+9+1 | 42+10+5+1 | 1 | `app`, `course` imports | M-L |
| 11 | `analytics` (`dashboard`) | 6 | 8 | 0 | course, notification | S-M |
| 12 | `learning` (lesson, assignment, sessions, loader) | 12 | 42 | 30 | chapter, course, quiz, loader | L |
| 13 | `courses`, `quiz-editor`, `question-editor` (course, chapter, quiz, question) | 12+2+7 | 33+11+19 | 36 | each other, `structureClient`, string-name consumers | XL |

`app` stays last as a slice because eight features read it (2.2); its members move to the feature that owns
each (`pro modal` to shared notices, `categories` and `integrations` to their features, `setupWizard` to
setup).

## 6. First slice: `webhooks`

### 6.1 What was added (new files only)

| File | Purpose |
| --- | --- |
| `assets/src/shared/api/{client,errors,url,index}.mjs` | the shared REST client (5.2) |
| `assets/src/features/webhooks/api.mjs` | the seven requests: list, create, update, delete, bulk, triggers, test; `createWebhooksApi( client )` binds them |
| `assets/src/features/webhooks/store.mjs` | `webhooksReducer`, `webhooksInitialState`, 10 plain action creators, 7 thunks (`createWebhookThunks`), 7 root-state selectors, `createWebhooksSlice( { api, showNotification } )`, `legacyShowNotification` |
| `tests/js/shared-api.test.mjs` | 12 tests of the client |
| `tests/js/webhooks-api.test.mjs` | 19 tests: authored slice against the recovered code |
| `tests/js/webhooks-api-recovered.mjs`, `tests/js/webhooks-api-harness.mjs` | the vm extraction and the test doubles (not test files themselves) |

The slice exposes the same 17 action names and 7 selector names as the recovered `webhooks` section, with
the same state keys (`webhooks`, `webhook`, `triggers`, `loading`, `total`, `totalPages`, `formData`).
Nothing is imported by existing code; `npm run build` output is unchanged.

### 6.2 How it was verified

`tests/js/webhooks-api-recovered.mjs` parses factory 86169 and extracts the webhooks code without using any
minified name for it: the action and selector namespaces are found by their export keys
(`bulkWebhookAction`, `selectWebhookFormData`, `showNotification`), the reducer by the `webhooks` property of
the root reducer. The declarations those roots need (52 of them, 24 KB: constants, helpers, the regenerator
runtime, the reducer, the thunks, the notification thunk) are followed through scope-resolved references and
copied unchanged, in source order. The only replaced binding is `pe = n.n( n( 12842 ) )` (`wp.apiFetch`); if
the closure ever needs another required module or a factory parameter, the extraction throws. The code runs
in a `node:vm` context with a scripted `apiFetch` and a timer recorder.

Each scenario runs on both implementations with the same scripted responses and asserts equality of:
the recorded requests (path, method, data, every other option key), the plain actions dispatched (including
notification payloads and the 100 ms hide), the loading flag after each action, the final state, the timers
requested, the return value and the rethrown error (same object). Scenarios: reducer and action-creator
sequence (15 steps, including array payloads, NaN totals, unknown ids and immutability), selectors, and for
every thunk the success path and the failure path; additionally `getWebhooks` with default parameters,
missing headers and an unreadable body, and `bulkWebhookAction` for `delete`, `active` and `inactive`.
Separate assertions state the request shapes literally so a change that moved both implementations would
still fail. A mutation check was run (changing one notice text and `PUT` to `PATCH` made two tests fail;
reverted).

Result: `node --test tests/js/webhooks-api.test.mjs tests/js/shared-api.test.mjs` passes 31 of 31;
`npx prettier --check` and `npx eslint --max-warnings 0` are clean on the new files.

### 6.3 Quirks preserved for parity, and deliberate differences

Preserved (each is covered by a test and listed so it can be fixed with its own change):

1. The list path is `/ohmylms/v1/webhooks?` followed by `new URLSearchParams( params )`, so it ends in `?` when
   there are no parameters and `undefined` values become the text `undefined`.
2. Notifications carry `message: { message, type }` and `status: 'success'`, also for errors.
3. `bulkWebhookAction( 'active' | 'inactive', ids )` dispatches a notification whose message is `undefined`
   (the text map is keyed `activate`/`deactivate`). The server accepts `delete`, `active`, `inactive`.
4. After a bulk action the list reloads with default parameters (page, search and status are lost) and is not
   awaited.
5. The response body is stored as received (`webhooks: body`), and a missing `X-WP-Total` header gives `NaN`,
   which the reducer turns into `0`.
6. `testWebhook` posts to `/ohmylms/v1/webhooks/test`, which no controller registers (3.2).

Deliberate differences (outside the observable store behaviour):

- No `console.error` calls (the repository's lint rules reject them; the notification and the rethrow remain).
- The reload started by a bulk action has a `.catch( () => {} )`: the recovered code leaves its rejection
  unhandled. State and notifications are the same.
- A failing request made with `parse: false` rejects with a `RestError` (message, code, status read from the
  JSON body) instead of the raw `Response`. A parsed REST error is rethrown as the same object.

## 7. Checks before a recovered implementation is retired

Per migrated slice or feature, in this order. A step that fails blocks the next one.

1. **Parity test** against the recovered code in a vm sandbox, as for webhooks: requests, dispatched actions,
   state, errors, timers. Extend it for the slice's cross-slice dispatches (2.4) with a recorded dispatcher.
2. **Route audit**: each request in the slice maps to a registered route and method (3.1). Requests without
   a route are either fixed in their own commit or recorded as known-broken before the wiring, not carried over
   silently.
3. **Real save and reopen on a WordPress site** (the local test site), per feature: create or edit, save,
   reload the browser, reopen from the list and compare every field; delete; list pagination and totals from the
   `X-WP-Total` headers; one failing request (invalid payload or a stopped route) shows the error and keeps the
   form. Webhooks: create with data mapping, edit, activate/deactivate in bulk (the text for these is empty, see
   6.3), delete, reload the list.
4. **Permissions**, per moved route and per role (administrator, editor or instructor, subscriber, logged out):
   record the HTTP status before the move and after it; they must be equal. Webhooks routes require
   `manage_options`, so every other role gets 403 and the page shows the error. Also: an expired nonce (stale
   cookie) gives `rest_cookie_invalid_nonce` with the error shape and no crash, and one pass on a site with
   plain permalinks (`?rest_route=`).
5. **Single store**: before and after the change, `Object.keys( wp.data.select( 'ohmylms/store' ) )` and
   `Object.keys( wp.data.dispatch( 'ohmylms/store' ) )` are identical; only one `ohmylms/store` is registered;
   recovered screens that read the slice (if any) still work.
6. **Build**: `node tools/build-assets.mjs --parity`, `npm run build`, the inventory report drift check, and a
   test that breaks each adapter anchor and expects the build to fail.
7. **Standards**: `npm run lint`, `npm run format:check`, `npm test`.
8. Only then delete the recovered declarations (the adapter's second pass), update the inventory, and move the
   slice from "recovered" to "authored" in `docs/RUNTIME-DEPENDENCY-INVENTORY.md`.

## 8. Wiring the webhooks slice (not done): exact edit list

Two independent steps; each is safe alone and reversible.

### 8.1 W1 - features address the store by name (pure refactor, no behaviour change)

Dry run: `node <scratchpad>/codemods/webhooks-store.mjs --dry`
(`C:/Users/abaym/AppData/Local/Temp/claude/C--Users-abaym--codex-worktrees-1197-OhMyLMS/08645949-c3b0-4463-a750-061fcc46660f/scratchpad/codemods/webhooks-store.mjs`).
It only supports `--dry`; it writes nothing and refuses to plan (exit 1) if a store reference cannot be
classified. Output at the time of writing:

```text
DRY RUN: nothing is written.

assets/src/features/webhooks/WebhookDetails.jsx  (factory-1841 binding A5)
  line 11: remove "T: StoreModule" from the readRuntime() destructure
  line 20   col 14  StoreModule.default -> OHMYLMS_STORE   selector via e: selectWebhookFormData  [webhooks slice]
  line 23   col 4   StoreModule.default -> OHMYLMS_STORE   action via 0, WordPressData.useDispatch: updateWebhookFormData  [webhooks slice]
  add:  import { OHMYLMS_STORE } from '../../shared/state/store-name.mjs';  // new module

assets/src/features/webhooks/WebhookDataMapping.jsx  (factory-1841 binding z5)
  line 11: remove "T: StoreModule" from the readRuntime() destructure
  line 19   col 14  StoreModule.default -> OHMYLMS_STORE   selector via e: selectWebhookFormData  [webhooks slice]
  line 21   col 41  StoreModule.default -> OHMYLMS_STORE   action via 0, WordPressData.useDispatch: updateWebhookFormData  [webhooks slice]
  add:  import { OHMYLMS_STORE } from '../../shared/state/store-name.mjs';  // new module

assets/src/features/webhooks/WebhookEditorModal.jsx  (factory-1841 binding $5)
  line 14: remove "T: StoreModule" from the readRuntime() destructure
  line 25   col 41  StoreModule.default -> OHMYLMS_STORE   action via 0, WordPressData.useDispatch: clearWebhookFormData, createWebhook, setWebhookFormData, updateWebhookById  [webhooks slice]
  line 104  col 14  StoreModule.default -> OHMYLMS_STORE   selector via e: selectWebhookFormData  [webhooks slice]
  add:  import { OHMYLMS_STORE } from '../../shared/state/store-name.mjs';  // new module

assets/src/features/webhooks/WebhooksPage.jsx  (factory-1841 binding a4)
  line 17: remove "T: StoreModule" from the readRuntime() destructure
  line 36   col 41  StoreModule.default -> OHMYLMS_STORE   action via 0, WordPressData.useDispatch: bulkWebhookAction, deleteWebhookById, getWebhooks  [webhooks slice]
  line 75   col 21  StoreModule.default -> OHMYLMS_STORE   selector via _eLocal: getWebhooks  [webhooks slice]
  line 79   col 22  StoreModule.default -> OHMYLMS_STORE   selector via _eLocal2: getWebhookTotal  [webhooks slice]
  line 82   col 21  StoreModule.default -> OHMYLMS_STORE   selector via _eLocal3: getAllIntegrations  [other recovered slice]
  add:  import { OHMYLMS_STORE } from '../../shared/state/store-name.mjs';  // new module

components.json: WebhookDetails.jsx (A5) remove "T" from dependencies; WebhookDataMapping.jsx (z5) remove "T" from dependencies; WebhookEditorModal.jsx ($5) remove "T" from dependencies; WebhooksPage.jsx (a4) remove "T" from dependencies

Factory 86169 anchors for the adapter step (read only, 1-based lines):
  createReduxStore         13642
  webhooksReducer          13667
  webhooksActionExports    519
  webhooksSelectorExports  544
  register                 13673

Totals: 4 files, 10 store call sites (9 webhooks, 1 other slice), 4 destructure entries, 4 manifest entries.
```

Edits it lists (files, lines, bindings):

- New file `assets/src/shared/state/store-name.mjs`: `export const OHMYLMS_STORE = 'ohmylms/store';`
- In each of the four components: add `import { OHMYLMS_STORE } from '../../shared/state/store-name.mjs';`,
  remove the `T: StoreModule` entry from the `readRuntime()` destructure (`WebhookDetails.jsx` 11,
  `WebhookDataMapping.jsx` 11, `WebhookEditorModal.jsx` 14, `WebhooksPage.jsx` 17) and replace the ten
  `StoreModule.default` arguments with `OHMYLMS_STORE` (`WebhookDetails.jsx` 20 and 23,
  `WebhookDataMapping.jsx` 19 and 21, `WebhookEditorModal.jsx` 25 and 104, `WebhooksPage.jsx` 36, 75, 79 and 82;
  line 82 is the `getAllIntegrations` selector of another slice, which works by name too).
- `assets/src/features/webhooks/components.json`: remove `"T"` from `dependencies` of `A5`
  (`WebhookDetails`), `z5` (`WebhookDataMapping`), `$5` (`WebhookEditorModal`) and `a4` (`WebhooksPage`). The
  adapter builds `readRuntime()` from that list, so a leftover `T` would still be passed but unused.
- Do not touch `T` in other features; they stay on the factory binding until their own migration.

Checks: section 7 steps 3 to 7 with the webhooks scenarios; the Playwright route spec `/webhooks` in
`tests/browser/extensions.spec.cjs`.

### 8.2 W2 - the authored slice replaces the recovered one inside `ohmylms/store`

- `assets/src/extensions/index.jsx`: add `stateSlices: { webhooks: createWebhooksSlice() }` to the public API
  object (next to `webhookComponents`, lines 35 and 97), with `createWebhooksSlice` imported from
  `../features/webhooks/store.mjs`. `createWebhooksSlice()` uses the shared client and `legacyShowNotification`.
- New `tools/state-adapters.mjs` (5.5, stage 1) and one call in `tools/build-assets.mjs` for
  `assets/dist/admin/ohmylms.js` in `--extensions` mode, after `adaptCommerce`. Anchors in factory 86169
  (1-based lines in the current recovered file): `createReduxStore( 'ohmylms/store'` 13642,
  `webhooks: vg( e.webhooks, t )` 13667, action namespace `n.d( V, { ... bulkWebhookAction ... } )` 517 to 535,
  selector namespace `n.d( H, { ... selectWebhookFormData ... } )` 537 to 545, `register( Uh )` 13673. The
  recovered webhooks declarations are lines 12306 (action types) to 12922 (selectors, `zg` to `Ug`).
- Because the namespaces keep the same keys and the `Gh`/`Hh` spreads stay in place, no feature file changes.
- New test `tests/js/state-adapters.test.mjs`: run the adapter on the real factory, evaluate it with stubbed
  `wp.data`, `wp.apiFetch` and `window.ohmylms.extensions.stateSlices`, and run the same scenarios as the
  parity test through `registry.dispatch( 'ohmylms/store' )`; plus the negative tests (missing anchor, extra or
  missing key, missing `window.ohmylms.extensions.stateSlices.webhooks`).
- Rebuild the generated assets (`npm run build`) and review the diff of `assets/dist`; do not edit it directly.

## 9. Not verified

- Nothing was run in a browser or against a WordPress site; no request was sent. Route and capability
  results are from reading PHP, not from HTTP calls. Object-level checks inside request callbacks were not
  read.
- Which recovered application fragments are live in the adapted build (4.2), and whether the shell shows the
  store notifications that the webhooks thunks dispatch.
- That `window.ohmylms.extensions` is populated before factory 86169 is evaluated in the production load order
  (factory 1841 reads `webhookComponents` from it at the same moment, which suggests so).
- The stage 1 adapter, `shared/state/store-name.mjs` and the by-name usage are designed, not built. By-name
  access relies on `@wordpress/data` resolving a string to the registered store, which the repository already
  does in `CourseChapter.jsx`. What a duplicate `register()` of the same name does was not tested; rule 1 in
  5.4 exists to avoid it.
- Static scan limits: member usage inside code that passes the store dispatcher to another function, and
  names built dynamically, are not seen (14 references in features and 15 in the recovered application could
  not be classified). `handleBulkDelete( entity, ids )` matches several routes, all listed.
- Settings persistence outside the store (where the `settings/*` writes happen) was not traced.
- Sizes in 5.6 are estimates.
