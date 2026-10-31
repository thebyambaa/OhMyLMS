# Typed lesson authoring

The administrator preview now offers `#/content-hub/lessons` and
`#/content-hub/lessons/:id`. Course-unit lesson placements link directly to the
content editor. Lessons remain shared WordPress posts; course placement uses the
existing outline draft save. This batch does not migrate the learner frontend.

## Ownership and block content

`assets/src/app/features/lessons` owns the library, typed response validators,
editor, resource forms and publication confirmation. Shared UUID creation now
lives in `app/lib/uuid.ts`. Block parsing/serialization and the visual canvas use
the installed WordPress `blocks`, `block-library` and `block-editor` script
externals. The small declarations describe only the public APIs this feature
uses; no second copy of Gutenberg is bundled or installed. See WordPress's
[standalone editor documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/).

The canvas provides core paragraph, heading, list, quote, table, code, HTML,
image/file, audio/video and separator blocks, plus block settings and a source
view. Existing unsupported blocks use WordPress's missing-block representation
and retain their original markup. Merely opening a lesson does not replace its
raw saved content with a serialization. Interactive assessment controls and
extension-specific block authoring remain in the full native editor, linked beside the
typed editor. Image/media upload integration remains native; URL insertion and
download resource links are available here.

The app entry now depends on WordPress's editor packages, and the preview page
loads their registered styles and rich-text formats. Lesson layout CSS is scoped
under `.ohmylms-app` and loaded through a content-hashed feature CSS chunk. WordPress package
scripts currently load with the preview entry, even on other preview routes;
per-route script isolation remains future asset-loading work. No performance
improvement is claimed.

## REST contract

All routes require `manage_options` plus the registered lesson type's creation
capability or per-post `edit_post`. Publishing additionally requires that type's
`publish_posts`. The existing cookie REST nonce middleware authenticates browser
requests. These checks run on the server independently of navigation visibility.

| Route | Contract |
| --- | --- |
| `GET /ohmylms/v1/authoring/lessons` | `page` 1–100000, search up to 100 characters; 20 summaries per page, ordered by title/ID; returns `{items:[{id,title,status}],pages,total}` |
| `POST /ohmylms/v1/authoring/lessons` | Exactly `{title,request_id}`; nonblank title up to 190 characters and UUID; creates an owned reading draft. Reusing the caller/UUID recovers the matching draft or returns 409 rather than duplicating it |
| `GET /ohmylms/v1/authoring/lessons/:id` | Raw editable lesson plus revision and native editor URL; private/no-store |
| `PUT /ohmylms/v1/authoring/lessons/:id` | Complete editable fields below and original revision; never accepts status, author, course, placement or arbitrary metadata |
| `POST /ohmylms/v1/authoring/lessons/:id/publish` | Exactly `{revision,confirmed:true}`; publishes the reviewed saved lesson, without changing a course draft or program pointer |

