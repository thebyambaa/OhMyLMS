# Content Hub overview REST API (`ohmylms/v1/content-hub/overview`)

Contract version 1. Server code: `includes/Rest/V1/ContentHubOverviewController.php`. Admin screen:
`assets/src/app/features/content-hub/OverviewPage.tsx` at `#/content-hub` (extension route `/content-hub`).

## Audit before

The legacy route `#/content-hub` (`assets/src/features/content-hub/ContentHub.jsx`, `hubRoutes.mjs`) is a frame
with a title and movable section tabs (Courses, Lessons, Quizzes, Exams, Question Bank, Assignments, Skills,
Curriculum). The hub address itself renders the first tab, the legacy course list. It shows no counts and no
recent items, and it has no endpoint of its own. It calls these routes:

| Severity | Finding |
| --- | --- |
| Medium | **The landing gave no overview.** `#/content-hub` opened the Courses tab. An author had to open eight tabs to see what existed or what waited for grading. The new screen lists every area with counts and links. |
| Medium | **Tab visibility ignored capability.** The frame showed every tab to every user who reached the page. Each tab's own request then failed. The new screen lists only areas the server reports and the user's capabilities allow. Lessons, curriculum and bundles stay administrator-only, as their screens are. |
| Low | `PUT /tab-preferences/content-hub` (`Extensions\TabPreferences`) stores a personal tab order and groups. It requires `manage_options` or `manage_ohmylms` and validates ids, names and colours. The new overview has a fixed order, so it does not read or write this preference. |
| OK | The Courses tab's list (`GET /courses`, now `/courses` in the new app) is already hardened (`per_page` ≤ 100, object capability checks). See [courses](courses.md). |

## Route

| Method | Path | Who | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/content-hub/overview` | `edit_posts` | 200 `Overview`, `Cache-Control: private, no-store` | 400, 401, 403 |

Argument: `recent` (integer 0–20, default 8), the number of recently changed items. The route is read-only and
writes nothing.

```json
{
  "areas": [
    { "id": "courses", "total": 5, "counts": { "published": 3, "drafts": 2 } },
    { "id": "quizzes", "total": 12, "counts": { "published": 9, "drafts": 3, "to_grade": 2 } },
    { "id": "skills", "total": 40, "counts": { "archived": 1 } },
    { "id": "curriculum", "total": 39, "counts": { "syllabuses": 3 } },
    { "id": "bundles", "total": 2, "counts": { "published": 1, "drafts": 1 } }
  ],
  "recent": [
    { "area": "quizzes", "id": 11, "title": "Fractions check", "status": "draft", "updated_at": "2026-10-30T09:00:00Z" }
  ]
}
```

- **Areas** appear in this order, each only when the user may open it: `courses`, `lessons`, `quizzes`, `exams`,
  `question-bank`, `assignments` (each needs the post type's `edit_posts`; `lessons` also needs `manage_options`),
  `skills` (`edit_posts`), and `curriculum` and `bundles` (`Curriculum\Access::can_manage()`, installed curriculum
  tables).
- **Scope**: post-type counts include every item when the user has the type's `edit_others_posts`. Otherwise
  they include only the user's own items. Trash and auto-drafts never count. `published` is `publish`, and
  `drafts` is `draft` plus `pending`. Scheduled and private items count only in `total`.
- `quizzes.counts.to_grade` is the number of `in-review` attempts in quizzes the user may grade
  (`Quiz\GradingPolicy`): every quiz for administrators, otherwise the quizzes they wrote and the quizzes in
  courses they wrote.
- `skills.counts.archived` counts skills with the archived status (`Skills\Provenance::STATUS_META`).
  `curriculum` counts every curriculum item, and `syllabuses` those flagged as syllabuses. `bundles` counts by
  status.
- **Recent** items are the latest changed posts of the post-type areas above, in the same scope. They are
  sorted by the site-local modification time, because new drafts keep a zero `post_modified_gmt`. Each item has
  its area, id, plain-text title, normalised status (`pending` → `draft`) and UTC `updated_at`. No author, user
  or learner data is returned.
- Performance: one grouped `COUNT` over `wp_posts` for the post types, one bounded `LIMIT` query for recent
  items, and one aggregate each for attempts, skills, curriculum items and bundles. Nothing is unbounded.

## Errors

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param` | `recent` outside 0–20. |
| 401 / 403 | `ohmylms_content_hub_cannot_view` | Not logged in (401), or without `edit_posts` (403). |

## Admin UI

`#/content-hub` shows a table of content areas (name linked to its screen, item total, details such as
"3 published, 2 drafts, 2 attempts to grade"). The quizzes row links to the quiz reports
(`#/reports/quizzes`). A second table lists the recently changed items, each linked to its editor
(`/courses/:id`, `/content-hub/lessons/:id`, `/quizzes/:id`, `/exams/:id`, `/content-hub/question-bank/:id`,
`/assignments/:id`). The screen also hides areas that the bootstrap capabilities do not allow. This is
presentation only: the server already left them out. The Skills catalogue keeps `#/content-hub/skills`, and its
navigation label is now "Skills".

## Tests

`tests/php/content-hub-overview-rest-integration.php` (`OHMYLMS_CONTENT_HUB_TEST=1`) checks 401 and 403
responses, argument limits, the areas an administrator and an author see, and counts as changes against a
baseline. It also checks that trash is excluded, pending items count as drafts, grading counts, skills,
curriculum and bundles, recent items (drafts included, shape, UTC times, `recent=0`) and author-only scope. It
removes every tagged fixture. App tests: `content-hub/model.test.ts` and `content-hub/OverviewPage.test.tsx`.
