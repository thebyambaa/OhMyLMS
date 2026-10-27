# Activity catalogue

The remaining core activities are implemented as 16 Question Player modes and a Maths generator wizard. Choose a mode in **Question Player → Play mode**, or insert its named OhMyLMS block variation. Each variation references bank content. The source question editor follows the response family below; it does not duplicate questions into separate game-specific formats.

| Activity | Authoring source | Player behavior |
| --- | --- | --- |
| Spin the wheel | Matching or Statement | Numbered wheel, random draw without repeats, card reader |
| Open the box | Matching or Statement | Choose numbered boxes to reveal their content |
| Speaking cards | Matching or Statement | Random prompt draw without repeats; enlarged reader |
| Flip tiles | Matching | Two-sided tile board with enlarged selected-card reader |
| Find the match | Matching | Choose the matching answer; successful rounds eliminate it; incorrect rounds can be retried |
| Pair or No Pair | Matching | Balanced real/mismatched pairs, generated privately; binary choice and review |
| Balloon pop | Matching | Moving balloons; selected label drops onto the current prompt |
| Gameshow quiz | Single choice or True/false | Timed rounds, speed bonus, one 50/50 lifeline per game |
| Timed True or false | True/false | Timed binary rounds and saved practice feedback |
| Win or lose quiz | Single choice or True/false | Start with 100 practice points; stake 10, 25 or 50; correct answers win the stake, errors lose it |
| Maze chase | Single choice or True/false | Arrow/touch movement through walls to answer targets; avoid the chaser |
| Flying fruit | Single choice or True/false | Moving answer targets selected by pointer or touch |
| Whack-a-mole | Single choice or True/false | Answers pop up in a nine-hole board; select the correct one |
| Airplane | Single choice or True/false | Steer into answer clouds, with direct selection also available |
| Speed sorting | Sort into groups | One item per timed round; choose its group |
| Word magnets | Build from tiles, Words mode | Free composition board; drag or nudge words; positions saved; ungraded |

Matching games require at least two pairs with distinct prompts and answer labels. Answer labels must remain distinct across the selected questions. Timed choice games require one correct answer and 2–6 choices. Sorting supports 2–8 groups. All new activities cap the issued content at 100 items. The author picker filters compatible approved questions and launch validates again. Random skill selections skip incompatible questions.

Game state uses the existing authenticated session lifecycle and frozen question instances. The server stores phase, progress, deadlines, points and action revision. Pause freezes the remaining round time; resume creates a new deadline from that remainder. Refresh resumes the same round. Stale and duplicate commands cannot award points again. Arcade avatar positions restart when their round view is reconstructed; the saved round, timer and score remain authoritative. Word magnet positions are stored on the server.

Practice scoring is separate from academic grades and skill evidence. Correct rounds earn 10 points. Gameshow adds up to 30 points for remaining time. Win or lose uses its selected stake instead; a balance below 10 can be staked in full. A zero balance ends that game. Wrong or expired matching rounds do not eliminate their target; Next round retries them until they are matched. A matching activity can therefore require more turns than its content count.

The public API omits current answer keys. It returns opaque option IDs and public choices; correct IDs appear only after submission in that round's review. Pair verification samples true/mismatched pairs using private ordering, rather than exposing correctness through the public round number. No client-reported points or elapsed times are accepted. Arcade collisions can end a practice round without creating academic evidence.

All arcade modes have stationary answer buttons and reduced-motion behavior. Maze and airplane also have native direction buttons and arrow-key input. Pause controls and animation cleanup are shared. Native prompts, answer text and progress remain available outside moving graphics. These are functional OhMyLMS adaptations: Wordwall's visual themes, slingshot physics, multiplayer leaderboards, sound libraries, printable layouts and subscription-specific settings are not reproduced.

## Maths generator

