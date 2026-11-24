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
| H5 | Home lists two courses, My learning lists one | OPEN (check how each list decides enrolment) |
| H6 | Each section shows its title in the top bar and again in a banner (History / History) | OPEN |
| H7 | My skills uses small native form controls and no banner, unlike the other sections | OPEN |
| H8 | The My classes page has the browser title "Assignments – My classes" | OPEN |
| H9 | "Join a class" is a tiny link beside a large button | OPEN |
| H10 | Completed courses on Achievements are plain rows with no date, link or status | OPEN |
| H11 | Phone width: the role switcher (Learner, Teacher, Parent, School) and the tab strip are clipped at the right edge with no cue that they scroll | OPEN |
| H12 | An unknown `hub_section` value silently shows Home | OPEN (low) |

Dark mode on Home was checked and looks consistent.
