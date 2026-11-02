# Source development

Student frontend modules and add-on contracts are documented in [INTERACTIVITY.md](INTERACTIVITY.md).
They load on demand independently of the opt-in admin source build. The jQuery learner scripts
(`ohmylms.js`, `lesson/video-player.js`, `video-progress-tracker.js`) were removed in batches L1–L7;
`ohmylms-frontend` remains a script-less handle (see [LEARNER-FRONTEND](LEARNER-FRONTEND.md)).

The source build is opt-in: `OHMYLMS_SOURCE_ASSETS` makes the site load `build/sdk` instead of the published
`assets/dist/admin`. Use it on development sites only.

The admin is the typed app in `assets/src/app` ([ARCHITECTURE.md](ARCHITECTURE.md)); for its conventions and the feature workflow, read [REACT-DEVELOPMENT.md](REACT-DEVELOPMENT.md). Run `npm run check` before delivering source changes.

## Source layout

The recovered admin (`admin.php?page=ohmylms`, its Webpack factories, fragments, adapters and SDK) was deleted
at the cutover ([CUTOVER.md](CUTOVER.md)); `admin.php?page=ohmylms` only redirects to the new app.

- `assets/src/app/`: the typed admin app, its extension host (`app/extensions/`: registry, `api.ts`,
  `ExtensionBoundary.tsx`, slots, DOM mounting) and the registration entry.
- `assets/src/features/`: authored JavaScript the kept Webpack entries and the app still use: native lesson and
  quiz editors (`learning/`), question editors and models (`question-editor/`, `question-bank/`), assessment
  settings, content-hub helpers, curriculum CSV/API helpers, math runtime, printable papers, Schools and the
  signup fields (`students/registration-api.mjs`). `features/curriculum/icons.json` is read by
  `Curriculum/Icons.php`.
- `assets/src/ui/`: the shared UI kit with the PHP twin in `Design/Ui.php`.
- `assets/src/blocks/`: block editor and view scripts (quiz player, question player, interactive activity, subject
  browsing).
- `assets/src/frontend/`: front-end SCSS (built by `tools/build-frontend-styles.mjs`). The authored
  learner scripts that used to live in `frontend/js` were removed in L7; behaviour is in `assets/interactivity`.
- `assets/src/runtimes/` and `assets/src/static/`: the recovered front-end, block, Elementor,
  theme-support and image assets still shipped. `assets/src/manifest.json` maps each to its output;
  `tools/build-assets.mjs` copies or reprints them. Delete by manifest output, never by folder.
- `assets/src/design-system/admin/public-selectors.json`: the public class names `stylelint.config.mjs` allows.
- `build/`: generated output; never edit it.

## Commands

Use Node 26.0.0 and npm 11.12.1 as pinned in `.nvmrc` and `package.json`.

```sh
npm ci
npm run build
npm run dev
npm test
npm run lint
npm run test:reproducible
```

`build` runs `build:assets` (front-end styles and the manifest outputs), `build:sdk` (the Webpack entries) and `tools/sync-admin-assets.mjs`, which publishes `build/` to `assets/dist`. `dev` watches the manifest sources and runs Webpack in watch mode. `test` runs the `node:test` suites in `tests/js`; `test:app` runs the app's Vitest suites. `lint:source` (`tools/check-source.mjs`) parses every script and checks that authored relative imports resolve. Source maps embed source contents. Entries use WordPress-provided React and generate `*.asset.php` through [WordPress dependency extraction](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dependency-extraction-webpack-plugin/).

Installed PHP packages are included; do not clone another e-commerce package over this modified implementation.

For the disposable WordPress installation, set `OHMYLMS_TEST_CREDENTIALS` to its external JSON file and run `npm run test:browser` (Chromium; set `OHMYLMS_CHROMIUM_PATH` to use a specific binary), then `php tests/php/extensions-integration.php` with mysqli/mbstring. The PHP suite refuses databases other than `ohmylms_source_test`. Also run `php tests/unit.php` and `php tests/membership-permissions.php`.

