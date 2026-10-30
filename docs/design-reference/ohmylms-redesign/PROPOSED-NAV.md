# Proposed grouped admin menu

Based on CURRENT-NAV-MAP.md. Groups are collapsible. The group holding the current screen opens automatically, and its item is marked `aria-current="page"`.

```
OhMyLMS
├─ Overview
│  └─ Dashboard                 /            (merges Home + Dashboard)
├─ Content Hub
│  ├─ Courses                   /content-hub/courses
│  ├─ Lessons                   /content-hub/lessons
│  ├─ Quizzes                   /content-hub/quizzes
│  ├─ Exams                     /content-hub/exams
│  ├─ Assignments               /content-hub/assignments
│  ├─ Question bank             /content-hub/question-bank
│  ├─ Skills                    /content-hub/skills
│  └─ Curriculum & bundles      /content-hub/curriculum   (tabs: Curriculum, Bundles, Skill map, Subjects, Categories & tags)
├─ Account Hub
│  ├─ Students                  /account-hub/students
│  ├─ Schools                   /account-hub/schools
│  ├─ Classes                   /account-hub/classes
│  ├─ Teachers                  /account-hub/teachers
│  └─ Parents                   /account-hub/parents
├─ Commerce
│  ├─ Earnings                  /commerce/earnings
│  ├─ Orders                    /commerce/orders
│  ├─ Memberships & plans       /commerce/memberships
│  ├─ Subscriptions             /commerce/subscriptions
│  └─ Coupons                   /commerce/coupons
├─ Engagement
│  ├─ Gamification & certificates  /engagement/gamification   (Certificates = tab)
│  ├─ Sessions                  /engagement/sessions   (shown when ohmylms_show_sessions_menu is on)
│  └─ Emails                    /engagement/emails
└─ Settings
   ├─ Settings                  /settings
   ├─ Integrations & add-ons    /settings/integrations
   ├─ Webhooks                  /settings/webhooks
   └─ Setup                     /settings/setup
```

New paths are a proposal. Keep every current path working as a redirect, either through LegacyRoutes.php or alias routes.

## Mapping: current → new

| Current label | Current route / page | New location |
|---|---|---|
| Home | `/` | Overview › Dashboard (merged) |
| Dashboard | `/dashboard`; WP menu "Dashboard" | Overview › Dashboard |
| Course report (hidden) | `/reports/courses/:id` | Content Hub › Courses › course report |
| Earnings | `/earnings` | Commerce › Earnings |
| Content Hub (nav) | `/content-hub/skills`; WP menu "Content Hub" | Content Hub group (Skills item) |
| Courses | `/courses`, `/courses/:id/:tab?` | Content Hub › Courses |
| Course structure (hidden) | `/content-hub/courses`, `/:id`, `/:id/settings` | Content Hub › Courses. Merge with the `/courses/:id` editor into ONE editor (structure + settings tabs) |
| Lessons | `/content-hub/lessons`, `/:id` | Content Hub › Lessons |
| Quizzes | `/quizzes`, `/:id`, `/:id/attempts` | Content Hub › Quizzes |
| Exams (legacy tab, orphan) | `#/content-hub/exams` | Content Hub › Exams (needs a new-app screen) |
| Assignments | `/assignments`, `/:id`, `/:id/submissions[/:submissionId]` | Content Hub › Assignments |
| Question bank | `/content-hub/question-bank`, `/:id`; hidden "Review imported questions" | Content Hub › Question bank (import review as a sub-screen) |
| Skill assessments | WP submenu (SkillAssessmentPage.php) | Content Hub › Skills › Assessments tab |
| Curriculum | `/content-hub/curriculum` | Content Hub › Curriculum & bundles |
| Skill map / Subject profiles (hidden) | `/content-hub/curriculum/skill-map`, `/subjects` | Curriculum & bundles tabs |
| Syllabus editor (hidden) | `/content-hub/curriculum/syllabus/:id` | Curriculum & bundles › syllabus editor |
| Course bundles (hidden) | `/content-hub/bundles` | Curriculum & bundles › Bundles tab |
| Categories and tags | `/taxonomies`; legacy `#/categories`, `#/tags` | Curriculum & bundles › Categories & tags tab |
| Students | `/students`; hidden PHP "Students" `page=ohmylms-schools` | Account Hub › Students |
| Account Hub | `/account-hub` (tabs Schools/Classes/Teachers/Parents); WP "Account Hub" | Account Hub › Schools / Classes / Teachers / Parents (tabs become items) |
| School / Class (hidden) | `/account-hub/schools/:id`, `/classes/:id` | Account Hub › Schools / Classes detail |
| Orders | `/orders`, `/orders/:id`; part of WP "Membership" | Commerce › Orders |
| Coupons | `/coupons` | Commerce › Coupons |
| Subscriptions | `/subscriptions`, `/:id` | Commerce › Subscriptions |
| Memberships | `/memberships`, `/:id`; WP "Membership" | Commerce › Memberships & plans |
| Sessions (filter, off) | `#/sessions` | Engagement › Sessions (when the sessions filter is enabled) |
| Gamification | `/gamification?tab=…`; WP "Gamification" | Engagement › Gamification & certificates |
| Certificates | `/certificates`, `/:id`; WP "Certificates" | Engagement › Gamification & certificates › Certificates tab (always, regardless of gamification toggle) |
| Emails | `/emails` | Engagement › Emails |
| Settings | `/settings?tab=…`; WP "Settings" | Settings › Settings |
| xAPI (removed submenu) | Settings tab `xapi` | Settings › Settings › xAPI tab |
| AI tutor (hidden page) | `ohmylms-ai…` | Settings › Settings › AI tutor tab |
| Integrations | `/integrations`; WP "Add-ons" | Settings › Integrations & add-ons |
| Webhooks | `/webhooks`; WP "Webhooks" | Settings › Webhooks |
| Setup | `/setup` | Settings › Setup |
| Extensions pages | `/extensions/:id` | The group the extension declares (default: Settings) |
| Student Home + editor (hidden) | Student/Hub.php | Unchanged (learner-facing). Link from Account Hub › Students |
| Layouts & extensions (hidden) | Extensions/Editor.php | Unchanged (editor tool) |
| Help & Feedback | external link | Top bar help icon |
| OhMyLMS (new) | `page=ohmylms-app` | Becomes the single OhMyLMS menu once legacy is retired |

## Implementation notes
- Add a `group` field to `FeatureDefinition` (types.ts), and render Navigation.tsx as collapsible groups ordered by a `NAV_GROUPS` constant.
- Mirror the six groups as WP submenu entries in Menu.php so the WP sidebar matches.
- Remove the duplicate nav items: Home, Taxonomies, the separate Students/Account Hub entries, and the split course editors.
