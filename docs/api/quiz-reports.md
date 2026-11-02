# Quiz reports REST API (`ohmylms/v1/reports/quizzes`)

Contract version 1. Server code: `includes/Rest/V1/QuizReportsController.php`. Admin screen:
`assets/src/app/features/quizzes/QuizReportsPage.tsx` at `#/reports/quizzes` (extension route `/quiz-report`). The
per-quiz attempts and grading screen is `#/quizzes/:id/attempts` (see [quizzes](quizzes.md)).

## Audit before

Before the cutover deleted the recovered router, the legacy address `#/quiz-report` (without an id) had **no route** in it
(`assets/src/application/routes.js` defined only `/quiz-report/:id` and `/quiz-report/:id/grade-quiz/:quizId`).
The daily instructor digest (`Emails\CreatorsEmail\QuizSubmitted`) links to it anyway. Quiz statistics across
quizzes existed only as the "No. of Submissions" column of the legacy quiz list
(`assets/src/application/shared/quiz-list.js`). That column had search, course and status filters, sorting by
title, date and submissions, and five rows per page, and each count linked to `#/quiz-report/:id`.

| Severity | Finding |
| --- | --- |
| High | **The legacy list and per-quiz report were authorised by `edit_post`**, so any editor saw every student's name, email and answers. `GET /quiz/{id}/report` returned every attempt, unpaginated, with `GROUP_CONCAT`ed answers. Already removed and replaced; see [quizzes](quizzes.md#audit-before). The overview applies the narrower `GradingPolicy` scope and returns aggregates only. |
| Medium | **`number_of_submissions` was computed in PHP after pagination** in `GET /quiz` (also removed). Sorting by it ordered only the current page, and totals counted quizzes the user could not see. The new route sorts and counts in SQL over the user's scope. |
| Medium | **The course filter loaded `GET /ohmylms/v1/courses` without a page size** to fill a dropdown. The new screen searches `GET /quizzes/courses` (≤ 50 per page, author-scoped). |
| Low | **Broken digest link**: `LegacyRoutes::url( '/quiz-report' )` opened a blank legacy screen. Mapping `/quiz-report` to `/reports/quizzes` fixes it. |
| Low | Legacy result badges compared `total_marks >= passing_mark` per row in the browser. The overview computes the same comparison (marks, not percent, as `Quiz::get_passing_grade()` does) in one SQL query per page. |

## Routes

| Method | Path | Who | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/reports/quizzes` | quiz `edit_posts`; rows limited to quizzes the user may grade | 200 rows + `X-WP-Total`, `X-WP-TotalPages`, `Link`; `Cache-Control: private, no-store` | 400, 401, 403 |
| GET | `/reports/quizzes/summary` | as above | 200 totals | 400, 401, 403 |

**Scope** (`Quiz\GradingPolicy`): administrators (`manage_options`) see every quiz. Other users see only the
quizzes they wrote and the quizzes in courses they wrote. An editor with `edit_others_posts` therefore sees
fewer quizzes here than in `GET /quizzes`, the same set that the attempts screen lets them open. A user who may
grade nothing gets an empty list and zero totals.

**Filters** (both routes): `search` (title, ≤ 200 characters), `course` (id ≥ 1, quizzes placed in the course
through a chapter), `status` (`all` = publish/draft/pending/future/private, `publish`, `draft`), `activity`
(`all`, `attempted` = at least one attempt, `to_grade` = at least one `in-review` attempt). The list also takes
`page` (≥ 1), `per_page` (1–100, default 20), `orderby` (`last_submitted` (default; quizzes without submissions
last), `attempts`, `to_grade`, `title`, `created_at`) and `order` (`asc`/`desc`, default `desc`). Ties are
broken by id. A page past the end answers 400 `ohmylms_quiz_report_invalid_page`.

```json
{ "id": 11, "title": "Fractions check", "status": "publish", "course": { "id": 5, "title": "Maths 101" },
  "attempts": 4, "learners": 3, "in_progress": 1, "to_grade": 1, "completed": 2,
  "average_score": 5, "passing_score": 5, "passed": 1, "pass_rate": 50,
  "last_submitted_at": "2026-10-04T10:00:00Z", "can_edit": true, "can_grade": true }
```

- `attempts` counts every attempt. `in_progress`, `to_grade` (`in-review`) and `completed` count each status.
  `learners` counts distinct students.
- `average_score` is the mean marks of completed attempts (null without any). `passing_score` is the pass mark
  in marks (0 = none). `passed` and `pass_rate` (percent of completed attempts at or above the pass mark) are
  null without a pass mark, and `pass_rate` is also null without completed attempts.
- `last_submitted_at` is the latest end time of a submitted attempt, converted from site time to UTC.
- `status` is `publish` (publish or scheduled) or `draft`. `course` is the first course whose chapter contains
  the quiz, or null.
- **No student data**: rows and totals never contain names, emails, ids or answers.

Summary: `{ "quizzes": 22, "attempts": 4, "learners": 1, "to_grade": 1, "completed": 3 }` over every quiz that
matches the filters. `learners` counts distinct students across those quizzes.

Performance: one query each for the total, the page and the summary. Each joins a per-quiz aggregate of
`wp_ohmylms_quiz_attempts` (grouped by the indexed `quiz_id`). The page adds one query for courses and one for
passes, and pass marks come from primed post meta. On very large attempt tables the aggregate is the cost to
watch. A per-quiz statistics table would be the next step.

## Errors

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param` | Enum, range or length validation (`per_page` > 100, unknown `orderby`/`status`/`activity`, `course` < 1, `search` > 200). |
| 400 | `ohmylms_quiz_report_invalid_page` | `page` past the last page. |
| 401 / 403 | `ohmylms_quiz_report_cannot_view` | Not logged in (401), or cannot write quizzes (403). |

## Admin UI

`#/reports/quizzes` shows totals, then filters for title search, status, activity, course search and choice,
and sort. The table lists quiz (linked to its attempts), course, status, attempts, learners, to grade,
completed, average marks, pass rate ("50% (1 of 2)"), last submission and actions (Attempts, and Settings when
the user may edit), with pagination. Filters, sort and page are kept in the address (for example
`#/reports/quizzes?activity=to_grade`), so views can be linked and survive reloads. The Content Hub overview
links here from its Quizzes row.

## Tests

`tests/php/quiz-reports-rest-integration.php` (`OHMYLMS_QUIZ_REPORTS_TEST=1`) checks 401 and 403 responses,
row shape and figures (attempts, learners, statuses, average, pass mark, pass rate, course, UTC time), the
absence of student data, every filter, sorting, paging with `Link`, argument validation, the summary and
`GradingPolicy` scope (quiz author, course author, editor and an author without quizzes). It removes every
tagged fixture. App tests: `quizzes/reportsModel.test.ts` and `quizzes/QuizReportsPage.test.tsx`.