## Opt-in and rollback

On a test site:

```php
define('OHMYLMS_SOURCE_ASSETS', true);
define('OHMYLMS_ENABLED_MODULES', ['examples']); // Optional, disabled by default.
```

Without these constants, shipped assets remain active and examples remain disabled. Missing generated files currently fall back to shipped assets; check browser inventories for fallback URLs before release. Turning off `OHMYLMS_SOURCE_ASSETS` restores the old JavaScript. Full rollback restores all three matching plugin folders from the source-baseline snapshot. Restore a database only with its matching code backup. This stage performs no additional prefix migration.

## Fixtures and provenance

`tools/create-test-site.py`, `setup-test-db.php`, `setup-test-user.php` and `tests/fixtures/isolation.php` document isolation. The site has a separate DB, generated administrator and local-only server. HTTP/email are blocked; tests mock known QPay endpoints in memory. The copied database contains site data and stays outside Git.

`tests/baseline-assets.json` is a versionable asset inventory. Ignored `test-results/` contains screenshots, traces and script/style/global/network observations. Never commit credentials, SQL dumps or authenticated traces. New dependencies have lockfile versions/integrity hashes; existing PHP licence files are retained. Recovered third-party JS has asset hashes, but its original package versions and missing extracted notices are not fully verified. Hash pinning is not package provenance; do not invent versions/licences.

## Communities

The Communities admin screen is retired: OhMyLMS has no communities backend, and the screen only worked with
the separate Community add-on's REST routes. Legacy `#/communities` links open Integrations, where the add-on
switch lives. The course `has_community` flag, the integration switch and the enrolment hook remain, so an
add-on can still provide communities and add its own admin page through the extension API
(`registerAdminPage`, see [EXTENSIONS.md](EXTENSIONS.md)). The recovered route and its fragments are deleted
with the rest of the recovered runtime (see [CUTOVER.md](CUTOVER.md)).

## Design tokens and admin page layout

Colors and fonts are chosen under Settings → Design → Colors & fonts and turned into CSS custom
properties by `includes/Design/Tokens.php`. Stylesheets read the variables instead of hard-coding
brand colors or fonts.

- Learner pages: `--ohmylms-primary-color` (`-rgb`, `-hover-color`, `-soft-color`),
  `--ohmylms-heading-color`, `--ohmylms-body-text-color`, `--ohmylms-progressbar-color`,
  `--ohmylms-outline-color` and `--ohmylms-font-family`. The font variable is left undefined for
  "Theme font", so write `var(--ohmylms-font-family, <fallback>)`.
- Admin app: the same brand variables plus `--wp-admin-theme-color*`, `--wp-components-color-*`, the
  Arco `--primary-1…9` scale and `--ohmylms-admin-*` (heading, text, muted, surface, subtle, border,
  radius, gutter, font). The Design screen previews admin colors live; saved values print on reload.
- Fonts: system, the theme font (learner pages) or one of the Cyrillic-capable Google Fonts in
  `Tokens::FONTS`. Values are validated; invalid colors and fonts fall back to the defaults.

Extension admin pages (`registerAdminPage`) render in the new app at `#/extensions/<id>`, inside the app layout.

## Course access and content links

`Course::has_access()` (and `Student::maybe_enrolled()`) answer "is the current user enrolled", not "may they
view it". Administrators and the course author get `false` unless they enrolled: checkout sends enrolled users
to the course instead of charging them again, the price box is hidden only from the enrolled, and only
learners get the review form. Previewing is granted where content is gated, to `manage_options` users and the
post author: `Hooks\CommonHook::restrict_access()`, `templates/single-lesson.php` and `SequentialMode`. Tests
that need an enrolled administrator must enrol one.

