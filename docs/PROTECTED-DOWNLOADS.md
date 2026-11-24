# Protected Downloads

Protected Downloads lets WordPress administrators share a document or ZIP archive with members on selected OhMyLMS plans. Add the **Protected Download** Gutenberg block to a lesson, post, or page, or share the download link directly.

## Configure private storage

If your site already uses private exam import storage, open **OhMyLMS → Downloads → Add Protected Download**. When `OHMYLMS_IMPORT_PRIVATE_DIR` points to a writable private directory, OhMyLMS creates a `protected-downloads` subfolder there for download files. This setup needs no additional constant.

If private exam import storage is unavailable, or you want a separate location, configure a dedicated folder:

1. Create a dedicated directory outside both the web server document root and the WordPress installation directory. Give the PHP process read and write access to it.
2. In `wp-config.php`, before WordPress loads, define `OHMYLMS_PRIVATE_DOWNLOAD_DIR` with the absolute path. For a Local site whose document root is `app/public`, a sibling directory works.

   ```php
   define( 'OHMYLMS_PRIVATE_DOWNLOAD_DIR', 'C:/Local Sites/math/app/private-downloads' );
   ```

   Use the path for your own installation. Keep the folder outside all web-served directories and aliases. PHP must be able to resolve the web server's `DOCUMENT_ROOT`. The explicit setting takes precedence over private exam import storage.

If you change the storage location later, move the existing files to the new folder and preserve their stored filenames. Otherwise, the existing records will point to missing files.

## Database

Protected Downloads uses the site's existing WordPress database. The title and publication status go in the posts table. The filename, private file key, and eligible plan IDs go in post meta. OhMyLMS checks access against the existing `<prefix>ohmylms_user_membership` table, where `<prefix>` is the site's WordPress table prefix. No new database, tables, or migration are required. Back up the database and the private directory together to restore both the records and their files.

## Supported files

PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ODT, ODS, ODP, RTF, TXT, CSV and ZIP. The plugin serves ZIP files as attachments without extracting them.

## Publish a download

1. In WordPress admin, go to **OhMyLMS → Downloads → Add Protected Download**.
2. Enter a title, choose a file under **Upload or replace file**, and select the eligible published plans under **Eligible membership plans**. If the list is empty, publish a membership plan in **OhMyLMS → Commerce** and reopen the download editor.
3. Publish the download. Copy **Download link**, or insert a **Protected Download** block in Gutenberg and select the download from the picker.
4. To replace the file, edit the existing download and upload the new version. The block and copied link continue to point to the same download record. Clear all eligible plans or unpublish the download to disable it.

## Verify access

Open the same link in separate browser sessions. Use one test account with a selected active plan, one without an eligible active plan, and a signed-out session.

| Visitor | Expected result |
| --- | --- |
| Signed out | WordPress sign-in opens with a return to the download link. |
| Signed in with any one selected active plan | The file downloads as an attachment. |
| Signed in without a selected active plan | Access is denied with HTTP 403 and links to the eligible published plans, when available. |
| Anyone opening a link for an unpublished download or a record without a file or selected plans | The download is unavailable with HTTP 404. |

OhMyLMS checks membership each time the link is opened, including when it was copied or forwarded. WordPress serves the file from the private directory and marks the response as private and non-cacheable.

If uploads fail, check the private directory, PHP write permissions, and the server upload limits. If an eligible member gets HTTP 404, check that the private directory still contains the file. For HTTP 403, check that the plan is published and that the member's enrollment is active and within its access dates.

See [Memberships and courses](MEMBERSHIP-COURSES.md) for the membership model.
