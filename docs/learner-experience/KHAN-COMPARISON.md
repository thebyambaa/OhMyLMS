# OhMyLMS prototype vs Khan Academy teacher / district tools

All data in the prototype is fake. Khan facts below come only from the cited public pages.

## Sources
- K1 Unit Mission progress / Student summary: https://support.khanacademy.org/hc/en-us/articles/360031123551
- K2 Course Mastery report deprecated, replaced by Unit Missions: https://support.khanacademy.org/hc/en-us/articles/46070831983373
- K3 Assignment Scores report (red 0–59, yellow 60–99, green 100/complete; course, date and type filters; student → Individual Student Report; score → Responses; CSV): https://support.khanacademy.org/hc/en-us/articles/360031122951
- K4 Activity (minutes, skills worked on or leveled up) and Skills tab (course/unit/mastery filters, only assigned skills, View Exercise, Assign): https://support.khanacademy.org/hc/en-us/articles/360031052391
- K5 Reporting overview: https://support.khanacademy.org/hc/en-us/articles/360031129891
- K6 Administrator Homepage (Activation Summary rostered/activated/started, Skills to Proficient+ per student, Learning Time, Khanmigo usage): https://support.khanacademy.org/hc/en-us/articles/17720742489357
- K7 Admin reports (drill-down by course/site/grade/teacher/skill, Course Progression, SAT, CSV): https://support.khanacademy.org/hc/en-us/articles/17718729129741
- K8 CSV dictionary (Attempted 0, Familiar 50, Proficient 80, Mastered 100; MAP Growth learning paths): https://support.khanacademy.org/hc/en-us/articles/17167578991629
- K9 CSV export filters: https://support.khanacademy.org/hc/en-us/articles/4407513378957
- K10 Districts Skills Progress (demo copy): https://z3nhugorichard.zendesk.com/hc/en-us/articles/17901131800345
- K11 Khanmigo teacher tools (Recommend Assignments, Class Snapshot, report card comments, exit tickets, rubrics): https://blog.khanacademy.org/khanmigo-features/, https://support.khanacademy.org/hc/en-us/articles/14799047733645, https://www.microsoft.com/en-us/education/blog/2024/08/khanmigo-for-teachers-your-free-ai-powered-teaching-tool/

## Comparison
| Area | Khan Academy | OhMyLMS prototype (before) | Gap, now addressed? |
|---|---|---|---|
| Scoping | Class picker; admin drill-down by site/grade/teacher (K6, K7) | One global view | **Added** district › school › class picker |
| Mastery model | 4 named levels with fixed points and colours (K8) | Numeric % heat colours | **Added** named levels, one legend used everywhere |
| Assignment grid | Student × assignment, R/Y/G, drill to responses, CSV (K3) | None | **Added** |
| Skills per unit | Skills tab, expand, assign skill (K4, K10) | Heatmap only | **Added** stacked bars per unit, expand, assign |
| Usage / time | Minutes on the Activity tab and admin Learning Time (K4, K6) | None | **Added** min/week plus editable class goals |
| AI next steps | Khanmigo Recommend Assignments, Class Snapshot (K11) | Rule-based insights | **Added** rule-based recommendations and a 7-day snapshot (not AI) |
| District roll-up | Activation funnel, Skills to Proficient+ (K6) | None | **Added** Schools tab |
| Action queue / triage | Not described in the sources | **Stronger**: "needs your attention" queue, snooze | Kept |
| Commerce, orders, revenue | Not applicable (free nonprofit) | **Stronger** | Kept |
| Dropout risk with reasons | Not described in the sources | **Stronger**: risk tags with reasons, nudge | Kept |
| Course funnel health | Course Progression for admins (K7) | Funnel and drop-off lesson | Roughly equal |
| Command palette (Ctrl+K) | Not described in the sources | **Stronger** | Kept |
| MAP Growth, SAT, exit tickets, rubrics, report-card comments | Yes (K7, K8, K11) | No | **Not done**, out of scope |

## Improvements made (app.js)
1. Scope picker (district / school / class) on Dashboard, Students, Assignments and Skills. Fake schools: Northside (8A, 8B) and Riverside (8C, 9A).
2. Khan-style mastery levels (Not started / Attempted / Familiar 50+ / Proficient 80+ / Mastered) with one colour set and a shared legend on the heatmap, Skills and the student drawer. The prototype's Mastered threshold is 95+; Khan's is 100.
3. Content Hub › Assignments shows a student × assignment score grid with Khan R/Y/G bands, a type filter, search and per-assignment class averages. Clicking a score opens a Responses drawer (reminder, extend, retake). Clicking an assignment header opens item analysis plus "assign reteach". Clicking a name opens the profile. A CSV download is generated in the browser.
4. Content Hub › Skills shows per-unit stacked bars. Clicking a skill lists students by level and offers "Assign practice to N below Familiar". It can be sorted weakest first.
5. Account Hub › Classes shows minutes per week against an editable weekly goal and a "7-day snapshot" drawer. That drawer has an Assign button that creates a real new assignment column.
6. Account Hub › Schools shows the district roll-up: rostered → activated → active this week, Skills Proficient+ per student, average minutes and high-risk count. Its buttons scope the Dashboard or Skills to that school.
7. Dashboard: a "Recommended next assignments" panel (groups of 3 or more below Familiar). Its Assign button adds the assignment to the grid and marks it ✓. There is also a weekly-goal panel, and the heatmap click now scopes Students to that class.
8. Beyond Khan: the recommendations are one-click and they create the assignment immediately. The existing queue, risk reasons, commerce and Ctrl+K are kept.
9. Bug fix: clicking a submenu link (such as Dashboard) or a tab whose hash equals the current one fired no `hashchange`, so nothing happened. A delegated click handler now always re-routes. The router also ignores `?query` when parsing the group and tab.
