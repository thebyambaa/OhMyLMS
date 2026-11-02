# Live sessions REST API (`ohmylms/v1/sessions`)

Contract version 1. Server: `includes/Rest/V1/SessionsController.php` (registered in
`includes/Rest/Api.php`). Client: `assets/src/app/features/sessions/`. Test:
`tests/php/sessions-rest-integration.php` (`OHMYLMS_SESSIONS_TEST=1`).

Meetings are still created by the provider integrations: the controller fires the existing
`ohmylms_{zoom|googlemeet}_session_{created|updated|deleted}` actions, which call the Zoom
`MeetingService` (`includes/Integrations/Zoom`) and the Google Calendar/Meet service
(`includes/Integrations/GoogleMeet`). Credentials and OAuth stay on their existing endpoints
(`/zoom/settings/credentials`, `/googlemeet/settings/*`, the Integrations screen).

## Audit before (legacy `ohmylms/v1/session`, `SessionController`)

| Severity | Finding |
| --- | --- |
| High | **Host start link exposed.** `GET /session` and `/session/{id}` returned `start_url` (a Zoom URL with a `zak` host token) and the meeting passcode to every `edit_posts` user who could *read* the post, so any contributor could start other instructors' meetings as host. Fixed in the legacy controller too: the list keeps only sessions the caller can edit, and `start_url`/`password` are blanked for callers without `edit_post`. |
| High | **No argument schema.** Create/update accepted any `date`, `timezone`, `duration`, `status`, `platform`, `course_id`; `platform` was interpolated into action names and error strings; `post_status` was taken from the body (contributors could publish). |
| High | **Orphans on provider failure.** Create inserted the post, then answered 500 when the provider failed, leaving a session without a meeting; update overwrote the post before the provider call and kept the new values on failure. The Google hook's "not created" state was never reset, so a failed Meet update looked successful. |
| Medium | **Wrong pagination.** `X-WP-Total` was the size of the filtered *page*; sessions without a chapter were silently skipped after paging. `prepare_items_query` let `meta_query`, `tax_query`, `post_parent` etc. through a WooCommerce-style allow-list. |
| Medium | **Unescaped error text** (`__( "{$platform} session …" )`, a non-literal translation) and English-only codes. |
| Low | Delete fired provider deletion with a `platform` taken from the request, not the stored provider. No `OPTIONS` schema. |
| OK | Zoom credentials are stored per user and never returned (`IntegrationSettings`); Google tokens live in user meta and are not exposed by the session routes. |

The legacy routes `GET|POST /session` and `GET|PUT|POST|DELETE /session/{id}` (`SessionController`, registered
by `Hooks\ApiHook`) were **removed on 31 October 2026**: no screen, block, template, add-on or extension
contract called them any more (the course builder links sessions through the course structure routes), so they
now answer 404 `rest_no_route`. Use `/sessions` and `/sessions/{id}`. Their hooks keep firing from
`SessionsController`: `ohmylms_{provider}_session_{created|updated|deleted}` (documented there) and
`ohmylms_rest_delete_session`; `ohmylms_rest_insert_session` and `ohmylms_rest_ohmylms_session_query` belonged
to the removed routes and had no listeners.

## Routes

Every route needs `edit_posts`; anonymous callers get 401, others 403 (`ohmylms_session_cannot_*`).

| Method | Path | Rule | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/sessions` | Lists sessions; users without `edit_others_posts` see only their own (SQL `author`) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/sessions` | `publish` also needs `publish_posts` | 201 + `Location` | 400, 401, 403, 422, 500, 502 |
| GET | `/sessions/{id}` | `edit_post` | 200 | 401, 403, 404 |
| PUT/PATCH | `/sessions/{id}` | `edit_post` (+ `publish_posts` to publish) | 200 | 400, 401, 403, 404, 409, 422, 500, 502 |
| DELETE | `/sessions/{id}` | `delete_post`; permanent; the provider deletes the meeting | 200 `{ deleted, previous }` | 401, 403, 404, 500 |
| GET | `/sessions/{id}/attendees` | `edit_post` | 200 + totals | 400, 401, 403, 404 |
| GET | `/sessions/providers` | `edit_posts` | 200 `[{ id, label, available, configured }]` | 401, 403 |
| GET | `/sessions/courses?search=` | `edit_posts`; at most 20 courses the user can edit | 200 `[{ id, title }]` | 400, 401, 403 |

