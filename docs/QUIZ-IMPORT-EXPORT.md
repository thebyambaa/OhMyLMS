# Quiz import and export

A quiz and all of its questions can be saved to one text file and imported again, on the same site or another one. The file is an **OhMyLMS quiz file**: JSON, `*.ohmylms.json`. It is lossless on purpose: every question type, every setting and every randomized-number template survives the round trip, because question `settings` are copied untouched and the importer saves through the same writer the question editor uses.

It is an OhMyLMS format, not an industry standard. IMS QTI (the 1EdTech standard) and Moodle GIFT cannot express the interactive types, randomized templates or skill mapping, so they are not supported. The older CSV layout (choice, true/false and text questions only) still imports.

## Using it

- **Export:** Content Hub → Quizzes → the `…` menu on a quiz row → **Export**, or the **Export** button in the quiz editor's header. The browser downloads `<quiz name>.ohmylms.json`. It holds every answer key, so only people who can edit the quiz may export it. The editor button saves your latest edits first (the same way Publish does), then exports; if the save fails or the current question is incomplete, nothing is exported and the editor shows why.
- **Import:** Content Hub → Quizzes → **Import**. Choosing a file checks it at once and shows what it holds (questions, points, types, randomized questions) or every problem with its question number. Nothing is created until the file is clean and you press Import.
- **Download an example file** in the dialog gives a small valid file (a choice question, a fill-in-the-blank and a randomized question). To learn the shape of any other question type, build one in the editor and export it.

## What an import does

- Always creates a **new draft quiz**. It never updates or replaces an existing quiz, and the status in the file is ignored. If a quiz with the same name exists, the copy is named `Name (imported YYYY-MM-DD HH:MM)`.
- **All or nothing.** The whole file is checked first. If any question is bad, nothing is created and every problem is listed (`Question 7: Mark exactly one option as correct.`).
- Each question is saved through `QuestionBank\DraftWriter`: type settings are validated, a frozen version is captured, skills and bank attributes are written. An imported question is as functional as one made by hand.
- The quiz is created and every question saved in one database transaction.

### What is checked

| Check | Where |
| --- | --- |
| Valid JSON, `format`, `version`, shape of every field, http(s) media addresses | `Exchange\Document` |
| The question type exists on this site and its own settings validate | `DraftWriter` and the type's `validate_settings` |
| Choice: at least two options with text or image; exactly one correct (multiple choice: at least one); true/false has exactly two | `Exchange\QuestionCheck` |
| Statement: a correct answer text. Fill in the blank: `{answers}` in the name. Matching: every pair has an item and a definition. Reorder: at least two items | `QuestionCheck` |
| Question text is not empty, score is a number of 0 or more | `QuestionCheck` |
| **Randomized numbers:** eight real examples are built from the full question (text, options, settings) and each must be a valid question; an undefined `{{z}}`, impossible ranges, conditions nobody can satisfy, or numbers that never change refuse the file | `QuestionCheck` + `Assessment\Template::validate` |
| Extension settings belong to an extension installed here | `Extensions\Settings::prepare` |

Things that are not errors become **warnings** shown after the check or the import: a skill that does not exist here, a player design this site lacks, an image that could not be copied, fields the format does not know.

## File layout

```json
{
  "format": "ohmylms.quiz",
  "version": 1,
  "source": { "site": "https://example.com", "uploads_url": "https://example.com/wp-content/uploads", "exported": "2026-10-12T05:00:00+00:00" },
  "quiz": {
    "name": "Algebra",
    "description": "<p>…</p>",
    "settings": { "time_limit": { "value": 30, "type": "minutes" }, "passing_grade": { "enabled": true, "value": 60 }, "allow_attempts": 3, "player_template": "paper" },
    "drip_settings": { "enable": true, "type": "enrollment-from-x-days", "days": 3 },
    "extension_settings": { "my-extension": { "note": "…" } },
    "skills": [ { "uuid": "…", "slug": "fractions", "name": "Fractions" } ]
  },
  "questions": [
    {
      "name": "Question 1",
      "description": "<p>What is 2 + 2?</p>",
      "settings": {
        "type": "single-choice",
        "score": { "enabled": true, "value": 2 },
        "question_code": "Q-1A2B3C4D",
        "hint": "Count on your fingers.",
        "explanation": "<p>Two plus two is four.</p>",
        "required": true,
        "randomize": true
      },
      "options": [
        { "answer": "3", "is_correct": false },
        { "answer": "4", "is_correct": true, "image_url": "https://example.com/wp-content/uploads/four.png" }
      ],
      "image_url": "https://example.com/wp-content/uploads/question.png",
      "video_url": "",
      "skills": { "p1": { "primary": { "uuid": "…", "slug": "fractions", "name": "Fractions" }, "supporting": [] } },
      "bank": { "difficulty": "challenge", "source": "Past paper 2025", "family_id": "", "secure": true },
      "extension_settings": {}
    }
  ]
}
```

