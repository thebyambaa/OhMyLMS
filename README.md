# OhMyLMS

WordPress LMS with editable React feature modules, a reproducible source build and a Khan-style learner hub (`[ohmylms_student_hub]`) for learners, teachers, parents and school staff. Mongolian is the only bundled translation. This repository is the plugin; `CONTINUE-LEARNER-HUB.md` is the shared work ledger and `AGENTS.md` holds the rules every contributor and assistant follows.

## Start here

- [AGENTS.md](AGENTS.md) and [Coding standards](docs/CODING-STANDARDS.md): WordPress standards, compatibility exceptions and the lint commands.
- [Build and local setup](docs/DEVELOPMENT.md): commands, isolated testing, source-asset activation, translations.
- [React development guide](docs/REACT-DEVELOPMENT.md): component boundaries, conversion contracts and the feature workflow.
- [Work ledger](CONTINUE-LEARNER-HUB.md): current state of the learner hub and every checkpoint, newest facts first in its "Current state" section.
- [Acceptance status](docs/ACCEPTANCE.md): tested scope and compatibility limitations.

## Learners, teachers, parents and schools

- [Student hub](docs/STUDENT-HOME.md): the hub page, its settings, learner profile details and compatibility behaviour.
- [Khan-style practice, levels and skill assessments](docs/learner-experience/KHAN-ACTIVITIES.md): practice runner, optional Khan level model, mixed-skill unit tests, Level up and the authoring screen.
- [School and family setup](docs/SCHOOL-FAMILY-SETUP.md) and the [learner-experience documents](docs/learner-experience/): parent and child contract, home-learning reports, unit and practice presentation, legacy retirement, release readiness.
- [AI tutor and fast-feedback practice](docs/AI-TUTOR.md), [XP, daily goal and mastery score](docs/XP-MASTERY.md) and [streaks](docs/GAMIFICATION-STREAKS.md).

## Authoring content

- [Content Hub](docs/CONTENT-HUB.md): courses, the lesson library and the skill library (skills are a core feature).
- [Curriculum and Course Bundles](docs/CURRICULUM-BUNDLES.md), [Syllabuses](docs/SYLLABUS.md) and [Shared skills](docs/SHARED-SKILLS.md).
- [Quiz Player block](docs/QUIZ-PLAYER-BLOCK.md), [quiz and question editors](docs/question-block-editor.md), [assessment engine](docs/ASSESSMENT-ENGINE.md) and [assessment modules](docs/ASSESSMENT-MODULES.md).
- [Question bulk import](docs/QUESTION-BULK-IMPORT.md), [quiz import and export](docs/QUIZ-IMPORT-EXPORT.md) and [exam PDF import](docs/EXAM-IMPORT-IMPLEMENTATION.md).
- [Block cleanup](docs/BLOCK-CLEANUP.md): retired editor blocks and existing-content compatibility.

## Integrations and extension points

- [Extension SDK](docs/EXTENSIONS.md), [Add-ons](docs/ADDONS.md) and [frontend Interactivity contract](docs/INTERACTIVITY.md).
- [Administrator assistant](docs/ADMIN-ASSISTANT.md) and [MCP connections](docs/MCP.md): authenticated LMS tools for OpenAI, Anthropic and Gemini, including `list_unit_skills` and the draft-only `create_skill_assessment`.
- [xAPI emitter](docs/XAPI.md) and the [cmi5 design](docs/CMI5.md).

Run `npm run format` to format authored React source and `npm run check` to validate formatting, contracts, tests and the production build.
