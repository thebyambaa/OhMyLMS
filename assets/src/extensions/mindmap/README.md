# Shared mindmap

`Mindmap.jsx` is a reusable tree browser and controlled selection editor. It belongs to the extension UI layer, not to memberships. Use it for curriculum hierarchies, skill trees and other parent/child data. Prerequisite networks with multiple parents require a separate graph model; this component renders forests.

Pass flat `items` with stable `id`, `parent` and `label` fields. IDs can be strings or numbers. `buildMindmapTree` retains orphaned records and safely breaks malformed cycles without mutating caller data.

Props:

- `selected`, `onSelectionChange(ids)`: controlled selection. Omit the callback for read-only browsing.
- `includeDescendants`: optional inherited selection; defaults to false. Membership curriculum selection turns it on. Skills can leave it off.
- `title`, `rootLabel`, `description`, `emptyMessage`: domain-specific labels.
- `nodeHint(node, state)`: optional hint; state contains `explicit`, `inherited` and `included`.
- `renderNodeDetails(node, state)`: optional slot for course lists, skill metadata, editing controls or actions. Caller owns persistence and permissions.

Expansion is local UI state. Native buttons and labeled checkboxes support keyboard use. The mindmap scrolls horizontally and vertically inside its container. Shared styles live in `assets/css/admin-ui.css` under `.ohmylms-mindmap*`.

`features/memberships/CurriculumMindmap.jsx` is the first adapter. It maps curriculum items to nodes, supplies item/course labels and writes to the existing plan selection. Future curriculum or skill editors should import this shared component rather than copy the adapter's layout.