- `settings` is the question's stored settings, verbatim. Hints, explanations, the score, `partial_credit`, `case_sensitive` and every type-specific setting live **inside** `settings`; a top-level `hint` is ignored with a warning.
- `options` hold answers. Matching and reorder options also carry `matching_data: { "label", "image_url" }` (the definition text and image). `questions` is accepted as another name for `options`.
- Shorthands accepted when writing a file by hand: a top-level `type` instead of `settings.type`; a missing `score` means 1 point; `is_correct` may be `true`, `"true"`, `1`; a bare list of questions without the outer object; a skill given as just its slug.
- Fields from a REST response (`id`, `uuid`, `modified`, `thumbnail_id`, …) are ignored without a warning.
- Bank attributes: `difficulty` is `easy`, `standard` or `challenge`; defaults are left out of exports. The bank a question sits in is not exported.

## Randomized numbers

A randomized question keeps its template in `settings.template` and its `{{a}}` placeholders in the text, options and settings, exactly as stored:

```json
"settings": {
  "type": "numerical", "score": { "enabled": true, "value": 2 }, "answer": 0, "tolerance": 0.01,
  "template": {
    "variables": [ { "name": "a", "type": "int", "min": 2, "max": 9, "step": 1 },
                   { "name": "x", "type": "decimal", "min": 1, "max": 5, "places": 2 } ],
    "constraints": [ "a>2" ],
    "set": [ { "path": "answer", "expr": "a*x" } ]
  }
}
```

Nothing is evaluated at import time; the copy draws numbers the same way the original did (the same seed gives the same numbers on both). See the "Randomized template questions" section of [ASSESSMENT-MODULES.md](ASSESSMENT-MODULES.md) for the template language.

## Media

- A question's `image_url` / `video_url` and options' `image_url` become media-library attachments. If this site already has the file, that attachment is reused; otherwise it is downloaded with WordPress's safe fetcher (private addresses are refused) and added to the library. Matching-definition images are copied the same way and keep their link if the copy fails.
- Addresses **inside text and settings** (a question's HTML, an option, a hint, a `labeling` image) are copied and rewritten only when they point at the source site's uploads folder (`source.uploads_url`). Other links, such as a YouTube address, are left alone.
- A file used several times is downloaded once. Up to 200 files are copied per import.
- A question or option image that cannot be copied is left out and reported as a warning. A dry run downloads nothing. The import request's `copy_media: false` skips copying.

## Skills, bank and extensions

- Skills are matched by their `uuid` first (stable across sites), then by `slug`. A skill that exists on neither is reported and left out; import never creates skills.
- Quiz-level skills, per-part question skill mapping and bank attributes (`difficulty`, `source`, `family_id`, `secure`) are carried.
- `extension_settings` of the quiz and of each question are validated by the extension that registered them. The extension must be installed on the target site, or the file is refused. Because extension settings are part of a question's frozen version, the question is versioned again after they are saved.

## REST API

`POST /ohmylms/v1/quiz/import/`

| Parameter | Meaning |
| --- | --- |
| `content` | The file text (`csv` is the older name for the same thing) |
| `name` | Quiz name; empty uses the name in the file |
| `format` | `auto` (default: a leading `{` or `[` is an OhMyLMS file, anything else CSV), `ohmylms` or `csv` |
| `dry_run` | `true` checks the file and reports without creating anything (HTTP 200) |
| `copy_media` | `false` skips copying media (default `true`) |

Success returns 201 (200 for a dry run) with `id` (not for a dry run), `name`, `renamed`, `format`, `summary` (`questions`, `points`, `types`, `randomized`, `media`), `warnings`, and `media_copied`. A refused file is HTTP 400, code `ohmylms_quiz_import_invalid`, with `data.errors`: a list of `{ question, message }` (`question` 0 is the file as a whole). Anyone who can author (`edit_posts`) may import.

`GET /ohmylms/v1/quiz/{id}/export` returns `filename`, `mime`, `content`, `summary` and `warnings`. A warning means a question is unfinished (for example a choice question with no correct answer); the file still exports, but it will be refused on import until the question is fixed. Only people who can edit the quiz may export it.

PHP: `OhMyLMS\Quiz\Exchange\Importer::run( $text, $args )` and `Exporter::export( $quiz_id )`. Action `ohmylms_quiz_imported( $quiz_id, $document )` fires after a successful import.

## Limits and not included

- At most 10 MB, 1000 questions per file, 200 options per question. A file from a newer format `version` is refused.
- One quiz per file. Not exported: the course or chapter the quiz sits in, learner attempts, reports, and the shared bank a question belongs to.
- Import never overwrites. To replace a quiz, import the file, check the copy, then delete or archive the old one.

## Tests

```
php tests/php/quiz-document-unit.php                     # the file format, no database
php tests/php/quiz-exchange-integration.php              # disposable test site: every question type, templates, media, skills, grading, refusals, permissions
node --test tests/js/quiz-exchange.test.mjs              # the dialog helpers and API calls
```

The integration test builds a quiz with every question type, imports its export, and requires the copy to be stored identically, grade identically and draw the same randomized numbers for the same seed.
