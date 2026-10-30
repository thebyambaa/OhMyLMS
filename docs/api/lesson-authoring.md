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
fields `drip`, `prerequisites`, `cover_image_id`, `video_id` and `audio_id` may be sent too;
an omitted optional field keeps its stored value.

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

Inline question insertion/editing, video player settings (`_video_settings`), lesson
objective controls and a full learner rendering preview remain native/future work. The shared unsaved guard covers links and page
unload, not browser hash-history navigation. The old admin menu remains in place.
