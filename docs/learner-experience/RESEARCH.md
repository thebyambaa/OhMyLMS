# Learner experience reference research

Observed October 15, 2026, in the user's existing signed-in Khan Academy account.
This records navigation and interaction patterns, not exported learner data.

Status (October 19, 2026): reference notes, kept as the evidence behind the learner hub design. They
describe the external product as it was observed, not OhMyLMS. Where they say a teacher interface is
out of scope, that was superseded: the teacher, parent and school workspaces were built (see
[CONTINUE-LEARNER-HUB.md](../../CONTINUE-LEARNER-HUB.md)). Product decisions that still apply:
no Tutor Me, no Teacher Tools, no learner-facing roadmaps, and a class is a student roster. Later
observations of exercises, quizzes and tests are in [KHAN-ACTIVITIES.md](KHAN-ACTIVITIES.md); the
data mapping is in [MAPPING.md](MAPPING.md).

## 1. Evidence and limits

The live reference was the Khan Academy Classroom application on
`https://classroom.khanacademy.org`. The learner entry route is `/learner/class`;
the active class adds an account-specific identifier. Do not put that identifier
or the class share code in repository documentation.

The live application differs from older public Learner Home articles. Prefer the
observed interface when planning the new UI. Some details may vary by edition,
role, language, classroom membership, and future product updates.

The reference account used dark appearance. Its light theme was not inspected
to the same depth. No Khan Academy source code, proprietary assets, teaching
content, roster, or private learning data was copied into OhMyLMS.

## 2. Learner shell

Observed main navigation:

- Classes
- Tutor Me
- Achievements
- History
- Browse Subjects

Settings, Help, feedback, and the account/logout control sit lower in the sidebar.
The shell uses a persistent left rail, icons with labels, a clear selected state,
and content panels separated by subtle borders. The top area changes with context.

Design implication: use the same shell and spacing system throughout OhMyLMS.
Membership is an OhMyLMS addition. Tutor Me must not be fabricated: OhMyLMS's
administrator assistant is not evidence of a learner tutoring feature.

## 3. Class interface

The selected class appears in a top selector. The main work area contains assigned
activities, or a large empty state with a library action when nothing is assigned.
Alongside it are compact progress and engagement summaries.

Mastery is shown as distinct levels. Engagement indicators are separate from
mastery and assignments. The reference also showed class rewards/challenges.

Implications:

1. Keep the class selector visible while looking at class work.
2. Make the next useful action prominent, including in an empty class.
3. Keep assignment completion, skill mastery, and engagement separate.
4. Use only engagement data that OhMyLMS actually supports. Do not invent class
   challenges, rewards, or mastery counts to make the screen look populated.
5. An independent learner must be able to bypass classes entirely.

## 4. Subject library

The subject library has a heading, short guidance, and expandable subject groups.
Expanding a group reveals course links. The inspected test-preparation group led
to a mathematics course with an overview and units.

The browse route was `/learner/library/en`. A selected course used a deeper
subject/course route. The interface did not require assigning the course through
a class before browsing its learning material.

Implications:

- Present subjects and their courses before commerce-style promotional cards.
- Support search, a visible selection, and return to all subjects.
- Use OhMyLMS curriculum placement as the source for grouping, rather than
  reviving retired course-category/tag taxonomies.
- Keep previewing content, following a collection, and enrolling/purchasing
  clearly distinct actions.

## 5. Course overview

On course selection, the left side changed to a course list within the selected
subject, with a return link to the subject library. This was a context-specific
navigator rather than the full general learner menu.

The main area contained:

- Course title and description.
- Counts of units and skills.
- A prominent continue-learning action.
- An expandable progress summary grouped by unit.
- A list of unit accordions, plus an expand-all control.
- Lessons/articles, practice exercises, quizzes, and unit checks within units.
- A course-level challenge entry when the course supported it.

Skill progress was visible separately from content navigation. Units with no
exercises did not pretend to have a skill-mastery score.

Implications:

- Course → unit → skill/lesson/checkpoint is the useful learner hierarchy.
- Use existing published programs and their enrollment versions.
- A chapter can be presented as a unit without renaming its public identifier.
- Do not calculate mastery by averaging unrelated lesson completion percentages.
- A course challenge is conditional on a real configured assessment; it is not a
  decorative button to add to every course.

## 6. Opening a lesson

Opening the continue-learning article moved to a focused activity layout with an
exit control, activity title, lesson navigation, and a completion action. It also
showed an assistant-related area with its availability state.

Only navigation and reading were inspected. No assessment answers were submitted
and the completion button was not used during this inspection.

Implications:

- Treat course browsing and activity taking as related but distinct contexts.
- Remember the originating course/unit so exiting returns to the right place.
- Keep current assessment, attempt, evidence, enrollment, and completion services.
- Do not imply that a learner AI assistant exists just because the reference
  displays an assistant pane.

## 7. History and settings

History provided overview and score-oriented subviews. Settings grouped account,
personalization, appearance, assistant options, notifications, and classes.

Account settings included a parent/guardian connection flow. The reference's
privacy and account-management rules are specific to that product; they are not
automatically suitable for OhMyLMS.

Implications:

- Profile should become a coherent settings experience in the same design system.
- Parent visibility must follow approved OhMyLMS relationships and data scopes.
- Password, email, linked-login, and membership changes need explicit, validated
  service flows. They cannot be simulated with front-end state.
- Keep child-specific permissions distinct from global account administration.

## 8. Teacher interface: observed, excluded from implementation scope

Before the user excluded teacher tools, the existing teacher class settings and
roster were inspected. They distinguished linked course coverage from assigned
work, and offered class-link/code and roster-import choices. Pending invitations
were distinguished from active students.

No invitations were sent, roster changes made, or class settings changed during
that inspection. Teacher tools, imports, and teacher dashboard reconstruction
are now explicitly out of scope.

## 9. Public background references

These provide background rather than an exact specification for the currently
signed-in Classroom application:

- [Khan Academy Learner Home guide](https://support.khanacademy.org/hc/en-us/articles/360030629852-What-is-my-Learner-Home-page-and-what-can-I-do-there)
- [IXL student dashboard overview](https://blog.ixl.com/2023/08/13/the-ixl-dashboard-empowering-students-to-own-their-learning/)
- [Moodle dashboard documentation](https://docs.moodle.org/en/Dashboard)

Khan Academy is the user's primary reference. The other products do not override
the requested direction.

## 10. Research still useful before each implementation slice

- Inspect the live reference's responsive sidebar and library on a small viewport.
- Inspect a unit containing both lessons and exercises, without submitting work.
- Check the desired History and Settings appearance directly before rebuilding them.
- Clarify how learners should pin/follow courses in OhMyLMS; browsing a reference
  course alone does not prove the intended persistence/enrollment contract.
- Use synthetic OhMyLMS fixtures to demonstrate course, roadmap, and bundle
  screens. Do not import the user's Khan Academy roster or progress.

## 11. Second inspection (dark theme, desktop width), October 15, 2026

Observed in the signed-in Classroom learner app. Values are computed styles, recorded
as design guidance. No learner data, class codes or identifiers are kept here.

### Visual tokens (dark)

| Token | Observed value |
|---|---|
| Font | Plus Jakarta Sans, then Noto Sans, then sans-serif (Cyrillic is covered by Noto Sans) |
| Page surface | `#252531`; sidebar and cards on a darker `#151521` |
| Card | 8px radius, 1px border `#4A4C53`, small shadow `0 2px 2px rgba(21,21,33,.6)` |
| Hero/banner panel | Deep indigo fill (about `#1f1f5a`), centered title, one line of guidance |
| Primary button | `#5753FA`, white text, 12px radius, 54px tall, hard bottom shadow `0 6px 0 #363498` |
| Secondary button | Transparent, indigo text and border, 12px radius |
| Nav rail | 240px wide, items 40px tall, 8px radius, 14px text, idle `#A0A1A4` |
| Nav selected | Fill `#222149`, text `#6C82FF`, icon matches |
| Page title | 20px, weight 700 |

### Shell and routes

- Rail: Classes, Tutor Me, Achievements, History, Browse Subjects. Lower: Settings, Help,
  Leave feedback, then the account row. The account row opens a log-out confirmation, so
  it is not a role switcher.
- Role switching is the **Switch role** dropdown beside the Settings title.
- Routes: `/learner/class/<id>`, `/learner/library/<lang>`, `/learner/history/overview`,
  `/learner/settings/account`. The rail has a close/collapse control beside the logo.
- Class page: class selector top-left, gem counter top-right. A hero card with an empty
  state and primary "Go to library" action sits beside three right-column cards: Mastery
  learning (Attempted, Familiar, Proficient, Mastered with counts), Missions completed
  (daily, weekly, unit) and a Gem challenge progress bar.

### Browse subjects

- Heading banner, then "All subjects": a **two-column grid** of tiles. Each tile has a
  colored subject icon, a bold name and a chevron.
- A tile expands in place into a short link list ending in "See all <subject>".
- Choosing a course changes the top bar to a back arrow and the subject name. The left
  panel becomes a course list for that subject, with the selected course highlighted
  and checked.

### Course overview

- Hero card: title; "Course, N units, N skills"; description; Continue learning button.
- Collapsible **Course progress** card: a percentage chip, "x of N skills to Proficient",
  and a bar. Beneath it, each unit is a line of small round icons, one per activity
  (pencil is an exercise, bolt a quiz, star the unit test), colored by mastery. A unit
  with no exercises says so instead of showing a score.
- Expanded unit: description, a "Continue this unit" button, numbered lesson groups, and
  rows with a status icon, title and chevron (check means complete, document icon means
  article or video).

### History

- Tabs: Overview and Scores. Filters for period ("This week") and activity type.
- Totals at right ("exercise min", "total learning min"). Table columns: Activity, Date,
  Level, Change, Correct problems. Rows carry a small type icon.

### Settings

- Tabs: Account, Personalization, Appearance, Khanmigo, Notifications, Classes.
- Account is stacked cards: Basics (name) then Password, each with a disabled-until-dirty
  save button and the note "Saving changes will reload the page."

### Takeaways for OhMyLMS

1. The look is a calm dark system with one saturated indigo accent. The tactile hard-shadow
   button is the most recognizable detail. Build the tokens once and support a light set.
2. The Course progress strip of one icon per activity needs real per-activity mastery
   from existing services. Do not fake colors.
3. Settings uses save-on-card with disabled buttons until changes. Use the same pattern.
4. The Switch role control belongs with Settings, not the account row. Teacher views stay
   out of scope unless the user widens it.

## 12. Role switch and teacher shell (observed October 15, 2026)

- **Switch role** is a dropdown at the top of Settings, beside the page title. Options are
  Teacher (external-link icon, opens the teacher app) and Learner (current, marked).
- Teacher app route: `/teacher/classes`. Same shell family as the learner app (dark rail,
  logo, indigo accent) but denser, with a class selector in the top bar and a global
  "Search or ask anything" field.
- Teacher rail: Dashboard; My Classes with sub-items Assignments, Roster, Reports, Class
  Settings; Browse Subjects; Teacher Tools. The account row at the bottom has an up/down
  chevron, so it is an account menu rather than a log-out button.
- Dashboard: "Welcome, <name>", a search box, three quick actions (Create an assignment,
  Browse Subjects, Class Snapshot), then "My classes" cards showing average skills to
  proficient and active students.
- Assignments: breadcrumb, filters (status, type, students), "Show overdue" toggle, search,
  sort. Each assignment card has a type tag, course, due date, an Overdue badge and a
  stacked progress bar (proficient or mastered, completed only, in progress).
- Teacher tools remain **out of scope** until the user widens it. The learner/teacher role
  switch could be offered in OhMyLMS Settings later, using existing school roles.
