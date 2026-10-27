# Categories and Tags React source

Edit the JSX files in this directory and run `npm run build` from the plugin root.

`CategoriesPage` and `TagsPage` cover loading, search, selection, create/edit/delete actions, bulk deletion, course counts and empty states. `TaxonomyModal` is their shared create/edit form and supports hierarchical category parents.

`components.json` records the three original factory-1841 bindings and runtime dependencies. `tools/taxonomy-adapters.mjs` replaces both anonymous route functions and the shared modal during the adapted build, failing when any binding is missing. Existing REST endpoints, shared controls and store synchronization remain unchanged.

Validate with `npm run lint`, `npm test`, `npm run build`, and the `/categories` and `/tags` browser routes.

Note: course categories and tags have been replaced by the curriculum and Course Bundles. The routes `/categories` and `/tags` now open those pages (`assets/src/extensions/index.jsx`), and the REST endpoints these components call refuse writes with HTTP 410, so the components are no longer reachable. They stay in the tree only because the recovered application build still binds them.