Collection arguments: `page`, `per_page` (1-50, default 20), `search` (max 100), `provider`
(`all|zoom|googlemeet`), `status` (`any|draft|publish`). Past the last page:
`400 ohmylms_session_invalid_page`.

Write body (create requires the first five): `title` (1-200), `provider` (`zoom|googlemeet`, fixed
after creation: `409 ohmylms_session_provider_locked`), `start` (`YYYY-MM-DDTHH:MM`, local to
`timezone`), `timezone` (IANA name), `duration` (5-1440 minutes), `description` (≤ 5000, `wp_kses_post`),
`status` (`draft|publish`), `course_id` (integer or null; must be a course the user can edit),
`password` (Zoom only, `^[A-Za-z0-9@_*-]{0,10}$`), `options` (`host_video`, `participant_video`,
`join_before_host`, `mute_upon_entry`, `auto_record`, booleans). Updates merge with stored values.

Semantic errors (422): `ohmylms_session_invalid_title`, `_invalid_timezone`, `_invalid_start`
(e.g. 2030-02-30), `_invalid_course`, `_invalid_password` (passcode with Google Meet).
`403 ohmylms_session_cannot_edit_course` when the course is not editable.

## Session

```json
{ "id": 5, "title": "Weekly review", "status": "publish", "provider": "zoom",
  "start": "2030-03-04T10:00", "timezone": "Europe/Berlin", "duration": 45, "state": "upcoming",
  "course": { "id": 40, "title": "Algebra" },
  "meeting": { "created": true, "join_url": "https://zoom.us/j/987?pwd=…" },
  "description": "…", "password": "abc123",
  "options": { "host_video": true, "participant_video": true, "join_before_host": false,
               "mute_upon_entry": true, "auto_record": false },
  "can_delete": true }
```

List rows omit `description`, `password`, `options` and `can_delete`. `state` is `unscheduled`,
`upcoming`, `live` or `ended`, computed from start, duration and time zone. `join_url` is the
attendee link (https only) once the provider reported a meeting. Single-session responses send
`Cache-Control: no-store`.

Attendee row: `{ user_id, name, status: enrolled|banned, completed }`: the students enrolled in the
session's course (chapter placement, else the parent course) and whether their progress marks this
session completed. No e-mail or other contact data. Without a course the list is empty.

## Provider flow and failure handling

1. Validate the merged values (schema, then the 422 checks). Nothing reaches the provider before that.
2. Write the post (`post_parent` = course) and the stored meta (`_platform`, `_start_date` as
   `Y-m-d H:i:s` in the session zone, `_timezone`, `_duration`, `_password`, `_<option>`).
3. Reset `_is_{provider}_session_created` to `no`, fire the provider action with a request carrying
   `topic`, `agenda`, `date`, `endDate` (start + duration), `timezone`, `duration`, `password` and the
   options; the integration sets the flag to `yes` and stores the meeting.
4. If the flag is not `yes`: a new session is deleted, an updated one gets its previous values and flag
   back; the answer is `502 ohmylms_session_provider_failed` (message points to Integrations).

`created` is fired for a session without a meeting yet, `updated` otherwise. New action
`ohmylms_session_saved( $id )` fires after a successful write.

## Secrets

Never returned: Zoom account ID, client ID/secret, webhook secret, access tokens; Google client
credentials, access/refresh tokens; Zoom `start_url` (host token) and raw provider payloads
(`_zoom_meeting_data`, `_googlemeet_event_data`). `/sessions/providers` returns booleans only:
`available` (the integration's hooks are loaded) and `configured` (Zoom credentials complete; Google
refresh token present for the current user). Hosts start meetings from their provider account.

## Known limits

- Chapter placement of a session stays in the course builder; this API sets the parent course only.
- Live provider calls are not exercised by the integration test (the hooks are replaced by a
  recording fake). A provider that fails silently still leaves the flag at `no`, which the controller
  reports as 502.
- The Google integration creates meetings with the current user's OAuth token; another editor without
  a connected account gets 502 on update.
- `state` is not filterable server-side (it depends on per-session time zones).

## Authored admin screens

`#/sessions` (list: search, provider filter, paging, join links) and `#/sessions/<id>` (`new` to
schedule; editor with schedule, time zone, duration, course, Zoom passcode and options, provider
status with a link to `#/integrations`, read-only attendees, confirmed delete). Legacy routes replaced:
`/sessions` → `/sessions` (the map in `includes/Admin/LegacyRoutes.php` is owned elsewhere).