Lesson, quiz and assignment links are `/<course_base>/<course-slug>/<lessons|quizzes|assignments>/<slug>/`
(`Course::get_content_link()`, `ohmylms_get_pretty_content_permalink()`). The rewrite rules only read the
content slug, so a draft, pending or private course with a slug still serves as the base. Without a usable base
(no course slug, trashed course, plain permalinks) and for unpublished content, the link is
`?post_type=<type>&p=<id>`, which also takes the editor's `preview=true`. `tests/php/content-permalinks-integration.php`
fetches each case as an administrator.

## Known issues

- `GatewayAuthorizenet` is a global class that neither PSR-4 nor the classmap covers, so the gateway registry
  never builds it and `authorize-net.js` is never enqueued (PayPal had the same problem; it is now
  `OhMyLMS\Gateways\Paypal\GatewayPaypal`). Review its return and webhook paths before loading it.
- No real Stripe or QPay payment has been run against the Interactivity checkout; 800.local has no gateway
  enabled.

## Block-based Text lessons and the question engine

Current editor: built-in Block lessons (stored as `text`), audio and video lessons open the native WordPress post editor. Course and syllabus links navigate directly to it; old lesson hash routes redirect for bookmark compatibility. The retired embedded Gutenberg workspace, its lesson-only styles and localized settings have been removed. Shared rich-text components remain for assignments and custom lesson types. Add future lesson blocks through native WordPress block registration. See `assets/src/features/learning/README.md` for integration and validation details.

- Instructors author. Students only view and interact.
- Lessons render through `the_content()`. Lesson navigation uses full page loads today.

### Requirements for the Question Bank and Exam Engine (implemented)

The original requirements for inline quiz performance data, skills, attempt history, immutable versions, server-side answer keys, guest history and untouched completion are all implemented in the question bank and exam engine, not in the Text lesson editor, and are merged into `main`. See [ASSESSMENT-ENGINE.md](ASSESSMENT-ENGINE.md) for the design, rollout and limitations. Status per requirement:

| # | Status |
|---|---|
| 1 | Inline checks (`[ohmylms_question uuid="…"]` / `question-check` activity) record practice evidence that feeds the skill dashboard and recommendations. They are inserted as shortcode text from the lesson-editor panel rather than as a native editor node. |
| 2 | Skills are `ohmylms_skill` terms with UUIDs and prerequisites; questions map skills per part. Skills are a core feature (the old creatorlms-skills plugin was removed and the model rebuilt in ohmylms). |
| 3 | Attempts record user, context, question UUID, version, response, correctness and time (attempt items, practice items, grade events). |
| 4 | Immutable question versions; attempts and reports read the version the learner saw. |
| 5 | Answer keys never reach the page; option values are per-delivery tokens graded by REST/form submission. |
| 6 | Guests keep history in localStorage with a server credential; results attach after login through a one-time claim. |
| 7 | Inline checks and practice never call completion hooks. |

## Translations

Mongolian (`mn`) is the only bundled translation. Files in `languages/`: `ohmylms.pot` (template), `ohmylms-mn.po` (source of truth, machine translation that needs a native review), `ohmylms-mn.mo`, one Jed catalog per script handle `ohmylms-mn-<handle>.json` (the first file `wp_set_script_translations( <handle>, 'ohmylms', <plugin>/languages )` looks for; `ohmylms-admin-app` includes every webpack chunk its runtime loads) and `ohmylms-mn-ohmylms.json` (every JavaScript string, the fallback for plugin scripts without a handle file). Spanish, Dutch and Bengali were removed. After a build (chunk names are hashed, so the POT must reference the current bundles), regenerate the template, merge and compile:

```sh
wp i18n make-pot . languages/ohmylms.pot --slug=ohmylms --domain=ohmylms --include="ohmylms.php,includes,assets,templates,packages" --exclude="node_modules,vendor,tests,build" --skip-audit
python -m pip install polib==1.2.0
python tools/merge-translations.py merge --locale mn
python tools/merge-translations.py compile --locale mn --wp "wp"
python tools/merge-translations.py stats --locale mn --untranslated 30
```

