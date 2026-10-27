# Bundled add-ons and Assessments

Skills is a core feature, like the Question Bank: it has no Add-ons card or switch, always loads, and cannot be turned off. The skill library is the Skills tab of the [Content Hub](CONTENT-HUB.md). A saved Skills switch from an older version is ignored.

MCP is another optional add-on; see [MCP setup](MCP.md).

Question Bank is a core feature. Its former add-on card, saved switch and module configuration no longer control availability. Assessment services load through the core bootstrap.

Quizzes, the Question Bank and Assignments are the Quizzes, Question Bank and Assignments tabs of the [Content Hub](CONTENT-HUB.md); there is no separate Assessments submenu. The old `#/quizzes`, `#/assignments`, `#/assessments`, `#/assessments/question-bank`, `#/assessments/assignments` and `#/extensions/question-bank` addresses open the matching tab, and the quiz and assignment editors and reports keep the Content Hub menu entry highlighted. Original list and editor URLs remain usable. Course creation keeps its existing workflow. These features have no Pro subscription requirement; normal WordPress permissions still apply.

Integration requirements recorded when this navigation was grouped:

- Add reusable bank questions to quizzes: **done** (the bank picker in the quiz editor, see [ASSESSMENT-ENGINE.md](ASSESSMENT-ENGINE.md)).
- Draw random quiz questions based on skills: **done** (skill question pools; mixed-skill quizzes and unit tests are built from them, see [learner-experience/KHAN-ACTIVITIES.md](learner-experience/KHAN-ACTIVITIES.md)).
- Create questions or quizzes inside assignments: not built.

Trusted project modules still use `OHMYLMS_ENABLED_MODULES` and `ohmylms_enabled_modules`. The core Skills module always loads, whatever is listed. The legacy `question_bank` module ID is ignored.

Run `php tests/php/addons-unit.php off`, `skills`, `bank` and `both` to check that legacy saved switches change nothing. `tests/js/content-hub.test.mjs` verifies the three assessment tabs, the old Assessments addresses and that the existing screens are reused, and `tests/browser/assessment.spec.cjs` drives them in a browser.
