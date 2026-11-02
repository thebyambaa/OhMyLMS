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
- Every command is a legacy hash route. `Commands::all()` sends it through `LegacyRoutes::target()`
  ([legacy routes](LEGACY-ROUTES.md)), so each command also carries the `page` and `tab` of its new screen, the app
  `route` and its address `url` (unmapped routes open the Dashboard). The palette opens `url`; when the command is on
  the current page and tab it sets `#<route>` instead, which the app's router opens without a reload. A command that
  sets its own `page` keeps it and opens `admin.php?page=<page>#<route>`.
- An action is a route with `?action=`: `/content-hub/exams?action=new` opens
  `admin.php?page=ohmylms-content&tab=exams&action=new`, the Exams tab with the New exam dialog open. The screen
  reads the parameter from its route and removes it once handled.

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
