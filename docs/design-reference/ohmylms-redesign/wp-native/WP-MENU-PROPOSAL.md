# OhMyLMS inside the native WordPress admin menu

Scope: the menu **organization stays exactly as in ../PROPOSED-NAV.md**: Overview, Content Hub, Account Hub, Commerce, Engagement (incl. Sessions), Settings. Only the **placement** changes. The menu moves into the standard WP admin sidebar and uses default wp-admin styling. The competitor research below is used only for how they place and nest menus in WordPress.

## 1. How competitors place their menus

### Tutor LMS
Sources: https://tutorlms.com/docs/menu/courses-list/, https://tutorlms.com/docs/menu/students-list/, https://tutorlms.com/docs/menu/instructors-list/, https://tutorlms.com/docs/menu/tools/
- **One top-level menu, flat submenus.** Docs refer to "WP Admin > Tutor LMS > Students" and "Tutor LMS > Settings > General". The Instructors submenu appears only when Marketplace is enabled. Pro labels the menu "Tutor LMS Pro".
- **Courses list:** a "+Add New" button, view tabs (All, Mine, Published, Draft…), and columns for Title with thumbnail, Categories, Author, Price, Date and Status. Status is a dropdown. A "⋮" menu offers Edit, Duplicate and Delete Permanently, and each title shows counts of Topics, Lessons, Quizzes and Assignments.
- **Students list:** Bulk Action at top-left, filter by Course and Date, search, sortable columns, and a "Details" button that opens the student's report.
- **Tools** is one submenu whose sections are **tabs**: Status, Import/Export, Tutor Pages, Rest API, Settings Log, WooCommerce Migration.

### Creator LMS
Sources: https://creatorlms.net/docs/user-management-order-settings/creator-lms-order-management-and-analytics/, https://wordpress.org/plugins/creatorlms/, https://creatorlms.net/changelog/, https://creatorlms.net/docs/getting-started-with-creator-lms/
- **One plugin menu.** The changelog (1.2.21, 2026-09-28) lists its rebuilt admin screens: dashboard, courses, students, memberships, subscriptions, orders, coupons, certificates, categories, tags, quizzes, assignments, add-ons and settings. Docs say "click on 'Orders' in the left-hand menu".
- **Dashboard:**
  - Earnings for the last 30 days (income, refunds, net) with a "Review Orders" link.
  - Top Selling Courses on the right.
  - Courses Sold and enrollments.
  - A published-courses list with a growth indicator.
- **Orders list:** columns for student, payment method, total and status; a "⋮ → View Order" detail page; filters at the top.
- **Other screens:** a setup wizard you can resume from the dashboard, and a per-course students page.

The source repo also has a `docs/CREATORLMS-COEXISTENCE.md` (not read here).

**Placement takeaway** (chosen option in §2): both use **one top-level plugin menu with a flat submenu list**. Sub-sections inside a screen are page tabs, not deeper menu levels. Optional items only appear when their feature is enabled. I found no evidence of either plugin using several top-level menus.

## 2. Chosen design: six group pages with tabs
The six PROPOSED-NAV groups become six real WP submenus. Each group's items become WP-native `nav-tab-wrapper` tabs on that group's page. This follows the Tutor LMS Tools pattern (one submenu, sections as tabs) and keeps the WP menu two levels deep. The items are unchanged.

```
OhMyLMS  [badge: pending orders]              add_menu_page( 'OhMyLMS', …, 'ohmylms', …, position 6 )
├─ Dashboard     admin.php?page=ohmylms                        (no tabs; Home + Dashboard merged)
├─ Content Hub   admin.php?page=ohmylms-content&tab=…
│    courses | lessons | quizzes | exams | assignments | question-bank | skills | curriculum
├─ Account Hub   admin.php?page=ohmylms-accounts&tab=…
│    students | schools | classes | teachers | parents
├─ Commerce      admin.php?page=ohmylms-commerce&tab=…   [badge]
│    earnings | orders | memberships | subscriptions | coupons
├─ Engagement    admin.php?page=ohmylms-engagement&tab=…
│    gamification | sessions (only when ohmylms_show_sessions_menu is on) | emails
└─ Settings      admin.php?page=ohmylms-settings&tab=…
     settings | integrations | webhooks | setup
```