Choose a **Numerical** question, expand **Maths generator**, choose a topic and number range, then apply. The wizard supports addition, nonnegative subtraction, multiplication, exact division, percentages, fractions with a shared denominator (decimal answers), and linear equations. Ranges are positive integers from 1 to 1000. Division generates a product divided by a nonzero factor. Fractions use a denominator from 2 to 12; equation coefficients range from 1 to 9. **Pythagorean triples** are built, not searched for: two coprime numbers m > n of opposite parity in the range give the shorter sides m²-n² and 2mn and the hypotenuse m²+n² (the range needs two numbers). **Quadratic, two distinct solutions** draws two different whole solutions between minus the maximum and the maximum (each at least the minimum away from zero, with a nonzero sum), derives x² + bx + c = 0 from them with b = -(p+q) and c = pq, and asks for the larger solution. **Quadratic, one repeated solution** builds (x - p)² = 0 (answer p). **Quadratic discriminant** asks for b² - 4ac of a random quadratic. **Pythagoras, find a shorter side** gives the hypotenuse and one side of a triple. **Simultaneous equations** picks whole x and y first, then two equations that have them as their only solution (answer x). **Topic presets**, each built from its answer so it is exact for every seed: fractions (equivalent fraction, simplify to the simplest denominator, add with different denominators as a missing numerator); percentages (increase, decrease, find the original after a discount, simple interest; prices are 20 times the range and percentages go in steps of 5 so answers are whole numbers); graphs (gradient from two points, y-intercept from a gradient and a point, x-coordinate of a midpoint); ratio (share an amount) and speed from distance and time. Fraction and ratio topics need at least two numbers in the range, and different denominators need three. Both are plain template variables, constraints and a computed answer, so an author can edit them after applying. The number fields of the visual question types (number line, jug, shade model, count blocks, set clock, make amount, build chart, grid build) also accept a `{{variable}}` or formula such as `{{a*b}}`, so a randomised question can vary them; the live preview ignores a variable until a learner is issued numbers.

The wizard writes numerical settings and a private variable template. Each issued question uses a saved seed to generate concrete numbers and its calculated answer. Use the question preview to inspect an instance. Authoring, approval, player grading and reports use the existing numerical response format. Applying a preset replaces the current numerical answer/template definition; there is no separate generator grader or question-data migration.

## Validation

`tests/php/all-activities-integration.php` covers every mode, compatibility, private projections, round actions, saved state, pause/resume, invalid/stale submissions, practice scoring, absence of academic events, magnet coordinate bounds and every Maths preset with multiple instance seeds. `tests/browser/all-activities.spec.cjs` exercises all 16 player interfaces, mobile controls, movement, lifelines, stakes, saved composition and native Gutenberg generator authoring/save/reopen/playback.

Research references: [Wordwall template catalogue](https://wordwall.zendesk.com/hc/en-gb/sections/360005042778-Templates), [official interaction and conversion descriptions](https://wordwall.zendesk.com/hc/en-gb/articles/46885000250641--How-to-generate-content-with-AI), [official features](https://www.wordwall.net/features). These describe source mechanics; this delivery is not a claim of pixel-for-pixel or settings parity.

## Activity question types (pilot)

Spin the wheel, Gameshow quiz and Hangman are also regular question types in the **Question** block's type dropdown and the quiz type chooser. They reuse the extended response-format machinery (settings-based, frozen with each version, answer keys private) and play inside any quiz.

- **Spin the wheel** (`spin-wheel`): up to 30 cards. Each spin draws a card without repeats. Ungraded (0 marks).
- **Gameshow quiz** (`gameshow`): 2–6 choices, one correct, 5–120 seconds. The choice is graded on the server; the key is never sent to the learner. The 50/50 lifeline and speed bonus of the Question Player mode are not part of the quiz type.
- **Hangman** (`hangman`): the first accepted line is the hidden word (learners get only its shape). Learners pick letters; the quiz is correct when every letter is guessed with no more than the allowed wrong letters. Letters are checked when the answer is submitted, so there is no live hit/miss feedback in the quiz version.

The Question Player block variations are unchanged. The other 19 activities remain Question Player modes (and shortcuts under "More activities" in the type chooser) until they are ported.

Batch 2 adds Open the box, Speaking cards, Flip tiles and Flashcards (ungraded card decks: one card per line as `front | back`), and Timed True or false, Whack-a-mole and Flying fruit (timed answer-pick games that grade like Gameshow quiz) as question types. Maze chase, Airplane, Win or lose, the matching games, Speed sorting, Word magnets, Image quiz, Spell the word and Watch and memorize remain Question Player modes.
