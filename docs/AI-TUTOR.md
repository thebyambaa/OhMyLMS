# AI tutor and fast-feedback practice

Two features that work together, and either works without the other.

## Fast-feedback lessons

A practice session can run in the `lesson` style (the `standard` style is unchanged and stays the default):

- **Staged:** questions move from *recognise* (choice, true/false, matching, dropdown, sorting) to *build* (tiles, number line, shading, clock, money, grid, fill-in) to *solve* (numerical, expression, multi-blank, structured). The first, middle and last third of the lesson each use their stage, falling back to the nearest stage that has questions. A question that has not been asked yet is always preferred to repeating a template.
- **Instant feedback:** a green or red sheet after each answer, the correct option highlighted, the worked solution (the question's `explanation`) on a miss, and **Continue** with the keyboard focus on it. A sound is available and off by default.
- **Mistake replay:** every miss comes back once after the planned questions, up to half the lesson length; a replay that is missed again is not repeated. A template question is replayed with new numbers; a plain question is asked again. Replays never move the difficulty band.
- **Summary:** accuracy on the planned questions only ("3 of 4 right first time"), and the missed questions with whether they were put right on the replay. The hook `ohmylms_practice_lesson_completed( $uuid, $result, $student_id, $term_id )` lets gamification award XP or streaks.
- **Evidence:** unchanged rules. A replay of a plain question is not a first try; a replay of a template is (new numbers).

Choose it for all practice under **Settings → AI tools → Practice style**, for one page with `[ohmylms_practice skill="12" style="lesson"]`, or for one link with `?ohmylms_practice=12&style=lesson&items=8`. `POST /practice/sessions` takes `style`.

## Independent AI tools and model pricing

Settings now labels this tab **AI tools**. **Enable AI tutor** and **Enable PDF exam
converter** are independent switches, sharing the provider, model and encrypted API key.
Tutor limits apply to tutoring; PDF jobs retain separate request and retry limits. Turning
conversion off blocks new requests, including queued stages, and keeps papers/reviews/exams.
Re-enable conversion and explicitly resume an interrupted extraction.

Compatibility: `enabled` and no-argument `Settings::configured()` still control the tutor.
`exam_converter_enabled` defaults to false, including on older sites. Older settings clients
that omit it preserve its saved value. The `ai-tutor-settings` URL remains valid.

After saving your provider and API key, model identifiers load automatically. Select one
and save again, or enter an identifier manually. **Refresh available models** repeats discovery.
Administrator-only `POST /ohmylms/v1/ai/models` uses saved keys in headers, refuses redirects,
times out after 20 seconds, limits responses to 2 MiB and entries to 1,000, and discloses
partial catalogues. Retired Claude models and Gemini models without generateContent are
excluded. A listed model still needs tool compatibility and account inference permissions;
listing does not guarantee PDF support or successful generation.

The dated public price reference shows standard USD per million input/output tokens for
exact verified IDs. Unknown snapshots show **price not verified**. Context tiers appear as
ranges. Gemini 3.8 Flash promotional rates expire after December 31, 2026. Prices are a
checked-in reference, not live billing data; discovery does not return pricing or account
balance. Free tiers, caching, taxes and negotiated terms may change the invoice. PDF images,
repeated batches, generated answers and retries prevent a fixed per-paper price.

Sources checked October 18, 2026:
[OpenAI pricing](https://developers.openai.com/api/docs/pricing),
[Claude pricing](https://platform.claude.com/docs/en/about-claude/pricing),
[Gemini pricing](https://ai.google.dev/gemini-api/docs/pricing).
Wire contracts: [OpenAI models](https://developers.openai.com/api/reference/resources/models/methods/list),
[Claude models](https://platform.claude.com/docs/en/api/models/list),
[Gemini models](https://ai.google.dev/api/models).

Verification: 102 AI unit checks cover independent switches, retained credentials, model
request headers, schema filtering, permissions and safe errors. The PDF integration rehearsal
passes 42 checks, including tutor-only rejection and disabling queued conversion before it
contacts a provider. Provider discovery transport is mocked; actual entitlement is checked
when an administrator saves credentials or refreshes the list.

## The AI tutor

Optional. It never marks an answer, changes a score or affects mastery: grading, evidence and mastery stay deterministic. With the tutor off, unconfigured, over its limit or failing, practice works exactly as before.

- **Learner features:** *Ask the tutor for a hint* before answering, and *Explain my answer* after a wrong one. A tutor hint marks the question as assisted, like the authored hint. Replies are plain text, shown in their own block, with math typeset by the site's KaTeX.
- **Providers:** Anthropic (Messages API), OpenAI (Responses API) and Gemini (generateContent). Each adapter only builds a request and reads a response (`includes/AI/Providers/`), so another provider can be added through the `ohmylms_ai_providers` filter. Model names are entered, not hard coded, because providers retire them.
- **Connection:** Settings → AI tools (administrators; the old OhMyLMS → AI tutor address forwards there). The API key is posted once, sealed with libsodium under a key derived from the site's salts, and only its last four characters are ever shown again. In production define `OHMYLMS_AI_API_KEY` in `wp-config.php` (or the environment variable); constants `OHMYLMS_AI_PROVIDER` and `OHMYLMS_AI_MODEL` also work and win over stored values. Changing the site's AUTH salts invalidates a stored key.
- **What is sent:** the question as the learner saw it, their answer and, for an explanation, the reference answer and worked solution. A hint is written **without** the answer or the solution; a reply that still contains the expected answer is discarded and refused. Never names, e-mails, user or site identifiers. The question and the learner's words are fenced as data in the prompt, with angle brackets neutralised, so they cannot add instructions.
- **Cost and abuse controls:** one reply per kind per question (kept and reused), a shared cache for identical questions and answers (a week), a daily limit per learner, three attempts per item and kind when the provider fails or a hint leaks (the daily credit is refunded on failure), guests off by default and capped at ten a day, a reply length cap, and a timeout. Totals are shown on the settings page.
- **Language:** the tutor replies in the language the question is written in.
- **Extending:** `ohmylms_ai_enabled` (per session), `ohmylms_ai_context` (change what is sent), `ohmylms_ai_providers`.

Tests: `php tests/ai-unit.php` (wire formats, key sealing, prompts, the leak check, stages) and the real-database phase `php tests/php/assessment-integration.php lesson` with the provider's HTTP mocked.
