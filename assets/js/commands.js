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
	var base = window.location.pathname.replace( /[^/]*$/, '' ) + 'admin.php?page=' + config.page;

	function open( route, close ) {
		var onApp =
			window.location.pathname.slice( -9 ) === 'admin.php' &&
			new URLSearchParams( window.location.search ).get( 'page' ) === config.page;
		if ( onApp ) {
			// The admin app routes on the hash, so changing it opens the screen without a reload.
			window.location.hash = '#' + route;
		} else {
			window.location.assign( base + '#' + route );
		}
		close();
	}

	config.commands.forEach( function ( command ) {
		dispatch.registerCommand( {
			name: command.name,
			label: command.label,
			searchLabel: [ command.label ].concat( command.keywords || [] ).join( ' ' ),
			callback: function ( args ) {
				open( command.route, args.close );
			},
		} );
	} );
} )( window.wp || {}, window.ohmylmsCommands );
