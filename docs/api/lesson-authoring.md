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
`external_url`, boolean `preview_enable`/`enable_comments`, and `resources`.
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

App tests cover transport/identity validation, source mode, resource edits,
conflict recovery, explicit publication, duplicate-save prevention and cancellation.
Browser verification exercises the installed WordPress visual editor, since DOM
unit tests intentionally mock the canvas rather than reimplementing WordPress.

Advanced media upload, inline question insertion/editing, extension lesson panels,
lesson objective controls, drip/prerequisite editing and a full learner rendering
preview remain native/future work. The shared unsaved guard covers links and page
unload, not browser hash-history navigation. The old admin menu remains in place.
