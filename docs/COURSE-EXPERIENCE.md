# Course experience

In the course editor, open **Basics → Details → Course experience** and choose
**Book mode** or **Kids mode**, then save. Existing courses default to Book mode.
The setting applies to this course only; it does not infer a mode from its level,
subject or curriculum.

Book mode retains the current course outline and player. Kids mode shows numbered
steps grouped into units, a Continue button, finished-step progress and a simpler
player. Finish the current activity to open the next available step. Completed
steps remain replayable. Labels accompany colors, and locked steps have no link.
The same ordering is enforced for direct lesson access, completion requests and
course practice. Authors can preview all steps.

Both views use the same published program, enrollment and completion records.
Switching views does not copy lessons, reset progress or grant skill mastery.
Practice steps finish when their existing course skill target is met. Content
publication and other existing access restrictions still apply.

The additive REST field `course_experience` accepts `book` and `kids` and is saved
as `_ohmylms_course_experience`. Omitting it during an update preserves its value.
This first version provides the path and player presentation; it does not generate
child-oriented lesson content or add narration, rewards or animations.

## Live Hangman

New Hangman questions default to **Live game (no marks)**. Guesses reveal letters
immediately, stop at the mistake limit and allow a restart. Latin and Cyrillic
keyboards and typed letters are supported. The answer stays on the server behind
a short-lived token tied to the signed-in user. Save the question and use Student
preview to play it; the authoring canvas does not mint learner game tokens.

Live games have zero marks, produce no skill evidence and are excluded from graded
adaptive practice pools. Existing scored Hangman
questions remain scored until their author enables Live game. Live games cannot
be selected as graded exam questions.
