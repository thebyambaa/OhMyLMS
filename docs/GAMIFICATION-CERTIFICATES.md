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
- The tab wiring described above belonged to the recovered admin (`ohmylms_params`, `extendRoutes`, `extraTabs.mjs`, `CertificatesTab.jsx`) and was deleted at the cutover ([CUTOVER.md](CUTOVER.md)). In the typed app, certificates are their own routes, `#/certificates` and `#/certificates/:id` (`assets/src/app/features/certificates/`), and Gamification is `#/gamification` (`assets/src/app/features/gamification/`); old `#/certificate-edit/:id` and `#/gamification/…` addresses are mapped by [LEGACY-ROUTES.md](LEGACY-ROUTES.md).
- Extensions that registered an `editor-panel` for `/certificates` render on the certificates screen.

## Validation

The JavaScript and browser tests of the recovered tab wiring were deleted with it at the cutover. The typed
screens are covered by `npm run test:app` (`features/gamification/*.test.ts(x)`, `features/certificates/`) and
`tests/browser/admin-app-smoke.spec.cjs` opens `#/gamification`.
