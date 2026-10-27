# OhMyLMS extensibility scope

Status (checked October 19, 2026): **scope proposal of September 24, 2026; the course-experience part is not built.** No experience
registry, per-course Experience tab or Learning Path package exists in the code (searches of `includes/` and `assets/src/` find
none). What does exist is the extension surface in [EXTENSIONS.md](EXTENSIONS.md): layouts, question types, lesson types, activities,
membership settings and slots, plus the numeric-answer example in `examples/extensions.php`. The learner experience moved instead to
the Khan-style learner hub ([STUDENT-HOME.md](STUDENT-HOME.md)). Read the rest of this file as a backlog idea, not as current
behaviour; confirm with the owner before building from it.

## Product goal

Build one LMS engine that supports independently installable course experiences and question types. An administrator can give different courses different student interfaces, including a Duolingo-style learning path, without duplicating courses, losing progress, changing the site's WordPress theme, or editing LMS core files.

This is the implementation scope and acceptance target, not a statement that all capabilities below exist today.

## Course experiences

A course experience is a coordinated package covering the course overview, chapter/path map, lesson player, quiz presentation, navigation, progress display and completion screen. It can provide its own assets and schema-validated configuration. A theme may use PHP templates, React, or both.

The course editor should offer an Experience tab with installed choices, descriptions, preview, configuration and a saved per-course selection. The initial choices are Classic and Learning Path. Existing courses default to Classic. Switching the experience preserves course/lesson/question identifiers, enrollments, attempts and completion records. Admin preview does not mark student progress or consume quiz attempts.

Content-level layout overrides are an advanced feature. Their precedence must be explicit and compatible with the existing layout inheritance rules. Missing or disabled experience packages fall back to Classic while retaining their saved settings.

Learning Path first release:

- Chapter/unit map with ordered lesson and quiz nodes.
- Clearly displayed available, locked and completed states derived from core access/progression rules.
- Resume the learner's next available activity.
- Focused lesson and quiz views with progress and a completion screen.
- Mobile and keyboard support.

Hearts, streaks, XP, leagues, adaptive learning and additional unlock rules are separate behavior extensions. They are not assumed merely because the interface resembles Duolingo.

## Shared learning engine

Experiences consume a documented, versioned learner contract for course structure, permitted content, current progress, available actions, quiz attempts and submission results. Audit existing endpoints and fill gaps rather than duplicate working services.

Enrollment, membership entitlements, prerequisites, timers, attempt limits, grading and completion remain server-authoritative. A theme cannot unlock paid lessons or award completion by changing browser state. Learner payloads must exclude answer keys, private editor settings and other students' data. Experience settings that affect access require validated server behavior, not presentation-only checks.

## Question types

An installed question extension supplies a stable type ID, label, settings schema, admin editor, student renderer, answer schema/validation, server grading and review/results rendering. Support automatic and manual grading and define how each renderer handles unanswered, in-progress, submitted and reviewed states.

All experiences should support a common question rendering boundary. A theme may enhance a question's presentation; otherwise it uses the question extension's default renderer. Changing course themes must not change answer semantics or grading. Unsupported types must be identified in authoring and fail safely in delivery rather than silently receiving a score.

Use a small numeric-answer add-on with configurable tolerance as the first end-to-end example. Persist and reopen its settings, attempt it in both Classic and Learning Path, grade on the server, and display its review. Preserve existing built-in question behavior.

## Membership extensions

Retain the membership configuration goal: separately registered settings with schema validation, editor controls, namespaced persistence and explicit server hooks for any entitlement changes. Presentation belongs to the experience; membership rules belong to the learning engine. Prove this boundary with one configurable membership add-on after the course/question vertical slice.

## Existing foundation and gaps

Available foundations include `Registry`, `Layouts`, `QuestionTypes`, namespaced extension settings, lifecycle events, and React editor/slot registration. Layouts support course/chapter/lesson/quiz contexts. Their current selection UI is a separate content-ID-based admin page, not a complete experience picker in the course builder.

The existing question API covers render, validate and grade, and editor integration exists. A coordinated experience registry, learner-facing contract, theme-independent question review boundary and installable Learning Path example still need implementation and verification. The React SDK currently depends on the opt-in source build; the release must establish supported asset loading for extensions.

## Delivery order and acceptance

1. Course experience registration, per-course persistence and editor selection, with Classic fallback. Verify permissions, invalid settings, inheritance, preview and switching with existing progress.
2. Learner contract plus the Learning Path package. Verify actual enrollment checks, locked nodes, resume and persisted lesson/quiz completion.
3. Complete question extension contract and numeric example. Verify authoring, validation, attempts, grading, review, timeout/manual behavior where applicable, and both experiences.
4. Membership add-on and developer documentation. Prove a separate plugin can register configuration and affect an authorized server operation without core edits.
5. Compatibility and release checks: built-in questions, existing courses, missing extensions, responsive/keyboard use, asset loading, rollback and the main-site smoke test.

Develop and test in the isolated site before enabling the implementation on the working site. Completion means another developer can install an experience or question add-on without patching LMS core, and the same course/progress works across supported experiences.

## Outside this scope

A rewrite of all admin screens, a new payment system, a theme marketplace, arbitrary uploaded executable code, a visual page builder, a mobile app and a full Duolingo gamification clone. Refactor existing modules only where the extension contracts require it.
