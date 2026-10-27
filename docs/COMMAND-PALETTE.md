# Command palette (Ctrl+K)

OhMyLMS adds its screens and actions to the WordPress command palette. Press **Ctrl+K** (**Cmd+K** on Mac) on any
wp-admin screen and type, for example, `new exam`, `import questions`, `go to quizzes` or `payments settings`.

| Kind | Commands |
| --- | --- |
| Go to | Dashboard, Content Hub and each of its tabs, Membership, Account Hub, Settings and the main settings pages |
| Action | New quiz, Import quiz, New exam, Import exam, New question, Import questions, New skill |

Only users who can manage OhMyLMS see them.

## How it works

- `includes/Admin/Commands.php` builds the list and loads `assets/js/commands.js`, which registers each command with
  the `core/commands` store. There is no build step for the script.
- Every command is a hash route of the admin app. On the OhMyLMS screen the palette changes the hash; elsewhere it
  opens `admin.php?page=ohmylms#<route>`.
- An action is a route with `?action=`: `#/content-hub/exams?action=new` opens the Exams tab with the New exam
  dialog open. Screens handle it with `useHubAction( { new: ..., import: ... } )`
  (`assets/src/features/content-hub/useHubAction.js`), which removes the parameter once handled.

## Add a command

```php
add_filter( 'ohmylms_admin_commands', function ( $commands ) {
	$commands[] = array(
		'name'     => 'my-addon/new-thing',
		'label'    => 'OhMyLMS: New thing',
		'route'    => '/content-hub/courses?action=new-thing',
		'keywords' => array( 'create' ),
		'kind'     => 'action',
	);
	return $commands;
} );
```

The screen that owns the route calls `useHubAction( { 'new-thing': open } )`.