Without a global `wp`, use Local's WP-CLI with the system PHP, e.g. `--wp "C:\php8.5.4\php.exe -n -d extension_dir=C:\php8.5.4\ext -d extension=mbstring -d memory_limit=2G -d error_reporting=24575 %LOCALAPPDATA%\Programs\local\resources\extraResources\bin\wp-cli\wp-cli.phar"`; `make-pot` also needs `-d extension=curl -d extension=openssl` (it fetches the block.json schema). `merge` has msgmerge semantics: unchanged strings keep their translation, a changed string gets the closest old translation (similarity ≥ 0.6) marked `fuzzy` with the old `#| msgid`, strings no longer in the POT become `#~` obsolete entries, and nothing is machine translated; `--extra other-mn.po` fills entries the main file leaves empty or fuzzy. `compile` builds the `.mo` and JSON from finished entries only (WP-CLI would ship fuzzy ones); add new script handles to `SCRIPT_HANDLES` in the script. Merging and compiling an unchanged catalog is byte-identical. Do not edit generated `.mo` or `.json` files by hand; `tools/compile-translations.py` is superseded.

WordPress 7 registers a plugin's language folder from its `Domain Path` header (the plugin root without one), so `ohmylms.php` declares `Domain Path: /languages`; without it the first `__()` at `init` caches the root folder and PHP strings stay English.

AI drafts (31 October 2026). Every untranslated or fuzzy string was drafted with `tools/translation-drafts.py`: `export` writes JSON chunks and a glossary of reviewed short strings, `import` writes drafts back and rejects any whose `{tokens}`, HTML tags or (for php-format/javascript-format strings) printf placeholders differ from the source. Drafts for strings shown on the admin menu, dashboard, learner pages and checkout (1,160) went live at once; the other 10,991 stayed `fuzzy` until a native speaker read the PO. That review was done on 2 November 2026 and `python tools/translation-drafts.py approve --po languages/ohmylms-mn.po` made them live (10,987 passed the placeholder, token and tag checks; four that had lost their `%s` were corrected by hand). New strings are drafted the same way and stay `fuzzy` until reviewed. Terms to check first: subscription "давтамжит захиалга" vs order "захиалга" (strings with both, or with "тогтмол төлбөр", are flagged with a translator comment); skill domain "хүрээ" (strings with "салбар" are flagged); unit and chapter are both "Бүлэг"; measurement unit "Нэгж"; question pool "Сан" or "асуултын багц" vs bundle "багц" and question bank "Асуултын сан"; syllabus "хичээлийн хөтөлбөр" vs curriculum "Хөтөлбөр"; placement test "түвшин тогтоох шалгалт". URL slugs, brand names and provider console labels are kept in English.

## Interface languages

The interface is available in English and Mongolian (`mn`, the code of `languages/ohmylms-mn.*` and of the
WordPress core pack), and each user picks.

- **Staff (wp-admin)** follow their WordPress profile language (Users › Profile › Language), as WordPress does.
  That covers PHP strings, the React app and every plugin script that depends on `wp-i18n`:
  `InterfaceLanguage::translate_scripts()` calls `wp_set_script_translations()` for those that have no text
  domain yet, so WordPress loads `ohmylms-<locale>-<handle>.json`. When a handle has no file,
  `InterfaceLanguage::script_catalog()` hands WordPress the all-strings `ohmylms-<locale>-ohmylms.json`, once
  per page. admin-ajax and REST calls whose referrer is a wp-admin screen keep WordPress' rule. Settings ›
  General links to the profile setting; there is no separate admin switcher.