Editable fields are `title`, `content`, `type` (`text`, `video`, `audio`),
`external_url`, boolean `preview_enable`/`enable_comments`, and `resources`. The optional
fields `drip`, `prerequisites`, `cover_image_id`, `video_id`, `audio_id` and `video` (see
[Video settings](#video-settings-and-the-completion-rule)) may be sent too;
an omitted optional field keeps its stored value. Reads also return the read-only
`inline_questions` (see [Inline questions](#inline-questions)).

| Field | Read | Write and validation | Storage |
| --- | --- | --- | --- |
| `drip` | `{enable, type, days, date, time}` | Exactly those keys; `type` is `enrollment-from-x-days`, `specific-date`, `cohort-start` or `cohort-from-x-days`; `days` 0–3650; `date` empty or a real `YYYY-MM-DD`; `time` empty or `HH:MM`; a specific-date release needs a date | `_drip_settings` (`time` as `HH:MM:SS`, unrelated keys kept), read by the learner drip gate |
| `prerequisites` | `{enable, items:[{id,title}]}` | `{enable, ids}`, up to 50 distinct lessons, quizzes or assignments other than the lesson itself; newly added content must be editable by the author | `_prerequisites` as `{enable, data:[{value,label}]}` (the learner gate's format) |
| `cover_image_id`, `video_id`, `audio_id` | Attachment ID or 0, plus read-only `media_urls` | 0 or an image/video/audio attachment the author can edit; an unchanged stored ID stays allowed | `_cover_image_id`, `_video_id`, `_audio_id` |

Reads also return `custom_type`: true for a registered extension lesson type (see below).
Each resource has `name`, `url`, and `size`. Content is limited to 1 MB; there are
at most 50 resources, with names up to 200 bytes, size labels up to 50 bytes and
absolute HTTP/HTTPS URLs up to 2048 bytes. Download link removal does not delete a
file. Hidden fields on retained resource URLs and the download settings object
are merged back server-side; unrelated drip, prerequisite, uploaded-media and
extension settings are preserved.

The domain boundary validates every submitted field before invoking the secured
native WordPress REST writer. That writer owns post sanitization, slashing,
revisions and existing lesson lifecycle hooks. Explicit post/settings errors
roll back the whole typed save. Reads, typed writes and native post updates share
a lesson advisory lock. A fingerprint of saved title/content/status/author and
metadata detects changes since the typed editor read. A 409 preserves local
edits; reload requires an explicit discard decision. Legacy LMS mutation routes,
core autosaves and direct post writers retain their existing protocol and do not
participate in the new lock; full
legacy-writer retirement remains part of cutover.

## Media, drip, prerequisites and extension lesson types

The editor's Lesson media section opens the WordPress media library (`wp.media`, which
the app screen enqueues) to upload or choose a cover image and, for video or audio lessons,
the uploaded file; choosing an uploaded file clears the external URL and entering an
external URL clears the uploaded file, as in the native editor. Drip and prerequisite
sections edit the fields above; the prerequisite picker searches the lesson library, and
existing quiz or assignment prerequisites are shown and kept.

Lessons whose type is a registered extension lesson type (`ohmylms_register_lesson_type`)
are editable here. The editor renders the `lesson-editor` entry with the same id
(`registerLessonEditor`, see [EXTENSIONS.md](../EXTENSIONS.md)) inside an
`ExtensionBoundary`, with the props `{lesson, value: {name, description}, onChange(patch)}`;
`name` and `description` map to the lesson title and content and are saved with the other
fields. A render error replaces only the extension with an alert. Without a registered entry
the editor falls back to title and source fields. The server keeps the extension type: a
typed save cannot change it, and built-in lessons cannot switch to an extension type.
Unregistered custom types remain read-only here.

Saving an already published shared lesson changes it immediately everywhere it
is used. The editor states this before editing. This batch introduces no separate
immutable lesson-content version or staged edits to a published resource.

## Verification and remaining scope

`OHMYLMS_LESSON_TEST=1` runs `tests/php/lesson-authoring-rest-integration.php` against
the Local database with `HTTP_HOST=800.local`. Only its tagged users, posts and
course units are created/deleted; it never resets the database. It checks bounded
summaries, idempotent drafts, raw block/math/reference round-trips, resource and
advanced-setting preservation, invalid input, stale/native writes, rollback after
a post write, publication permissions/confirmation/replay and draft course attachment.
It also covers drip, prerequisite and media save/reopen and storage format, invalid
values (unchanged after rejection), a stale 409, rollback of drip settings when a
failure follows the settings write, and extension lesson types (flag, name/description
save, type kept, no type switching).

App tests cover transport/identity validation, source mode, resource edits,
conflict recovery, explicit publication, duplicate-save prevention, cancellation, drip
and prerequisite payloads, media library selection, and the extension lesson editor
(props, saved changes, error isolation, fallback).
Browser verification exercises the installed WordPress visual editor, since DOM
unit tests intentionally mock the canvas rather than reimplementing WordPress.

A full learner rendering preview remains future work. The shared unsaved guard covers links and page
unload, not browser hash-history navigation.

## Video settings and the completion rule

The optional `video` field reads and writes the player settings of `_video_settings`:
`{provider, duration, completion, watch_percent, poster_id, autoplay, loop, controls, aspect_ratio}`
(read adds `poster_url`, display only). `provider` is `self-hosted`, `youtube`, `vimeo` or `bunny`
(stored `platform`); `duration` 0–86400 seconds; `completion` `none` or `watch_percent` (stored
`completion_rule`) with `watch_percent` 1–100; `poster_id` 0 or an image attachment the author can edit
(an unchanged poster stays allowed); `aspect_ratio` `n/n`. A write sends every key; unrelated player keys
(`remove_branding`, widths…) are kept. The source stays the uploaded `video_id` or the `external_url`.
Native editor saves of the same keys are bounded the same way (`NativeLessonEditor::clean_video_settings`).

`Learning\VideoCompletion` applies the rule to a video lesson (an uploaded video, or an external URL on a
video lesson): the progress tracker completes the video at the lesson's percentage instead of the site
setting, measures it against the declared duration when that is longer than what the player reports, and
bounds each report by elapsed time (a first report counts at most two minutes, later ones at most twice
the time since the previous report plus 30 s). "Mark as complete" is rendered disabled with "Watch at
least N% of the video…", and `Ajax::lesson_completed` refuses with `ohmylms_lesson_video_not_watched`
until the tracked watch reaches the percentage (the tracker then completes the lesson itself). The learner
page shows the length, uses the poster for the site's player and in front of uploaded videos (falling back
to the cover image), and passes `data-ohmylms-watch-percent` to the tracker. Watching is measured in the
browser; the server bounds it but cannot prove viewing. With the site's custom player switched off,
YouTube and Vimeo embeds show the provider's own thumbnail instead of the poster.

## Inline questions

Reads include `inline_questions` for the saved content, in reading order:
`[{position, kind: block|shortcode, question_id, uuid, title, status}]`, `status` being `approved`,
`not_approved` (learners do not see it yet), `exam_only` or `missing`. The editor's Inline questions
section lists the checks of the draft content, links each to the question bank editor, inserts approved
non-exam questions as `ohmylms/shared-multiple-choice` blocks (searching `GET /questions?status=approved`),
and moves or removes them; moving swaps two standalone blocks and leaves everything around them unchanged,
while text shortcodes are listed and can be removed (open the full editor to turn them into blocks). The
changes are part of the lesson content and save with it. The native editor's "Inline question checks"
panel lists the placed checks too, selects them in the canvas, swaps neighbours and links each question.

## Objectives

| Route | Contract |
| --- | --- |
| `GET /ohmylms/v1/authoring/lessons/:id/objectives` | `{id, revision, skills: [{id, name, code, managed}], mappings: [{syllabus_id, term_id, relation, name, code, objective, topic, syllabus_name, missing}], course_targets: [{…objective, syllabus_name, course_id, course_title}]}` |
| `PUT /ohmylms/v1/authoring/lessons/:id/objectives` | Exactly `{revision, skill_ids, mappings: [{syllabus_id, term_id, relation}]}`; `relation` `teaches`, `practices` or `assesses`; up to 200 skills and mappings; 409 on a stale revision |
| `GET /ohmylms/v1/authoring/lessons/:id/objective-options` | `type=syllabuses` (teaching syllabuses, roadmaps excluded), `type=objectives&syllabus_id=&search=` or `type=skills&search=`; 20 per page, `{items, total, pages}` |

Skills are the lesson's `ohmylms_skill` terms (what coverage and practice read); mappings are
`Curriculum\LessonObjectives` (`_ohmylms_lesson_objectives`, also used by the MCP authoring tools). A mapped
objective links its skill as a *managed* skill that leaves with the mapping; explicit skills stay until
unlinked. Both are replaced together under the lesson lock in one transaction (a rejected part rolls back
the other). `course_targets` are the course objectives (`_ohmylms_course_objectives`) of the courses that
place the lesson, offered as quick choices. Nothing here awards mastery or changes a course.

Verification: `OHMYLMS_LESSON_SETTINGS_TEST=1` runs `tests/php/lesson-settings-integration.php` (video
settings save/reopen, storage and validation without writes, bounded native saves, inline question
statuses, the watched-percentage rule through the tracker and the completion handler, drip through the
shared schedule, objective search, save, managed skills, conflicts and invalid input; it maps objectives of
an existing teaching syllabus read-only). App tests cover the video settings payload, inline question
insert/move/remove and the objectives editor.
