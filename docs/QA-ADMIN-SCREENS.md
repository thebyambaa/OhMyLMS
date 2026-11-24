# Admin screens walkthrough

Run on 800.local, 2026-11-25, as admin at desktop width: Courses, Gradebook, Lessons & Quizzes,
Curriculum, People, Commerce, Engagement, Add-ons, Settings, Downloads and Math References.
Status: FIXED, NOT A DEFECT, or OPEN.

| # | Flaw | Status |
|---|------|--------|
| A1 | The Courses list counted lessons and quizzes from the older relationship tables, so a course built in the builder showed "0 lessons 0 quizzes" (the Sample Course, and others such as "111") | FIXED: `Course\ContentCounts` counts the published program, or the draft before the first publish, and falls back to the tables |
| A2 | Gradebook filters: the search box collapsed to zero width beside the floated filters and overflowed the page, selects had different heights, and "Edit weights" touched the sentence | FIXED: filters share one row height, the search box clears the float, the link has a gap |
| A3 | Commerce "Orders by country" showed the code "MN" | FIXED: shows the country name in the reader's language (`countryName()`) |
| A4 | Settings page pickers (search box, list, help) ran into the next field with no gap | FIXED: `oml-page-picker` fieldset spacing |
| A5 | The Gradebook opens on an arbitrary course ("111", which has nothing to grade) | OPEN: choose the most recently active course with graded work |
| A6 | People table breaks long names and emails mid-word | NOT A DEFECT: only long generated test data; wrapping is the fallback |
| A7 | Add-ons category links look like plain text | NOT A DEFECT: they use the standard admin tab style shared with the other screens |
| A8 | Courses and Commerce both repeat "Earnings" as the page heading and the chart title | OPEN (low) |
| A9 | Settings still shows "Page 2389 (not published or missing)" for the course archive page | OPEN: data on this site; the picker already explains it |