- **Learners (front end, and admin-ajax/REST calls from learner pages)**: `OhMyLMS\I18n\InterfaceLanguage`
  filters `determine_locale`. The first of these that names an interface locale wins:
  1. the signed-in user's `locale` user meta (the profile field, so admin and learner pages agree);
  2. the `ohmylms_locale` cookie;
  3. `Accept-Language`, by quality: `mn`, `mn-MN` and any `mn-*` give `mn`, `en*` gives `en_US`, `*` and
     unknown languages fall through (`OhMyLMS\I18n\AcceptLanguage`);
  4. `en_US`.

  A signed-in user without a saved locale follows the cookie and then the browser until they choose. The
  filter does not run in wp-admin, cron, WP-CLI or installs, inside `switch_to_locale()`, or on
  wp-login.php when WordPress' own `wp_lang` picker is used. `<html lang>` follows, because
  `language_attributes()` reads `determine_locale()`.
- **More languages**: filter `ohmylms_interface_locales` (WordPress locale => name in that language). Each
  locale needs `ohmylms-<locale>.mo`, and the core pack for WordPress' own strings (dates, "Log in",
  comment forms): `wp language core install <locale>`. The plugin bundles no core packs. 800.local has
  `mn` installed; WordPress' latest Mongolian core pack is the 6.5 one, so core strings added since then
  stay English.

**Switcher.** `OhMyLMS\I18n\Switcher::render( $args )`, the theme helper `ohmylms_get_language_switcher( $args )`
and the shortcode `[ohmylms_language_switcher label="…" variant="inline|compact"]` print a GET form whose
buttons are `name="ohmylms_locale" value="<locale>"`, each with `aria-pressed` and `lang`, inside a labelled
group (the `compact` variant keeps the label for screen readers only). It is in `global/main-header.php`,
`profile/settings.php` and the Student Hub (menu footer, Settings › Account, sign-in screen); theme copies of
those templates made before this change do not show it. Settings › General › "Language switcher"
(`ohmylms_language_switcher`, default on) hides every switcher; the resolution, the cookie, `?ohmylms_locale=`
and the REST route keep working, so a theme can render its own. Styles: `assets/css/language-switcher.css`
(tokens only).

- With JavaScript, `ohmylms/language` posts the choice and reloads the page; if the request fails it loads the
  form's GET URL ([INTERACTIVITY](INTERACTIVITY.md#language-switcher)).
- Without JavaScript, `?ohmylms_locale=<locale>` on any front-end URL is applied on `template_redirect`, then a
  `303` (with `nocache_headers()`) goes back to the same URL without `ohmylms_locale` and
  `ohmylms_locale_nonce`. Visitors need no nonce; only their cookie changes. A signed-in user's profile
  changes only with the form's `ohmylms_locale_nonce` (action `ohmylms_switch_locale`). Unknown locales
  change nothing.
- REST (`OhMyLMS\I18n\Rest\LocaleController`, added through `ohmylms_rest_v1_controllers`):
  `GET ohmylms/v1/learner/locale` returns `{ locale, source, locales: [ { locale, tag, name } ] }`, with
  `source` one of `user`, `cookie`, `browser`, `default`. `POST` with `locale` (one of the interface locales,
  otherwise `400 rest_invalid_param`) saves it to the user meta when signed in and always sets the cookie,
  answering with `Cache-Control: no-store`. Signed-in callers send the `wp_rest` nonce (`403
  ohmylms_locale_invalid_nonce` without it; application passwords need none). Action:
  `ohmylms_interface_locale_changed( $locale, $user_id )`.
- Cookie: `ohmylms_locale`, one year, path `/`, `SameSite=Lax`, `HttpOnly`, `Secure` on HTTPS.

**Caching.** Logged-out pages now differ by the cookie and the browser language. Every front-end HTML response
sends `Vary: Accept-Language, Cookie` (`wp_headers`, merged with existing tokens). A page cache or CDN that
ignores `Vary` must key logged-out pages on the `ohmylms_locale` cookie and on whether `Accept-Language`
prefers `mn`, or must not cache them; otherwise visitors get the other language. Many CDNs skip caching
responses with `Vary: Cookie`, which is the safe default. The switch redirect and the POST answer are never
cached.

