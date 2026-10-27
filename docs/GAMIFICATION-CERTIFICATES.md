# Certificates in the Gamification screen

With the free **Gamification** add-on on, **Certificates** is the seventh tab of **OhMyLMS → Gamification** (Bonus Point, Achievement Badges, Learner Levels, Reward, Leaderboard, Streaks, **Certificates**) and no longer has an entry of its own in the admin menu. Its screen is the same one that had the entry: the certificate list with search and course filter, **Add Certificate**, and the template editor.

| Address | What opens |
| --- | --- |
| `#/gamification/certificates` | The Certificates tab |
| `#/certificates` | Opens the tab (old links and bookmarks keep working) |
| `#/certificate-edit/:id` | The template editor, with **Gamification** highlighted in the menu |
| `#/settings/gamification-settings/…` | Settings → Gamification, which keeps its six settings tabs (Certificates is not a setting) |

## When Gamification is off

There is no Gamification screen, so Certificates keeps its own **Certificates** menu entry and screen at `#/certificates`, as before. Switching Gamification on or off in **Add-ons** moves it between the two; no certificate is changed either way.

## How it works

- `includes/Admin/Menu.php` adds the **Certificates** entry only while `ohmylms_show_gamification_menu` is false.
- The page's `ohmylms_params.is_gamification_enabled` flag (the same filter) tells the admin app which layout is in use.
- `assets/src/extensions/index.jsx` (`extendRoutes`) takes the application's own `/certificates` screen and registers it as a Gamification tab through `assets/src/features/gamification/extraTabs.mjs`. `GamificationSettings.jsx` appends the registered tabs on the standalone screen only. `/certificates` is then replaced by a redirect to the tab, and `/certificate-edit/:id` is wrapped so Gamification stays highlighted.
- The certificate screens call the application's menu highlight for the retired entry on every render, so `features/gamification/CertificatesTab.jsx` puts the Gamification highlight back with the shared `features/menuHighlight.js` hook (the Content Hub uses the same one). The recovered certificate components are untouched.
- Extensions that registered an `editor-panel` for the `/certificates` route still render under the tab.

## Validation

```sh
node --test tests/js/gamification.test.mjs tests/js/content-hub.test.mjs
OHMYLMS_TEST_CREDENTIALS=... OHMYLMS_CHROMIUM_PATH=... OHMYLMS_PHP=... node node_modules/@playwright/test/cli.js test tests/browser/gamification.spec.cjs --workers=1
```

The browser test switches the add-on on the disposable site with `tests/php/gamification-browser-fixture.php` (it refuses any database other than `ohmylms_source_test`) and puts the option back afterwards. It follows `#/certificates` into the tab, checks the seven tabs and the menu highlight, that Settings → Gamification has six tabs, that the template editor keeps Gamification highlighted, and that with the add-on off Certificates keeps its own screen and entry.