| Group page | Tab label | Slug |
|---|---|---|
| Dashboard | — | `page=ohmylms` |
| Content Hub | Courses (default) | `page=ohmylms-content&tab=courses` |
| | Lessons | `…&tab=lessons` |
| | Quizzes | `…&tab=quizzes` |
| | Exams | `…&tab=exams` |
| | Assignments | `…&tab=assignments` |
| | Question bank | `…&tab=question-bank` |
| | Skills | `…&tab=skills` |
| | Curriculum & bundles | `…&tab=curriculum` |
| Account Hub | Students (default) | `page=ohmylms-accounts&tab=students` |
| | Schools / Classes / Teachers / Parents | `…&tab=schools` / `classes` / `teachers` / `parents` |
| Commerce | Earnings (default) | `page=ohmylms-commerce&tab=earnings` |
| | Orders / Memberships & plans / Subscriptions / Coupons | `…&tab=orders` / `memberships` / `subscriptions` / `coupons` |
| Engagement | Gamification & certificates (default) | `page=ohmylms-engagement&tab=gamification` |
| | Sessions (when enabled) | `…&tab=sessions` |
| | Emails | `…&tab=emails` |
| Settings | Settings (default) | `page=ohmylms-settings&tab=settings` |
| | Integrations & add-ons / Webhooks / Setup | `…&tab=integrations` / `webhooks` / `setup` |

These slugs are proposed; none exist in the repo yet.
- **Detail screens** add `&id=` and an optional `&view=` (for example `page=ohmylms-content&tab=courses&id=12&view=settings`).
- **Second-level sections** use `&section=`, rendered as `.subsubsub` links under the tab rather than a second tab row. Examples are Curriculum & bundles › Bundles / Skill map / Subjects / Categories & tags, Skills › Assessments, and Settings › xAPI / AI tutor.

## 3. Implementation notes
- **Menu registration (includes/Admin/Menu.php):**
  - `add_menu_page` keeps slug `ohmylms` at position 6.
  - Six `add_submenu_page` calls register `ohmylms` (relabel `$submenu['ohmylms'][0][0]` to "Dashboard"), `ohmylms-content`, `ohmylms-accounts`, `ohmylms-commerce`, `ohmylms-engagement` and `ohmylms-settings`.
  - Remove the current `$submenu[$slug][]` hash entries, and stop CommonHook.php and SkillAssessmentPage.php from adding their own submenus. Those screens become tabs.
  - Restore the pending-orders badge on both the top-level menu and Commerce. It is currently forced to 0.
- **Tabs as data:**
  - Each feature in `assets/src/app/features/registry.ts` gets `group` and `tab` keys.
  - PHP builds each page's tab list from a PHP mirror of the registry (or a localized JSON). It filters by capability per tab and by flags. Sessions appears only with `ohmylms_show_sessions_menu`; gamification and webhooks keep their existing filters.
  - Hidden tabs are not rendered. If someone requests a hidden tab, it falls back to the group's default tab.
  - The submenu's capability is the lowest capability among its tabs. If a user can see no tabs, the submenu is not registered.
- **Rendering:**
  - Each group page prints `<div class="wrap"><h1 class="wp-heading-inline">Group</h1><nav class="nav-tab-wrapper">…</nav><div id="ohmylms-root" data-page data-tab></div>`.
  - Tabs are real `<a href="admin.php?page=…&tab=…">` links, so they work without JS, keep the WP menu highlight, and can be bookmarked.
  - React mounts the tab's screen. Inside a tab, use `history.replaceState` to update the query string. Use `@wordpress/components` (Button, Card, Notice, SelectControl, SearchControl), and WP list-table markup (`.wp-list-table.widefat.striped`, `.subsubsub`, `.tablenav`, `.row-actions`) or `WP_List_Table` for lists.
- **Retire "OhMyLMS (new)":**
  - Remove the AppPage.php submenu (`page=ohmylms-app`) and the AppLayout.tsx header and nav.
  - Keep `ohmylms-app` and the hash URLs as redirect-only routes (see §4).
- **Styling:** use WP admin color variables (`--wp-admin-theme-color`) on admin screens, and keep `--oml-*` for learner-facing pages. No `#adminmenu` overrides are needed with this option.

