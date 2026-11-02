/**
 * Registers the OhMyLMS commands with the WordPress command palette (Ctrl+K / Cmd+K).
 * The list comes from includes/Admin/Commands.php as window.ohmylmsCommands.
 */
( function ( wp, config ) {
	'use strict';
	if ( ! config || ! wp.data || ! wp.commands ) {
		return;
	}
	var dispatch = wp.data.dispatch( wp.commands.store );
	var base =
		window.location.pathname.replace( /[^/]*$/, '' ) + 'admin.php?page=';

	// A command opens its address. On the same OhMyLMS page and tab the app's router opens the route
	// from the hash without a reload; add-on commands with their own page get `#<route>` on it.
	function open( command, close ) {
		var query = new URLSearchParams( window.location.search );
		var here =
			window.location.pathname.slice( -9 ) === 'admin.php' &&
			query.get( 'page' ) === command.page;
		if ( ! command.url ) {
			if ( here ) {
				window.location.hash = '#' + command.route;
			} else {
				window.location.assign(
					base + command.page + '#' + command.route
				);
			}
		} else if (
			here &&
			( query.get( 'tab' ) || '' ) === ( command.tab || '' ) &&
			document.getElementById( 'ohmylms-app-root' )
		) {
			window.location.hash = '#' + command.route;
		} else {
			window.location.assign( command.url );
		}
		close();
	}

	config.commands.forEach( function ( command ) {
		dispatch.registerCommand( {
			name: command.name,
			label: command.label,
			searchLabel: [ command.label ]
				.concat( command.keywords || [] )
				.join( ' ' ),
			callback: function ( args ) {
				open(
					Object.assign( { page: config.page }, command ),
					args.close
				);
			},
		} );
	} );
} )( window.wp || {}, window.ohmylmsCommands );
