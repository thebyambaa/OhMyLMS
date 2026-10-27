# Inline fill-in-the-blank questions

In the shared question editor, select **Fill in the blank** and write the answer in braces in the **Question** body:

`The capital of {France} is {Paris}.`

Newly authored questions show blanks at those positions and an answer bank below. Students drag a token into a blank, or select a token and then activate a blank. Repeated answers have independent tokens. Activating a filled blank returns its token to the bank. Preview supports the same placement behavior without recording responses. Each correct blank earns an equal share of the question's marks. Required questions need an answer in every blank.

The editor persists `blank_mode: drag` with newly authored curly-bracket prompts. Existing frozen versions without that setting retain their typed inputs. Drag delivery exposes the shuffled answer words needed for the bank, without exposing which word belongs in each position.

Use **Case-sensitive answers** in the question editor to control capitalization. It is on by default to preserve existing grading. Turn it off to accept both `Paris` and `paris`; Unicode capitalization, including Mongolian, is supported. The setting is saved with the question and its frozen versions, and also applies to legacy blanks.

The authored question title, including braces, is persisted and frozen with question versions. Learner delivery replaces the answers with text segments and character counts; expected answers are not sent to the student. Editing the current draft does not change grading for an existing frozen attempt.

Questions without brace answers retain their legacy separate answer fields and grading. Empty braces and unmatched braces remain literal text. The shared editor copies the prompt into the internal title for compatibility with frozen grading; authors edit only the Question body. Avoid splitting one brace answer across formatting elements.

Run `php tests/php/inline-blanks-unit.php` for parsing, rendering, frozen-delivery privacy, required-blank, and grading checks.
