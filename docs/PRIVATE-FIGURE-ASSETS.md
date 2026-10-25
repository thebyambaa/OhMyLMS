# Private figure assets: first implementation slice

Implemented October 25, 2026: author-only, manually specified PDF crops using bounded
Action Scheduler work and an explicitly configured local Poppler renderer. This is the
storage/rendering foundation of [the implementation plan](PDF-FIGURES-IMPLEMENTATION.md).
Automatic detection, teacher crop UI, placements, question-version bindings, publication
gates, student delivery, JSXGraph reconstruction, and reference-aware garbage collection
are not implemented by this slice.

## Deployment requirements

Use the existing `OHMYLMS_IMPORT_PRIVATE_DIR` outside all served directories and aliases.
Back up figure records and private files with the database. The PHP worker needs write
access to that directory and permission to execute a maintained Poppler `pdftoppm` binary.
Set its absolute path in site configuration, for example:

```php
define( 'OHMYLMS_PDFTOPPM_BINARY', '/usr/bin/pdftoppm' );
```

Use the host's installed executable, not a development-tool runtime path. Windows absolute
paths are supported. No renderer is downloaded or guessed from PATH. `proc_open`,
`proc_get_status`, `proc_terminate`, and `proc_close` must be enabled. GD and Imagick are
not required by this renderer. The capability endpoint exercises the binary and private
temporary storage under the requesting PHP runtime; scheduler workers must also have those
permissions. CLI success alone does not certify the production web or cron worker.

Each crop is limited to page 1–500, a 2400-pixel maximum raster side, 10 MiB stdout,
64 KiB stderr, and a shared 20-second rendering deadline. Output growth is monitored
every 20 milliseconds; these are application watchdogs, not operating-system memory or
disk quotas. Rasterizing hostile PDFs still requires host-level process isolation and
resource policy appropriate to the deployment. Private temporary files are removed after
normal completion and handled failures; a killed PHP worker can leave a workspace for
later operator cleanup. No automatic cleanup scans are introduced here.

## Author API

Core WordPress REST authentication and cookie nonce verification apply. Job operations
require the import owner to retain `edit_posts` and `upload_files`; another administrator
does not acquire access to the owner's sources.

| Request | Behavior |
| --- | --- |
| `GET /wp-json/ohmylms/v1/exam-import-renderer` | Run a capability probe; return safe engine/version/limit metadata or an explicit error. |
| `POST /wp-json/ohmylms/v1/exam-imports/{id}/figures` | Queue a crop from the validated question-paper source. |
| `GET /wp-json/ohmylms/v1/exam-imports/{id}/figures` | List the job's crop states and safe author-preview metadata. |

POST a JSON body such as:

```json
{
  "page": 2,
  "bounds": [0.1, 0.2, 0.9, 0.65]
}
```

Bounds are `[left, top, right, bottom]` in the range 0–1, measured from the top-left of
the rotated visible PDF CropBox. Poppler first renders the full visible page to establish
actual dimensions, then crops with floor/ceil pixel bounds using the same rendering flags.
The result retains normalized bounds, actual page raster dimensions, pixel bounds, and the
renderer contract version. Source checksums are verified before rendering.

Creation returns an asset UUID and `queued` state. Poll the listing through `rendering` to `ready` or `failed`.
A ready asset has a protected author-preview URL. Failed assets have a safe error category,
never raw renderer diagnostics. Repeating a POST with the same source checksum, page,
canonical bounds, and renderer settings reuses the asset and resumes eligible work.
Changed bounds create a new asset; ready PNG bytes are never overwritten.

There are at most 100 assets per import and three rendering attempts per asset. Repeated
deliveries of ready assets are no-ops. Failed scheduling returns an explicit error; retrying
the same POST can recover it. There is no automatic infinite retry loop.
An interrupted worker can leave `rendering` state; repeating the same POST after its lock
is released resumes within the attempt budget or exposes an exhausted-attempt failure.

## Storage, retention, and access

Assets use the nonpublic `ohmylms-figure` post type and private metadata. Files are named
`figure-{uuid}.png` within private storage. They are not Media Library attachments and
never pass through the public-upload media freezer. Metadata is persisted before rendering;
exclusive file creation prevents overwrites. A complete identical file left by an interrupted
worker can be recovered; a conflicting or damaged file fails visibly.

Only question-paper crops are supported here. Client-selected document paths and mark-scheme
sources are not accepted. Creation does not imply teacher review or approval.

`?ohmylms_figure={uuid}` streams a checksum-verified PNG only to its originating author
while that author retains the required capabilities. Responses use private no-store headers.
Guests and other administrators are denied. No student route is enabled before exact
question-version membership and delivery authorization are implemented.

Deleting an import removes the source PDF through existing cleanup but does not delete
its independent figure assets. This slice deliberately retains all crops, including
unreferenced ones. Reference-aware collection and immutable question-version associations
must land before deletion or collection of committed assets is enabled.

## Verification

`tests/php/figure-assets-integration.php` runs against this checkout's explicit Local site
with transactional fixture writes and a synthetic three-page PDF. It checks normal,
rotated, and CropBox pages; actual PNG dimensions; invalid bounds/pages; source integrity;
REST ownership; scheduler registration; idempotence; bounded retries; permissions;
retention after source deletion; byte-integrity enforcement; and process cleanup.

Set `OHMYLMS_TEST_PDFTOPPM` to an absolute binary path, then run with the Local PHP
configuration and MySQL port:

```sh
php tests/php/figure-assets-integration.php --local
```

It calls the crop worker within a rollback transaction; it does not establish live scheduler
throughput, browser rendering, production isolation, real-paper detection accuracy, or
question-version/player integration. Real exam PDFs and crops remain outside Git.

Verification on October 25, 2026:

- 46 crop integration checks passed with real Poppler and WordPress; database fixture writes rolled back and recorded fixture files removed.
- The existing exam-import integration rehearsal passed 22 checks; standalone registry/grading tests passed 16 checks.
- Scoped WordPress PHP standards and syntax checks passed for the changed services and new rehearsal.
- `npm test` passed 649 tests, with one skipped; formatting, source-contract checks, JavaScript lint, and CSS lint passed.
- `npm run check` stopped at repository-wide PHP standards failures outside these scoped files. Those failures were not suppressed.
- Composer was unavailable on PATH; its configured `php tests/unit.php` command was executed directly.
- Local PHP reports its existing missing Imagick DLL startup warning. Rendering succeeded without Imagick.
- The renderer was configured only for the rehearsal process. Site configuration and production worker capability remain unchanged and unverified.
