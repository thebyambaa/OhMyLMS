# Exam papers, structured questions and figures: design notes

Status: **design notes and findings; the structured-part import and exam features proposed here have since been built (see the update below), while interactive figures remain a design only.** Written October 15, 2026
after studying a real Cambridge IGCSE Mathematics 0580 Paper 4 (Extended, calculator) question paper and its
published mark scheme, supplied by the site owner. The paper's text is deliberately not copied into this
repository; only its structure is described.

Update October 18, 2026: structured per-part imports are now implemented and rehearsed in
WordPress. Private PDF extraction/review, the Exam entity and a shell extending the quiz player
are implemented with synthetic integration and browser coverage. Real-paper extraction quality,
interactive figure reconstruction and the supplied mockup design remain pending. Follow [EXAM-IMPORT-IMPLEMENTATION.md](EXAM-IMPORT-IMPLEMENTATION.md)
for the current sequence; the original findings below describe the earlier design state.

## 1. What a real paper looks like

| Feature | In the paper | What the LMS must carry |
|---|---|---|
| Paper header | Board, qualification, subject code (0580), paper and variant (4, 43), session (Oct/Nov 2025), 2 hours, 100 marks, calculator allowed, answer on the paper, geometrical instruments needed | Exam fields: board, qualification, code, paper, variant, session, duration, total marks, calculator, instructions text |
| Reference sheet | A formula list page (areas, volumes, quadratic formula, sine and cosine rule) | A reference document the learner can open during the exam |
| Questions | 26 numbered questions, 45 or so parts, each part with a mark in brackets, from [1] to [5] | Structured question per number; parts with their own marks |
| Answer forms | Numbers with units, algebraic expressions, descriptions ("describe fully the single transformation"), choose or ring one, order a list, complete a table, mark a point on a diagram, two-part answers (lower and upper bound), answers that must show working | Numeric, expression, text and hand-marked parts; some parts are not answerable on screen |
| Figures | Regular polygon with a labelled diagonal, coordinate grid with three triangles, stem-and-leaf table, scale map with north arrow, similar triangles, a quadrilateral with lengths and angles, a cuboid with a diagonal, a parallelogram with vectors | Rebuilt figures (section 4), not screenshots |
| Mark scheme | Table: question part, answer, marks, **partial marks** | Per part: expected answer, marks, partial-mark rules |
| Mark types | M method, A accuracy, B independent; FT follow through; oe, soi, cao, isw, nfww, dep, SC, rot, awrt | Marker's notes shown to a teacher or a self-marking learner |
| Marking principles | Whole marks only, positive marking, marks once earned are kept, alternative methods accepted | Rules for hand-marked parts |

## 2. What the plugin already has (checked in the code)

- A **`structured` multi-part question type** (`includes/Assessment/Structured.php`): parts with `id`, `label`, `prompt`, `marks`, `kind`
  (`numerical`, `text` or `written`), answer or `answers`, `tolerance`, `unit`, `accepted` (text), and a teacher-only `rubric`. Part IDs are stable
  so skills and evidence are attributed **per part**. Written parts are marked by a teacher. Structured questions with written parts are excluded
  from practice and so are exam-only by nature.
- An **exam preset on quizzes** (`AssessmentSettings`): kind `exam`, timed deadline with grace, sections with marks and page breaks, availability window,
  results released after close, accommodations, exam-only questions (bank `secure` flag), versioned revisions, autosave.
- Tolerance-based **numerical** answers, a MathLive/CAS answer path, matching, reorder, dropdown and fill-in-the-blank types, and an extension registry.
- Lazy loading of heavy front-end libraries (`assets/interactivity/math-loader.js` loads MathLive on demand).

## 3. Gaps the real paper exposes

1. **The bulk import creates single-part questions only.** A real paper needs one `structured` question per number with parts, part marks, part
   answers, mark-scheme partial marks (as the part `rubric`) and **part-level skills**. Next change: a `parts` field on an import row, validated by
   `Structured::validate_settings`, with per-part `skills` falling back to the row's skills.
2. **Hand-marked and on-paper answers.** Method marks, descriptions and drawn answers cannot be graded by the machine. The Exam needs a *paper mode*:
   the learner works on paper or on screen, then self-marks against the mark scheme or a teacher marks. The `written` part kind plus `rubric` is the base;
   a marking screen and a learner self-mark flow are not built.
3. **Exam entity.** Header fields, attached documents (question paper PDF, mark scheme PDF, reference sheet), duration, total marks and calculator rule.
   Built on the quiz engine (Option A in `CONTINUE-LEARNER-HUB.md`). Documents must be visible only when allowed (the mark scheme only after results are released).
