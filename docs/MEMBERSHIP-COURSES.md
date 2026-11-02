# Membership course selection

The learner course overview does not show individual course prices or an Enroll now action.
An active membership automatically supplies its included course access records through the
existing synchronization below; no additional enrollment click is needed. Learners without
access see a link to membership plans. Lesson/quiz permissions and progress continue to use
those automatically created records. The legacy `#oml-course-enroll` anchor is retained for
existing subject and activity links, and now points to the membership access explanation.

Membership plans combine individual courses, curriculum items (including everything below them) and Course Bundles (a bundle's courses plus the courses under its curriculum members). A match through any inclusion is sufficient. Explicit course exclusions take priority over every inclusion. Only published courses enter the resolved set; future matching publications are included automatically.

The Courses step restores all saved selections and previews the currently included courses with their matching sources. POST `/ohmylms/v1/membership/course-preview` computes unsaved selections without changing enrollments and requires the existing membership-editor permission.

The plan editor is the typed app's `#/memberships` screen (`assets/src/app/features/memberships/`). The curriculum mindmap of the recovered editor (`assets/src/extensions/mindmap/`, `CurriculumMindmap.jsx`) was deleted at the cutover ([CUTOVER.md](CUTOVER.md)); `GET curriculum/outline?courses=1` still returns the outline with linked courses for a future picker.

The explicit `products` selection remains separate from `course_curriculum`, `course_bundles` and `excluded_courses`. Metadata uses `_products`, `_course_curriculum`, `_course_bundles` and `_excluded_courses`. `Membership::get_products('edit')` reads explicit choices for editing/persistence; its default view resolves current rules for checkout, course counts and member-facing lists. Omitted fields preserve existing selections.

Plan saves, course publication/status changes, changes to a course's curriculum placement or bundles (`ohmylms_curriculum_changed`), curriculum item deletion and curriculum moves synchronize membership-owned enrollment rows. Active members gain newly matching courses. Losing the last inclusion match, excluding a course or unpublishing it revokes this plan's grant, including courses already started. Progress is retained. Pending memberships remain pending; inactive memberships receive no active grant. Membership renewals retain progress while updating the order reference.

Independent course purchases, manual enrollments and other memberships are separate sources of access. Synchronization only changes rows belonging to this plan. Legacy rows can be attributed to a plan through its membership order; unscoped rows without reliable provenance are preserved.



## Old category and tag rules

Course categories and tags were replaced by the curriculum and Course Bundles. A plan saved before that may still hold category or tag rules (`_course_categories`, `_course_tags`). They are **not dropped**, because dropping them would revoke the enrollments they grant. They still resolve (`CourseSelection::resolve` accepts them next to the new rules), are shown by name in a warning in the plan editor (`legacy_rules` in the plan payload) and count in the course preview. They can only be **removed**: a request that adds a category or tag ID the plan does not already hold is rejected with `membership_legacy_rules`. Remove one once its courses are covered by curriculum items or bundles.

Checks: `php tests/php/membership-selection-unit.php`, `php tests/php/placement-integration.php`, `npm test`, `npm run test:app` and `npm run build`.

## Free access through Sale price

In plan Details, set Sale price to 0 and select Free until. An optional Sale starts date schedules availability. Free access lasts through the selected end day in the WordPress site timezone. Existing sale fields and REST names remain compatible; empty dates can be cleared.

Free signup requires an authenticated, nonce-protected request and an active published plan. It creates a tracked membership grant and the plan's course access, without an order, signup fee, payment or recurring subscription. Standard checkout rejects active free promotions so stale carts cannot charge for them. Students must explicitly purchase a plan after free access ends.

Cron and authenticated request-time checks expire the tracked grant. Course revocation is scoped to the user and plan, preserves paid upgrades and other active grants, and retries database failures. No automatic charge occurs. Calendar checks: `php tests/php/membership-free-sale-unit.php`.

Course Bundles are retired. Their navigation, course/membership pickers, learner catalog listing and public filter are removed. Old bundle screen URLs open Curriculum. The legacy REST fields and resolver argument remain accepted, but bundle selections no longer grant access; an administrator request resynchronizes existing plan enrollments once.

The All learning content checkbox persists as the boolean all_learning_content REST field and _all_learning_content plan meta. It includes all current and future published courses and their lessons/quizzes. Exclusions still win. Turning it off restores the saved individual and curriculum choices. The existing publication hooks synchronize newly matching courses for active members.
