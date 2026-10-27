# Course readiness report

The independent course outline editor now combines the existing server publication checks and local lesson checklist in one report. No publication rule has changed.

- Saved-draft blockers include completion requirements, unpublished resources, and insufficient approved question or family counts for required outcomes.
- All linked optional skill outcomes also receive question-pool guidance. Their gaps do not block publication.
- Lesson guidance uses current unsaved placements for activities, skill links, teaching and assessment coverage. It is advisory.
- While the outline is dirty, saved publication results are hidden and authors are asked to save explicitly. Saving, reloading, publishing or pressing Check readiness again refreshes the server results. Failed refreshes clear prior results and provide a retry button.
- Lesson and skill links select the appropriate lesson inspector. Resource links select the placement or open its editor in a new tab. Completion issues open the course completion controls. Question gaps offer skill-scoped import and a separate question-bank tab for review and approval; the author must check readiness again afterward. Restricted course pools still apply.
- The existing CourseProgram readiness report retains its required-only skills field and ready semantics. The additive practice_skills field includes optional outcomes and adds required/group_id descriptors. It uses the existing authorized author question pool and exposes counts, never answer keys.

Verification: learner journey integration checks cover optional versus required gaps, publication-check agreement, exact unpublished resource identity, and approved/non-secure question filtering. The outline browser test covers the unsaved state, lesson repair navigation, refresh failure/retry, saving, publication and stale revision protection.