4. **Access.** Quizzes and exams are gated by enrollment in their course (`Quiz\\Submission::access`, `CommonHook::restrict_access`, the single-lesson
   template) and attempts store `course_id`. A truly course-less exam would be locked for learners and absent from course reports. The safe route for
   "stand-alone" exams is a hidden, automatically managed single-exam wrapper course, with auto-enrollment when the learner starts. This is a decision for the owner.
5. **Figures** (section 4).
6. **Skill search for classification** was name-only. **Fixed**: see section 5.

## 4. Figures: recreate diagrams as HTML (2D) and Three.js (3D)

Goal: an AI turns a diagram image into a figure that is crisp, accessible, editable and, for 3D, interactive.

**Do not store AI-written HTML or JavaScript in questions.** It is an XSS and breakage risk and cannot be validated. Store a **declarative figure spec** (JSON)
that trusted code renders. The AI produces and edits the spec; a person checks it side by side with the original image before approving the question.

Spec v1 (proposed, from the diagrams in this paper):

- Common: `version`, `kind` (`2d` or `3d`), `alt` (text description, required), `not_to_scale` (shows the NOT TO SCALE note), size limits, numbers only (no formulas, no scripts).
- 2D (rendered as inline SVG): `grid`/`axes` (range, labels), `points` (id, x, y, label, label position), `segments` and `lines` (solid, dashed, arrow), `polygons` (fill, label),
  `regular_polygon` (n, centre, radius, vertex labels), `circles` and `arcs`, `angles` (vertex, two rays, arc, label or value), `right_angle` marks, equal-length and equal-angle ticks,
  parallel arrows, `labels` and length labels along a segment, `vectors` (arrow with bold label), `table` (stem-and-leaf and frequency tables as accessible HTML tables), north arrow and scale bar.
- 3D (rendered with Three.js, lazy loaded): solids `cuboid`, `prism`, `pyramid`, `cylinder`, `cone`, `sphere` or a custom `polyhedron` (vertices and edges); vertex labels; hidden edges dashed;
  measure labels on edges; highlighted lines (a space diagonal) and highlighted planes (the base); angle markers between a line and a plane; camera presets; orbit, zoom and touch
  controls, a reset button, keyboard rotation, `prefers-reduced-motion` respected.
- Every 3D figure also renders the **static SVG projection** from the same spec, for print, PDF, screen readers and browsers without WebGL.
- Interactive answering on a figure (marking a point, drawing a line, placing a transformation image) is a later layer built on the same 2D renderer; the paper's
  scale-and-bearing and construction questions need it.

Three.js is not in the project yet. Add it as a pinned vendored ES module loaded only when a question has a 3D figure, following `math-loader.js`; do not load it on other pages.

Safety rules for the spec validator: bounded counts and coordinates, plain-text labels only (escaped on output), no URLs, no HTML, no event handlers; the server rejects anything outside the schema.

AI workflow: the AI reads the page image, writes the spec, a preview shows the original and the rebuilt figure together, a person confirms. An import row gets a `figure` field.

## 5. Skill search for AI classification (built)

`QuestionBank\\SkillCatalogue::search` (behind the MCP tool `search_skill_catalogue`) now ranks by relevance instead of requiring the exact phrase:
any word may match the skill's name, code, full objective or description; more matching words and name or code matches rank higher; long words are cut three
letters so "differentiation" finds "differentiate"; common filler words are ignored; an exact code or UUID comes first.

Checked against all 26 questions of the real paper with natural descriptions of each question. Every question found a sensible skill in the top results from the
site's Cambridge-aligned catalogue (for example nth-term rules, stem-and-leaf, substitution, scale drawing, interior and exterior angles, index laws, simultaneous equations,
standard form, complement probability, expanding brackets, rearranging formulas, sine and cosine rule, inverse and composite functions, differentiation, similar shapes,
grouped mean, angle between a line and a plane, bounds, vector section ratios). Weaker spots, where the first result was a near miss and a person or the AI should
read the objective: describing transformations (the catalogue has translation clearly, enlargement less so), ordering fractions and percentages by size, exponential equations in
`y = c^x` form, and percentage-rate-of-work problems. The catalogue also contains a stray root skill named "asdf" with no code, worth deleting.

## 6. Suggested order

1. `parts` in the import (structured questions with part marks, partial-mark rubric, part skills). Test with several questions from this paper, as a dry run first.
2. Figure spec v1 and 2D SVG renderer with the validator and a side-by-side admin preview; then the 3D Three.js view with the SVG fallback.
3. Exam entity (header, documents, paper mode, wrapper course for stand-alone) on the quiz engine, then learner entry and the "Exam" label in the course overview.
4. Teacher marking and learner self-marking for hand-marked parts using the mark scheme notes.
5. Batch classification script using the MCP tools.
