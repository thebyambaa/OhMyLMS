# Prompt for Cursor (paste into Cursor chat in the OhMyLMS repo)

Use the files in this folder as REFERENCE and inspiration, not an exact UI blueprint.

- ohmylms-prototype/ : a clickable static prototype (open index.html in a browser). It shows ideas: a decision-focused dashboard (needs-attention queue, at-risk students, course health, mastery heatmap, insights), scope picker (district/school/class), Khan-style mastery levels, assignment score grid, and a flexible gradebook (All / Individuals only / one roster filter, saved views, transcript, skill mastery, org roll-up).
- ohmylms-redesign/PROPOSED-NAV.md and wp-native/WP-MENU-PROPOSAL.md : the agreed menu organization. Place it inside the native WordPress admin menu: one "OhMyLMS" top-level menu with 6 submenus (Dashboard, Content Hub, Account Hub, Commerce, Engagement incl. Sessions, Settings), each group page using WordPress nav tabs.
- ohmylms-redesign/CURRENT-NAV-MAP.md : how the admin is organized today.
- ohmylms-prototype/GRADEBOOK-RESEARCH.md and KHAN-COMPARISON.md : research and the recommended enrollment/roster/gradebook data model.

Goals: use WordPress's default admin design system (@wordpress/components, WP list tables, WP admin colors), fit the existing codebase, design tokens and conventions (see docs/ and AGENTS.md), keep old URLs working via redirects, and keep the .ohmylms-button class. Adapt freely where the real code or data suggests a better approach. All data in the prototype is fake.