**Emails.** Every email is written in its recipient's language, whatever the language of the request or cron
run that sends it. A user's language is their `locale` user meta (the profile field the switcher also
writes); without one, for an address that belongs to no user, or for a language WordPress has no core pack
for, the site locale (`get_locale()`); never the cookie or browser of the current request.
`OhMyLMS\I18n\RecipientLocale::run( $recipient, $compose )` (user ID, `WP_User` or address) switches with
`WP_Locale_Switcher` (which reloads every loaded text domain, `ohmylms` included, and `$wp_locale`), runs
`$compose( $locale )` and restores the previous locale in a `finally`; calls nest. `RecipientLocale::each(
$recipients, $compose )` composes once per language: with one language `$compose` gets `$recipients`
unchanged, otherwise the recipients of each language. The whole composition runs inside: settings with
their translated defaults, subject, template, merge tags and `wp_mail()`. Several recipients (custom
recipients, guardian copies) get one email per language. `RecipientLocale::in_locale( $locale, $compose )`
runs in a given locale (used to render defaults in one language). Wrapped: every class in
`includes/Emails` (`AssignmentCommentMail` for both comment emails; the test send of
`EmailManager::send_test()` follows the administrator's profile), `Exams\ResultsEmail`,
`Automation\RuleRunner`, `Analytics\Reminders`, `Engagement\Point::send_email()`, school invitations, the
Zoom, Google Meet and cohort reminders (their subjects and bodies are `ohmylms` strings, one per line, with
the English text unchanged), and the account email of the e-commerce checkout.

Saved email settings hold only what an administrator changed. `EmailManager::save()` drops every text field
(subject, heading, message, button, link, footer, course suggestion) equal to its default as rendered in the
saving request's language, and `null` (or an empty subject, heading or message) resets a field; `enable` and
recipients are stored as before. A missing field is the class default, which comes from `__()` and is
therefore rendered in each recipient's language; a customized field is sent as typed, to everyone. The REST
item reports `defaults` and `customized` per field, and the editor offers "Reset to default" per field
([emails API](api/emails.md#stored-values-and-defaults)). Earlier saves stored every field; the 1.2.22 update
`ohmylms_update_1222_forget_email_defaults()` (`EmailManager::forget_defaults()` for every email and every
interface locale) removes stored values equal to the English or Mongolian default and is idempotent. It runs
from `Install::update()` once the plugin version is 1.2.22; to run it sooner: `wp eval
'ohmylms_update_1222_forget_email_defaults();'`.

Test: `OHMYLMS_RECIPIENT_LOCALE_TEST=1 php tests/php/recipient-locale-integration.php` (also covers a
Mongolian administrator saving only a subject, the reset, the clean-up and the three reminders).

Tests: `node --test tests/js/language.test.mjs` and `OHMYLMS_INTERFACE_LANGUAGE_TEST=1 php
tests/php/interface-language-integration.php`, which share the `Accept-Language` cases in
`tests/fixtures/accept-language.json`.

## Interactive items

`registerSlashCommand(id, {label, description, aliases, iconName, action(editor)})` still registers commands in the extension registry, but the recovered rich-text editor that showed them in its "/" menu was deleted with the legacy admin; no current editor reads them.

Interactive content is stored as a shortcode, e.g. `[ohmylms_activity type="reveal" prompt="..." answer="..."]`, and rendered by PHP (`ohmylms_register_activity`, see `includes/Extensions/Activities.php`). Behaviour uses the WordPress Interactivity API (`assets/extensions/reveal.js`, a script module). Only the shortcode is saved, so content filtering never strips markup. Plain shortcode attributes cannot contain double quotes; use a `data='{...}'` JSON attribute for richer data.

To add an activity: register the PHP activity and add a script module if it needs behaviour. Lesson pages render content after `wp_head`; on block themes `Activities::ensure_import_map()` prints the module import map in the footer so late-enqueued modules resolve `@wordpress/interactivity`.
