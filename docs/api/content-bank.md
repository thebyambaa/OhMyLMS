# Content Bank folders and tags

The Content Bank page (`admin.php?page=ohmylms-bank`) holds the reusable content: lessons, quizzes, exams,
assignments and the question bank. Folders (a tree) and tags (a flat list) organize that content for authors;
learners never see them.

Folders and tags are the taxonomies `ohmylms_bank_folder` and `ohmylms_bank_tag`
(`OhMyLMS\ContentBank\Organization`), registered on the lesson, quiz, exam, assignment and question types. They
are not public and have no WordPress admin screen.

## Filtering lists

Every plugin list endpoint for those types accepts two extra query parameters on `GET`:

- `bank_folder`: a folder ID. Items in that folder and in its sub-folders.
- `bank_tag`: a tag ID.

Both together narrow the list to items that match both. The filter is applied to the post query itself, so it
works on `authoring/lessons`, `quizzes`, `assignments`, `exams/library` and `questions` without each controller
knowing about it.

## Endpoints (`ohmylms/v1/content-bank/*`)

All need `edit_posts`; each item is also checked with its own `edit_post` capability.

| Route | Purpose |
| --- | --- |
| `GET terms` | `{ folders: [{ id, name, parent }], tags: [...] }`. |
| `POST terms` | `{ kind: 'folder' \| 'tag', name, parent? }` creates one (201). A duplicate name is a 400. |
| `GET assignments?ids=1,2` | `{ "<id>": { folders: [ids], tags: [ids] } }` for items the user may read. |
| `POST organize` | `{ ids (1 to 100), folder_ids?, tag_ids?, mode: 'add' \| 'replace' \| 'remove' }`. |

`organize` only changes the lists it is given (`folder_ids`, `tag_ids`). `replace` with an empty list clears it.
Items that do not exist or that the user cannot edit are returned in `skipped` with a reason; they never fail
the request. A folder or tag that does not exist is a 400.
