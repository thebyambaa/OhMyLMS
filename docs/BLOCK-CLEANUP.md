# Removed editor blocks

The Activity, Course List, My Courses and Student Assignments Gutenberg blocks have been removed at the user's request. Their registrations, editor enqueues, exclusive PHP renderers and exclusive JavaScript sources/outputs are retired. The general editor helper no longer contains Course List styling setup.

Course catalog widgets and shortcodes, the My Courses shortcode and activity registrations and shortcodes remain shared features. The My Courses and Student Assignments shortcodes (`[ohmylms_my_courses]`, `[ohmylms_student_assignments]`) are still registered, but they now only print a link to the learner hub, where learner assignments live under Classes; the front-end student assignment portal was retired (see [learner-experience/LEGACY-RETIREMENT-PLAN.md](learner-experience/LEGACY-RETIREMENT-PLAN.md)). Removing the block does not remove learner assignments.

Existing posts are not rewritten or deleted. Saved instances of these four blocks become unsupported blocks and their dynamic frontend content stops rendering. Replace those instances with the appropriate existing shortcode in a WordPress Shortcode block if their content is still needed.

The public helpers `ohmylms_page_has_course_list_block()` and `ohmylms_page_has_my_courses_block()` remain as compatibility shims returning false, so callers do not fatal after removal. The removed PHP block class/file mappings are an intentional compatibility exception: these classes implement the specifically retired blocks and are no longer loaded.

`assets/src/manifest.json` records retired generated asset paths. Both builds (including parity builds) and shipping remove those files and their maps, preventing old bundles from surviving rebuilds. Historical baseline metadata and translation catalogs remain provenance; they do not register or load blocks.
