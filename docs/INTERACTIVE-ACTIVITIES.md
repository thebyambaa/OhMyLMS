# Interactive activities

The **OhMyLMS Interactive Activity** block (`ohmylms/interactive-activity`) holds one try-and-learn activity. It is allowed in the lesson editor. Learners experiment; nothing they do is stored, graded or counted toward skills.

| Type | What the learner does |
| --- | --- |
| `function-graph` | Types equations with the math editor and watches them graph. Variables become sliders. |
| `number-line` | Drags up to four points along a number line (arrow keys also work). |
| `fractions` | Shades the parts of a bar or circle; sees the fraction, its simplest form, decimal and percent. |
| `clock` | Sets an analogue clock by dragging the face or with sliders; sees the digital time. |
| `converter` | Types an amount and converts it between units of length, mass, time or volume. |
| `chart` | Edits the numbers of a small table and watches a bar, line or pie chart and its total, mean, median, mode and range. |
| `geometry` | Drags the corners of a triangle, four-sided shape, circle or angle and sees lengths, angles, perimeter and area. |

The author picks the type in the block sidebar. Choosing another type starts that type's default settings.

## Configuration contract

Each type stores a small versioned `config` object in the block attributes. Only numbers, switches and a few allowed words are kept; every value is clamped, and text is stripped of tags.

- JavaScript: `assets/src/blocks/interactive-activity/activities.mjs` (`normalizeActivity`). The function graph keeps its own `config.mjs`.
- PHP: `InteractiveActivityBlock::sanitize_activity()` applies the same limits on every render, so a saved block can never exceed them.
- `tests/fixtures/interactive-activity-contract.json` lists input and expected output for both sides. `tests/js/interactive-activities.test.mjs` and `tests/php/interactive-activity-contract-unit.php` each check it, and a JavaScript test checks that the PHP type and unit lists match the script.

## Adding a type

1. Add its configuration and any pure helpers to `activities.mjs`, with matching limits in `sanitize_activity()` and cases in the fixture.
2. Write `assets/src/blocks/interactive-activity/<type>.mjs` with `createActivity()` from `kit.mjs`. It returns `{ update, destroy }`. Draw with the DOM or SVG; use `ctx.labels` for text.
3. Register it in `registry.mjs`, in `InteractiveActivityBlock::ACTIVITIES` and `labels()`, and in the picker and panel in `index.jsx` and `settings.jsx`.
4. Rebuild with `npm run build:sdk`, then `node tools/sync-admin-assets.mjs`.

## Limits

Geometry uses JSXGraph, so its points move with mouse or touch only. The other types work from the keyboard. A four-sided shape whose sides cross shows no area.
