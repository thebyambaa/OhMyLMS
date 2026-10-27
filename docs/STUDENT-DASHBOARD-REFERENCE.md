# Student dashboard reference study

Status (October 19, 2026): this describes the **legacy student dashboard** (`templates/profile/dashboard-content.php`,
`Practice\Dashboard`, route `PUT /ohmylms/v1/student/dashboard-theme`). Learners now use the Khan-style learner hub
([STUDENT-HOME.md](STUDENT-HOME.md)); the dashboard shortcode and the generated dashboard page only link to the hub.
The legacy renderer and its Meadow, Ocean and Sunset themes run only when the hub is disabled. The IXL study and
the data decisions below remain accurate for that legacy dashboard; the browser spec is
`tests/browser/student-dashboard.spec.cjs`.

Reference: [IXL for students: Take charge of your learning with the Student Dashboard](https://www.youtube.com/watch?v=MfPxveAWHZc). Reviewed the visible dashboard screens in the 65-second video. No transcript was available. This document distinguishes observed features from the proposed OhMyLMS adaptation.

## What the video shows

| Approximate point | Observed feature |
| --- | --- |
| 0:10 | Personalized greeting and avatar above a wide work panel. Tabs: From your teacher, Recent skills, Recommendations. A narrower right column holds a diagnostic action and a study plan. |
| 0:20 | Recent skills appear as cards with question previews, current scores, last-practiced dates, and Continue links. |
| 0:30 | A study-plan card links to a personal SAT study plan. |
| 0:35–0:40 | A weekly summary shows questions answered, minutes practicing, skills with progress, comparisons with weekly averages, and a link to a Student Summary Report. |
| 0:35–0:45 | Awards and leaderboard panels appear side by side. Awards have previous/next controls and a link to the awards page. The leaderboard card includes the competition name, status/date, rank, and learner result. |
| 0:50–0:55 | The same dashboard uses different scenic backgrounds, including an underwater theme. White content cards retain their layout over each theme. |

The video is a promotional overview, so it does not establish assignment workflows, recommendation formulas, time tracking rules, diagnostic scoring, or theme persistence behavior.

## Proposed OhMyLMS layout

1. A compact greeting with the learner's existing avatar, name, and a Customize dashboard action.
2. **What should I work on?** as the primary area. A wide tabbed panel contains Continue learning, Recent skills, and Recommendations. Add From your teacher only when an actual learner-specific assignment source has been verified.
3. A narrow companion column contains the existing learning streak card, with today's status, freeze balance, and the next badge milestone. Course bundles can supply a study-plan card where a learner has an applicable bundle.
4. **How am I doing?** shows this week's real activity and skill progress. Course counts remain available as secondary information.
5. **My achievements** shows earned badges, the learner's current level, and progress toward the next achievement. An adjacent leaderboard card reuses existing eligible course rankings.
6. Existing enrolled courses and memberships remain reachable through the account navigation and relevant dashboard links.

On mobile, stack the work panel and companion column, wrap tabs, and stack summary/achievement cards. Use readable foreground colors, keyboard-accessible tabs, visible focus states, and reduced-motion support.

## Existing implementation to reuse

| Dashboard area | Existing source | Work still required |
| --- | --- | --- |
| Continue learning | `templates/profile/dashboard-content.php`, student course progress and resume URLs | Present as compact actionable cards. |
| Recent skills | `includes/Practice/Sessions.php` and persisted practice sessions/items | Query only the signed-in learner's latest skill activity and provide verified practice/resume links. |
| Recommendations | `includes/Skills/Recommendations.php` | Display existing help, review, and ready suggestions with their reasons. |
| Skill progress | `includes/Skills/Mastery.php`, `includes/Skills/Evidence.php` | Summarize real learner evidence and state. Preserve existing mastery definitions. |
| Streaks | `templates/profile/streak.php`, `includes/Engagement/Streak.php` | Adapt placement and show the configured next milestone. |
| Awards and levels | Existing Badge, Level, Point helpers and profile display | Build a compact achievement panel using existing earned records and feature switches. |
| Leaderboard | `includes/Engagement/Leaderboard.php` | Present an eligible course ranking with an explicit course/ranking label. |
| Themes | Current design tokens and profile settings | Add a small approved set of original themes and save the learner's choice. |

## Data and behavior decisions

- Weekly counts must use the learner's calendar boundaries and real persisted activity. Keep empty states truthful.
- Practice session start/completion timestamps exist, but their difference includes idle time. Label it as elapsed session time if used; active practice minutes require dedicated activity tracking.
- A count of skills practiced is different from skills whose mastery improved. Use precise labels and derive each measure from its corresponding records.
- Show comparisons with prior weeks only when meaningful history is available. Do not invent averages for a new account.
- Teacher ownership of a course does not imply that a particular skill was assigned to a learner. Verify assignment storage before exposing an assignment tab.
- The IXL diagnostic snapshot and SAT plan represent separate product capabilities. The OhMyLMS adaptation should use its actual assessments and bundles rather than add unsupported diagnostic scores.
- Streaks, badges, points, levels, and leaderboard visibility must honor their existing settings. Theme changes must not alter achievements or learning data.
- Use original visual assets and OhMyLMS labels while following the reference's card hierarchy and friendly presentation.

## Implemented dashboard

The default student dashboard now uses this layout, available through the existing dashboard shortcode/block and profile dashboard template. Continue learning, recent skills, review recommendations, real weekly practice totals, achievements, eligible course leaderboards, and course bundles reuse existing data and services. Streaks and their next unearned milestone appear when enabled. Membership cards remain available for enrolled members.

Meadow, Ocean, and Sunset themes use original CSS scenery and persist per learner through the nonce-authenticated `PUT /ohmylms/v1/student/dashboard-theme` route. Only the current learner's preference can be changed. Keyboard-accessible tabs, mobile stacking, honest empty states, and failed-save feedback are included. Theme template overrides are still supported.

Weekly summaries count meaningful practice answers, distinct skills practiced, and local calendar days with practice. They do not estimate active minutes or present unverified teacher assignments or diagnostic scores.

Validation: 10 isolated student dashboard integration checks, 76 gamification/streak regression checks, and two desktop/mobile browser tests passed. Browser tests cover keyboard tabs, real rendered course/skill cards, theme success/failure feedback, and overflow. Live student dashboard rendering was also inspected.
