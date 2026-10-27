# Administrator assistant and development worker

The bottom-right **Ask OhMyLMS** button appears only inside the OhMyLMS WordPress administrator screen. The server requires `manage_options`, `edit_posts`, and a valid `wp_rest` nonce for every chat, settings, pairing, job, and application-decision request. Learner feedback configuration is separate.

## LMS chat

Open **Connection**, choose an OpenAI model available to your account, enter the API key in the password field, and enable administrator chat. An existing OpenAI tutor key/model can be reused. Alternatively configure `OHMYLMS_ASSISTANT_API_KEY` and `OHMYLMS_ASSISTANT_MODEL` server constants (the API key also supports an environment variable). Blank key input preserves the current credential. Stored keys use the existing salts-based `Secrets` encryption; the browser receives only availability, never the credential. Do not put credentials into conversation messages.

Enable the existing MCP add-on for LMS tools. The first version offers exactly these existing MCP operations:

| Read operations | Reviewed creation operations |
| --- | --- |
| List courses; read a course; read its chapters | Create a new draft lesson |
| List lessons; read a lesson for editing context | Create a new shared skill |
| List syllabuses; list syllabus objectives | |
| List shared skills; read a shared skill | |
| Search the skill catalogue; list a unit's skills with how many questions exist for each | Propose a new **draft** quiz, unit test or course challenge for chosen skills (see below) |

There is no publication, deletion, enrolment, grading, question-answer-key access, existing-lesson replacement, arbitrary REST invocation, or filesystem execution in the LMS tools. Creation IDs and extra arguments are rejected. Each proposed write shows its concrete fields and waits for **Create now**. A receipt records the actual tool result. A subsequent provider outage does not make an executed write replayable.

OpenAI uses the [Responses function-calling API](https://developers.openai.com/api/docs/guides/function-calling), `store: false`, strict schemas, and server-owned conversation history. PHP then sends MCP `initialize` and `tools/call` JSON-RPC messages through the registered WordPress MCP Adapter. This is an actual local MCP bridge, not a public remote MCP server or a tunnel. Existing ability and REST permissions still execute. Only an exact internally created request object can use the signed-in administrator identity; external MCP bearer authentication is preserved.

Conversations expire after one hour and are bounded to 40 displayed messages, three provider rounds per turn, and 30 turns per ten minutes of activity. Closing/reopening the panel preserves its in-page conversation; a full page reload starts a new browser conversation. LMS content and tool results are treated as untrusted data. Messages render as text, never model-authored HTML.

## File attachments

Use **Attach files** or drag files anywhere into the chat panel. Remove a file with its × button before sending. Files remain in the browser until **Send**, then upload through the administrator-only, nonce-protected server endpoint to the configured OpenAI account. No public media-library copy is created.

Up to three files can accompany a message. Each may be up to 5 MB, subject to the site's smaller PHP/WordPress upload limit shown below the composer. Supported formats: PDF, PNG/JPEG/WebP, TXT/Markdown/CSV/JSON, DOCX, XLSX and PPTX. Text files must be UTF-8. Images/PDFs require a model that supports those inputs.

The server validates actual HTTP uploads, file types, size and ownership, and uses opaque administrator-bound handles rather than accepting arbitrary provider file IDs. Documents use Responses input_file and images use input_image. Provider uploads have an explicit one-hour expiry; abandoned uploads also expire. Attachment filenames appear beside the submitted message. Removing an unsent, already-uploaded file requests provider deletion. Files cannot become tool instructions or bypass action review. See the [official file-input guide](https://developers.openai.com/api/docs/guides/file-inputs) and [Files expiry reference](https://developers.openai.com/api/reference/resources/files/methods/create).

The development section and its worker routes are no longer registered in the chat. Existing worker source/journals are retained without an active in-LMS entry point.

## Building quizzes and unit tests with the assistant

Ask in plain words, for example "Make a unit test for Unit 3 with two questions per skill." The assistant reads the unit (`get_course_chapters`,
`list_unit_skills`), looks for skills if needed (`search_skill_catalogue`) and proposes `create_skill_assessment`. Like the other creation
tools it only proposes: nothing is made until the administrator approves the action in the chat.

- It can only create a **draft** and can never publish; a person publishes it from the quiz editor.
- It uses existing approved questions from the bank, in their own question types. It cannot write questions, answers or marks. New questions
  from AI go through the import tools and wait for human approval before any assessment can draw them.
- It names skills the unit already teaches, and `list_unit_skills` shows how many different approved questions each skill has. A skill with
  fewer than the requested questions makes the build fail with the shortage message, and nothing is left behind.
- Up to 40 skills, 1 to 10 questions per skill and 60 questions in all. The unit placement needs edit rights on the unit's course.
