# Unified course editor

Empty courses, syllabus courses and courses imported from quiz files open the same course editor. The starting-content choice only determines what is prefilled.

Units contain block lessons, practice and quizzes. Lessons are reusable WordPress lesson posts, edited through their block editor. The outline inspector edits the placement title, unit, required completion and skill connections. It does not edit the shared resource body.

## Skill connections

Select a unit and choose **Link course skills** to select existing skills without adding practice automatically. Select a block lesson to choose the skills it teaches, or a quiz to choose the skills it should assess. Each placement stores its course-local `skill_ids`.

The same skill can be selected in several lesson or quiz placements. The course keeps one outcome and one completion target per skill. Removing a placement leaves the shared resource and other placements intact.

**Skill connections** shows each course skill's teaching activities, practice and planned assessments, computed from the current draft. Selecting a listed resource opens its outline inspector. Quiz inspectors compare intended skill links against saved questions: primary question mappings count as assessment coverage; supporting mappings and quiz tags do not. A planned link alone does not establish learner mastery.

## Saving and publishing

**Save outline draft** saves units and placements together. Navigation warns about unsaved outline edits. Preview saves pending edits first, and publishing uses readiness checks and the reviewed revision. Concurrent changes retain local edits and require a reload before retrying. Shared content bodies use their resource editors and publication status.

Use the site's canonical admin address. On this Local installation, `http://800.local` is the configured WordPress origin; opening the admin through `:10004` can cause cross-origin REST writes to fail.

## Compatibility and verification

REST paths, resource identifiers and assessment contracts remain compatible. The existing legacy group helper remains available for older integrations; the current editor creates block lesson placements.

The local browser fixture exercises outline editing, saving, preview, publishing, conflicts and shared skill connections. The syllabus integration checks draft block lesson creation, skill links, save/reload, validation, authorization and rollback. Fixture records and session tokens are removed after verification.
