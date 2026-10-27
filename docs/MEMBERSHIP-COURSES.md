# Membership course selection

The learner course overview does not show individual course prices or an Enroll now action.
An active membership automatically supplies its included course access records through the
existing synchronization below; no additional enrollment click is needed. Learners without
access see a link to membership plans. Lesson/quiz permissions and progress continue to use
those automatically created records. The legacy `#oml-course-enroll` anchor is retained for
existing subject and activity links, and now points to the membership access explanation.

Membership plans combine individual courses, curriculum items (including everything below them) and Course Bundles (a bundle's courses plus the courses under its curriculum members). A match through any inclusion is sufficient. Explicit course exclusions take priority over every inclusion. Only published courses enter the resolved set; future matching publications are included automatically.

The Courses step restores all saved selections and previews the currently included courses with their matching sources. POST `/ohmylms/v1/membership/course-preview` computes unsaved selections without changing enrollments and requires the existing membership-editor permission.

The Courses step also provides a curriculum mindmap with expandable branches and the published courses linked to each item (`GET curriculum/outline?courses=1`). The mindmap and the curriculum picker share the same selection; selecting a parent highlights inherited child items and updates the preview. Its reusable UI is in `assets/src/extensions/mindmap/`; `features/memberships/CurriculumMindmap.jsx` contains the inclusion rules. Future curriculum and skill editors should reuse the shared mindmap.

The explicit `products` selection remains separate from `course_curriculum`, `course_bundles` and `excluded_courses`. Metadata uses `_products`, `_course_curriculum`, `_course_bundles` and `_excluded_courses`. `Membership::get_products('edit')` reads explicit choices for editing/persistence; its default view resolves current rules for checkout, course counts and member-facing lists. Omitted fields preserve existing selections.

Plan saves, course publication/status changes, changes to a course's curriculum placement or bundles (`ohmylms_curriculum_changed`), curriculum item deletion and curriculum moves synchronize membership-owned enrollment rows. Active members gain newly matching courses. Losing the last inclusion match, excluding a course or unpublishing it revokes this plan's grant, including courses already started. Progress is retained. Pending memberships remain pending; inactive memberships receive no active grant. Membership renewals retain progress while updating the order reference.

Independent course purchases, manual enrollments and other memberships are separate sources of access. Synchronization only changes rows belonging to this plan. Legacy rows can be attributed to a plan through its membership order; unscoped rows without reliable provenance are preserved.



## Old category and tag rules

Course categories and tags were replaced by the curriculum and Course Bundles. A plan saved before that may still hold category or tag rules (`_course_categories`, `_course_tags`). They are **not dropped**, because dropping them would revoke the enrollments they grant. They still resolve (`CourseSelection::resolve` accepts them next to the new rules), are shown by name in a warning in the plan editor (`legacy_rules` in the plan payload) and count in the course preview. They can only be **removed**: a request that adds a category or tag ID the plan does not already hold is rejected with `membership_legacy_rules`. Remove one once its courses are covered by curriculum items or bundles.

Checks: `php tests/php/membership-selection-unit.php`, `php tests/php/placement-integration.php`, `node --test tests/js/membership-selection.test.mjs`, `npm test`, and `npm run build`.
