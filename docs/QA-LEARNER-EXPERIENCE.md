# Learner experience walkthrough

Run on 800.local, 2026-11-25, as the admin user viewing the student hub at desktop and phone
width. Status: FIXED, or OPEN (next batch).

## Student hub

| # | Flaw | Status |
|---|------|--------|
| H1 | The learner's name on the Home banner is dark grey on a dark blue gradient (nearly unreadable); the hub heading colour overrode the banner | FIXED: `profile-home.css` keeps light text in the band |
| H2 | Empty-state lines ("Badges are turned off.", "No assignments yet.") and card headings used the theme's 21px and 31px sizes next to 13px labels | FIXED: card headings 20px bold, empty text body size and muted |
| H3 | A course at 100% still had a "Continue" button; My learning says "Review course" | FIXED: Home says "Review" at 100% |
| H4 | The same "Skill levels appear when this course has skills..." sentence was repeated on every course card | FIXED: shown once per page, reworded |
| H5 | Home lists two courses, My learning lists one | FIXED: Home and Achievements now skip roadmap source courses and unpublished courses, the same rule My learning uses |
| H6 | Each section shows its title in the top bar and again in a banner (History / History) | NOT A DEFECT: the bar is page chrome and the banner carries the subtitle; revisit only as a design change |
| H7 | My skills uses small native form controls and no banner | FIXED in part: the exam planner and placement list use hub-styled controls; the missing banner is a layout choice and is left |
| H8 | The My classes page has the browser title "Assignments – My classes" | FIXED: the default view no longer names the page |
| H9 | "Join a class" is a tiny link beside a large button | FIXED: it is an outlined button (`oml-hub-button is-secondary`) |
| H10 | Completed courses on Achievements are plain rows | FIXED: each row says it is completed and links to review the course |
| H11 | Phone width: the role switcher is clipped, and the tab strip scrolls with no cue | FIXED: the pills wrap, and the tab strip shows edge fades while more tabs are hidden on that side |
| H12 | An unknown `hub_section` value silently shows Home | NOT A DEFECT: a safe fallback that only affects hand-edited addresses. A redirect would touch the routing of five role workspaces for no learner benefit. |

Dark mode on Home was checked and looks consistent.

## Phone width (375px)

The lesson page and the quiz player had no horizontal overflow and read well.

| # | Flaw | Status |
|---|------|--------|
| P4 | The typed answer box in the quiz player's paper template had no border (a faint shadow on a white box) | FIXED: 2px border at about 3:1 contrast and a clear focus ring (`quiz-player-templates.css`) |

All learner findings are now FIXED or explained.
