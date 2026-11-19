# Protected Downloads

Protected Downloads lets WordPress administrators attach a PDF to one or more OhMyLMS membership plans. Use the Gutenberg block in a lesson, post, or page, or share the download link directly.

## Configure private storage

1. Create a dedicated directory outside both the web server document root and the WordPress installation directory. Give the PHP process read and write access to it.
2. In `wp-config.php`, before WordPress loads, define `OHMYLMS_PRIVATE_DOWNLOAD_DIR` with the absolute path. For a Local site whose document root is `app/public`, a sibling directory works.

   ```php
   define( 'OHMYLMS_PRIVATE_DOWNLOAD_DIR', 'C:/Local Sites/math/app/private-downloads' );
   ```

   Use the path for your own installation. The directory must already exist, and PHP must be able to resolve the web server's `DOCUMENT_ROOT`.

3. Include the private directory with your WordPress database backups. Restoring the database alone restores the download records. Restoring the directory supplies the PDFs referenced by those records.

## Publish a download

1. In WordPress admin, go to **OhMyLMS → Downloads → Add Protected Download**.
2. Enter a title, choose a PDF under **Upload or replace PDF**, and select the eligible published plans under **Eligible membership plans**.
3. Publish the download. Copy **Download link**, or insert a **Protected Download** block in Gutenberg and select the download from the picker.
4. To replace the PDF, edit the existing download and upload the new version. The block and copied link continue to point to the same download record. Clear all eligible plans or unpublish the download to disable it.

## Verify access

Open the same link in separate signed-in and signed-out browser sessions.

| Visitor | Expected result |
| --- | --- |
| Signed out | WordPress sign-in opens with a return to the download link. |
| Signed in with any one selected active plan | The PDF downloads as an attachment. |
| Signed in without a selected active plan | Access is denied with HTTP 403 and links to the eligible published plans, when available. |
| Anyone opening a link for an unpublished download or a record without a file or selected plans | The download is unavailable with HTTP 404. |

OhMyLMS checks membership each time the link is opened, including when it was copied or forwarded. WordPress serves the file from the private directory and marks the response as private and non-cacheable.

If uploads fail, check the configured directory, the PHP process permissions, and the server upload limits. If an eligible member gets HTTP 404, check that the private directory still contains the PDF. Review eligibility and the plan's publish status when the response is HTTP 403.

See [Memberships and courses](MEMBERSHIP-COURSES.md) for the membership model.
