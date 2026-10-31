# Automations (`ohmylms/v1/automations`)

Rules of the form **when &lt;event&gt; then &lt;actions&gt;** that run on the plugin's existing hooks. Server code:
`includes/Automation/Rules.php` (allow-list and validation), `RuleStore.php` (tables, log),
`RuleRunner.php` (listeners and actions), `includes/Rest/V1/AutomationsController.php`. Screen: Commerce ›
Memberships › **Automations** (`…&tab=memberships&panel=automations`, `features/commerce/automations`).
Test: `tests/php/automations-integration.php` (`OHMYLMS_AUTOMATIONS_TEST=1`, 74 checks).

## What the legacy admin had

The recovered admin edited Mail Mint automations per course (`MailMintAutomationController`,
`automation-*` screens) and required the Mail Mint plugin; its OhMyLMS connector
(`Automation/Triggers/OhMyLmsTriggers.php`) offered course enrolment, enrolment cancelled, new course order,
lesson completed, course completion rate, assignment submitted, assignment pass/fail and quiz submitted. Those
triggers are the events here (plus course completed and plan purchased); Mail Mint's own actions are replaced by
the five plugin actions below. The Mail Mint connector is untouched and still works where Mail Mint is active.

## Safety

- Rules are data. Events, conditions and actions come from fixed lists; every parameter is validated
  (published course, plan, coupon or active cohort; learner or site administrator as recipient). There is no
  free recipient address, URL, code, filter or SQL anywhere in a rule.
- Email subjects and bodies are plain text (`sanitize_text_field`/`sanitize_textarea_field` on save, tags
  stripped again when sending, `text/plain`). Merge tags: `{first_name} {last_name} {display_name}
  {course_title} {course_url} {site_name} {site_url} {coupon_code}`; anything else stays literal.
- An action may raise another event (enrolling fires `course_enrolled`). Chains stop after two levels and a
  rule runs at most once per learner and object in a request, so two rules that enrol into each other stop.
- Targets are checked again when a rule runs (a course unpublished since is reported, not enrolled).
- Every action writes one log row: `success`, `skipped` (already enrolled, already holds the plan, blocked
  learner) or `failed` with a message. Rows older than 90 days are pruned.

## Events and conditions

| Event | Hook | Conditions |
| --- | --- | --- |
| `course_purchased` | `ohmylms_checkout_after_create_order` (paid checkout, QPay, points checkout) | `course_ids` |
| `membership_purchased` | same, order with `_membership_id` | `membership_ids` |
| `course_enrolled` | `ohmylms_after_enrolled_student`, `ohmylms_manual_student_enrollment` | `course_ids` |
| `enrolment_cancelled` | `ohmylms_update_order_status_to_cancelled` | `course_ids` |
| `lesson_completed` | `ohmylms_after_lesson_completed` | `course_ids`, `lesson_ids` |
| `course_progress` | `ohmylms_course_completion_rate` | `course_ids`, `operator` (gte/lte/eq, default gte), `rate` 0-100 (required) |
| `course_completed` | `ohmylms_course_completed` | `course_ids` |
| `quiz_submitted` | `ohmylms_quiz_submission` | `course_ids`, `quiz_ids` |
| `assignment_submitted` | `ohmylms_after_assignment_submitted` | `course_ids`, `assignment_ids` |
| `assignment_reviewed` | `ohmylms_after_assignment_review` (learner read from the attempt) | `course_ids`, `assignment_ids`, `result` pass/fail |

Id lists hold up to 50 existing items of the right post type; an empty list means any.

## Actions

| Action | Parameters | Effect |
| --- | --- | --- |
| `send_email` | `to` learner/admin, `subject` ≤200, `body` ≤5000 | Plain-text email |
| `enrol_course` | `course_id` (published) | `Student\Enrollments::create()` with source `manual`, `source_type` `automation`, `source_ref` = rule; fires `ohmylms_manual_student_enrollment` |
| `add_to_cohort` | `cohort_id` (active) | `Cohorts::add_member()` (source `automation`); the learner must be enrolled in the cohort's course or hold its plan |
| `grant_membership` | `membership_id` (published) | A plan row without order or expiry, then `CourseSelection::sync()` |
| `send_coupon` | `coupon_id` (published), `subject`, `body` containing `{coupon_code}` | Emails the coupon code to the learner |

## Routes

All require `manage_options` (`ohmylms_automation_forbidden`).

| Method | Path | Purpose |
| --- | --- | --- |
| GET / POST | `/automations` | Paged list (`page`, `per_page`, `search`, `status` any/active/paused, `event`); create (201) |
| GET / PATCH / DELETE | `/automations/{id}` | Read; partial update validated as a whole after merging (changing `event` drops conditions it does not support unless sent); delete with its log |
| GET | `/automations/options` | Events, actions, merge tags, limits |
| GET | `/automations/choices` | Picker: `type` course/membership/lesson/quiz/assignment/coupon/cohort, `search`, or `include` ids for labels |
| GET | `/automations/log`, `/automations/{id}/log` | Paged run log (`status` any/success/skipped/failed) |

Errors: 400 `ohmylms_automation_unknown_field` / `rest_invalid_param`; 422 `ohmylms_automation_invalid` with
`data.params` keyed `name`, `event`, `conditions.<name>`, `actions.<index>.<param>`; 404
`ohmylms_automation_not_found`. Rules start `paused` unless `status` says otherwise. Hook:
`ohmylms_automation_action_ran( $rule_id, $action, $status, $context )`.

## Not covered

Actions run synchronously in the request that raised the event (no queue or delay steps). The new assignment
grading service (`Assignment\Submissions`) fires no review hook, so `assignment_reviewed` only fires from the
REST grading route that does (`AssignmentController`).