## 4. Old-route redirect map (LegacyRoutes.php)
Applies to `admin.php?page=ohmylms#/<route>` (handled client-side, since the hash never reaches PHP) and to `page=ohmylms-app` + `<route>` (handled server-side with `wp_safe_redirect`). Routes are from ../PROPOSED-NAV.md. Sub-paths `/:id` map to `&id=`, and trailing segments map to `&view=`.

| Old route | New URL (`admin.php?…`) |
|---|---|
| `/`, `/dashboard` | `page=ohmylms` |
| `/courses`, `/courses/:id/:tab?`, `/content-hub/courses[/:id[/settings]]` | `page=ohmylms-content&tab=courses[&id=…&view=…]` |
| `/reports/courses/:id` | `page=ohmylms-content&tab=courses&id=…&view=report` |
| `/content-hub/lessons[/:id]` | `page=ohmylms-content&tab=lessons[&id=…]` |
| `/quizzes[/:id[/attempts]]` | `page=ohmylms-content&tab=quizzes[&id=…&view=attempts]` |
| `#/content-hub/exams` | `page=ohmylms-content&tab=exams` |
| `/assignments[/:id/submissions[/:sid]]` | `page=ohmylms-content&tab=assignments[&id=…&view=submissions&sid=…]` |
| `/content-hub/question-bank[/:id]`, "Review imported questions" page | `page=ohmylms-content&tab=question-bank[&id=…│&section=import-review]` |
| `/content-hub/skills`; SkillAssessmentPage submenu | `page=ohmylms-content&tab=skills` / `…&section=assessments` |
| `/content-hub/curriculum[/syllabus/:id]` | `page=ohmylms-content&tab=curriculum[&view=syllabus&id=…]` |
| `/content-hub/curriculum/skill-map`, `/subjects`, `/content-hub/bundles`, `/taxonomies` (`#/categories`, `#/tags`) | `page=ohmylms-content&tab=curriculum&section=skill-map│subjects│bundles│taxonomies` |
| `/students`; `page=ohmylms-schools` (hidden Students) | `page=ohmylms-accounts&tab=students` |
| `/account-hub` (`?tab=schools│classes│teachers│parents`), `/account-hub/schools/:id`, `/classes/:id` | `page=ohmylms-accounts&tab=<tab>[&id=…]` (default `schools` for bare `/account-hub`) |
| `/earnings` | `page=ohmylms-commerce&tab=earnings` |
| `/orders[/:id]` | `page=ohmylms-commerce&tab=orders[&id=…]` |
| `/memberships[/:id]` | `page=ohmylms-commerce&tab=memberships[&id=…]` |
| `/subscriptions[/:id]` | `page=ohmylms-commerce&tab=subscriptions[&id=…]` |
| `/coupons` | `page=ohmylms-commerce&tab=coupons` |
| `/gamification?tab=…` | `page=ohmylms-engagement&tab=gamification&section=…` |
| `/certificates[/:id]` | `page=ohmylms-engagement&tab=gamification&section=certificates[&id=…]` |
| `#/sessions` | `page=ohmylms-engagement&tab=sessions` (falls back to `tab=gamification` when disabled) |
| `/emails` | `page=ohmylms-engagement&tab=emails` |
| `/settings?tab=…` (incl. `xapi`), AI tutor page | `page=ohmylms-settings&tab=settings&section=…│xapi│ai-tutor` |
| `/integrations`; "Add-ons" submenu | `page=ohmylms-settings&tab=integrations` |
| `/webhooks` | `page=ohmylms-settings&tab=webhooks` |
| `/setup` | `page=ohmylms-settings&tab=setup` |
| `/extensions/:id` | the declared group's page, `&tab=ext-<id>` (default group: Settings) |
| `page=ohmylms-app` (no route) | `page=ohmylms` |

Unchanged (not redirected): Student Home (Student/Hub.php), Layouts & extensions (Extensions/Editor.php). Help & Feedback becomes a link on the Dashboard page.

## Mockups (fake data)
`01-wp-dashboard.html/.png`, `02-wp-courses.html/.png`, `03-wp-students.html/.png`. They show a 1440×900 wp-admin look-alike: admin bar, #1d2327 sidebar with the OhMyLMS menu expanded to its 6 submenus (current one bold); 02 shows Content Hub › Courses tab, 03 shows Account Hub › Students tab, WP notices, postboxes, and a WP list table with views, bulk actions, row actions and pagination.
