# OhMyLMS: current admin navigation map

Read from: includes/Admin/Menu.php, AppPage.php, LegacyRoutes.php, assets/src/app/{routes.tsx,layout/*,features/registry.ts,features/*/index.ts}, assets/src/features/content-hub/hubRoutes.mjs, docs/CONTENT-HUB.md.

## Two admin apps side by side
- **Legacy app:** `admin.php?page=ohmylms#/...`, a hash router whose screens are in assets/src/features and assets/src/extensions.
- **New React app:** `admin.php?page=ohmylms-app`, menu label "OhMyLMS (new)" (includes/Admin/AppPage.php). It uses assets/src/app.
- LegacyRoutes.php rewrites the old hash URLs to the new app's paths. The option `ohmylms_legacy` opts out of this.

## 1. WP admin menu (sidebar), in order
```
OhMyLMS (add_menu_page, position 6, manage_options)  includes/Admin/Menu.php
├─ Dashboard            → /dashboard
├─ Content Hub          → /content-hub   (replaces Courses, Assessments, Skills, Curriculum, Bundles submenus)
├─ Certificates         → /certificates  (ONLY when gamification is off)
├─ Sessions             → /sessions      (filter ohmylms_show_sessions_menu, default off)
├─ Membership           → /memberships   (plans, coupons, orders, subscriptions "share" it; pending-order badge forced to 0)
├─ Account Hub          → /accounthub
├─ Gamification         → /gamification/point-settings (when enabled)
├─ Webhooks             → /webhooks      (filter, default off)
├─ Settings             → /settings/general-settings
├─ (ohmylms_after_settings_menu_item hook)
├─ Help & Feedback      → https://ohmylms.com/contact-us/ (new tab)
├─ OhMyLMS (new)        → page=ohmylms-app            includes/Admin/AppPage.php
├─ Add-ons              → /integrations               includes/Hooks/CommonHook.php
├─ Skill assessments    → page (edit_posts)           includes/Assessment/SkillAssessmentPage.php
└─ xAPI                 → registered then removed (URL kept)  includes/XApi/AdminPage.php
Hidden pages (parent null/options.php): AI tutor (includes/AI/AdminPage.php), Student Home + editor
(includes/Student/Hub.php), "Students" page=ohmylms-schools (includes/Schools/Views.php),
Review imported questions (includes/QuestionBank/ImportPage.php), Layouts & extensions (includes/Extensions/Editor.php).
```

## 2. Legacy Content Hub tabs (hubRoutes.mjs)
```
/content-hub
├─ Courses        /content-hub/courses
├─ Lessons        /content-hub/lessons
├─ Quizzes        /content-hub/quizzes
├─ Exams          /content-hub/exams
├─ Question Bank  /content-hub/question-bank
├─ Assignments    /content-hub/assignments
├─ Skills         /content-hub/skills
└─ Curriculum     /content-hub/curriculum   (bundles alias here; /categories,/tags also land here)
```

## 3. New app: in-app nav (registry.ts order; header nav, flat list, no groups)
The nav is rendered by AppLayout.tsx in `<header>` with an `<h1>OhMyLMS` and Navigation.tsx (NavLinks to every non-hidden feature, plus extension `admin-page` links at /extensions/:id). Routes are filtered by capability.
```
Dashboard            /dashboard                       features/dashboard
│  └ (hidden) Course report /reports/courses/:id
Earnings             /earnings                        features/dashboard
Home                 /                                features/home
Setup                /setup                           features/setup
Content Hub          /content-hub/skills  (Skills page only)   features/content-hub
Curriculum           /content-hub/curriculum          features/curriculum
│  ├ (hidden) Skill map         /content-hub/curriculum/skill-map
│  ├ (hidden) Subject profiles  /content-hub/curriculum/subjects
│  ├ (hidden) Syllabus editor   /content-hub/curriculum/syllabus/:id   features/syllabus
│  └ (hidden) Course bundles    /content-hub/bundles
(hidden) Course structure  /content-hub/courses, /:id, /:id/settings   features/course-structure
Courses              /courses   └ (hidden) /courses/:id/:tab?          features/courses
Quizzes              /quizzes   └ (hidden) /quizzes/:id, /quizzes/:id/attempts
Assignments          /assignments └ (hidden) /:id, /:id/submissions, /:id/submissions/:submissionId
Lessons              /content-hub/lessons └ (hidden) /content-hub/lessons/:id
Question bank        /content-hub/question-bank └ (hidden) /:id
Orders               /orders    └ (hidden) /orders/:id                 features/commerce
Coupons              /coupons
Subscriptions        /subscriptions └ (hidden) /subscriptions/:id
Memberships          /memberships └ (hidden) /memberships/:id
Students             /students
Account Hub          /account-hub  tabs: Schools | Classes | Teachers | Parents
│  ├ (hidden) School  /account-hub/schools/:id
│  └ (hidden) Class   /account-hub/classes/:id
Certificates         /certificates └ (hidden) /certificates/:id
Gamification         /gamification (?tab=points|badges|levels|leaderboard|streaks|xp)
Emails               /emails
Integrations         /integrations
Settings             /settings (?tab=general|branding|account-and-privacy|permalink|advanced|ai-tutor|design-system|xapi|payment-gateways|currency|tax)
Categories and tags  /taxonomies
Webhooks             /webhooks
(hidden) Extensions  /extensions/:id
* → NotFound
```

## Oddities
- The new app has **24 flat nav items** with no grouping. The WP menu has about 9.
- **The Content Hub is split up.** Its new-app nav item opens Skills only. Courses, Quizzes and Assignments became top-level `/courses`, `/quizzes` and `/assignments`, and LegacyRoutes sends `/content-hub/{courses,quizzes,assignments}` there. Lessons, Question bank and Curriculum stay under `/content-hub/*` but each gets its own nav item.
- **Exams is orphaned.** The legacy hub has an Exams tab (`/content-hub/exams`) with no new-app route.
- **Two course editors.** `courses` (/courses/:id) and the hidden `course-structure` (/content-hub/courses/:id) both claim the extensionRoute `/course-edit/:id/:step?/:subStep?`. LegacyRoutes sends course-edit to /courses/:id.
- **Membership is split.** The WP "Membership" entry bundles plans, coupons, orders and subscriptions. The new app splits them into 4 nav items, and Earnings sits apart from them.
- **People are in three places:** Students (/students), Account Hub (/account-hub), and a hidden PHP page labeled "Students" (ohmylms-schools). LegacyRoutes has 3 rules for `/accounthub`, pointing to /account-hub, /account-hub?tab=, and /students.
- **Certificates conflict.** The WP menu puts Certificates inside Gamification when gamification is enabled. The new app always has a separate /certificates, and maps /gamification/certificates there.
- **Taxonomies conflict.** "Categories and tags" (/taxonomies) is still in the nav, yet LegacyRoutes says Curriculum and Bundles replace categories and tags (/categories and /tags go to curriculum).
- **Both Home (/) and Dashboard (/dashboard)** are in the nav. Setup is a permanent nav item.
- **Misnamed entries:** "Add-ons" opens Integrations, and xAPI is registered and then removed (its settings are a Settings tab).
- **Dead code in Menu.php:** the pending-order badge is hard-coded to 0, and a "Free vs Pro" submenu is commented out.
