/** Versioned, framework-independent registry for trusted plugin modules. */
export const API_VERSION = 1;
export const KINDS = Object.freeze( [
	'admin-page',
	'editor-panel',
	'question-editor',
	'lesson-editor',
	'slash-command',
	'slot',
	'membership-settings',
	'checkout-field',
	'integration',
] );
export function createRegistry() {
	const entries = new Map( KINDS.map( ( kind ) => [ kind, new Map() ] ) );
	const listeners = new Set();
	let revision = 0;
	const notify = () => {
		revision++;
		for ( const listener of listeners ) {
			listener();
		}
	};
	function register( kind, id, definition ) {
		if ( ! entries.has( kind ) ) {
			throw new TypeError( `Unknown extension category: ${ kind }` );
		}
		if ( ! /^[a-z][a-z0-9_-]*$/.test( id ) ) {
			throw new TypeError( 'Extension IDs must be lowercase slugs.' );
		}
		if ( entries.get( kind ).has( id ) ) {
			throw new Error( `Duplicate extension: ${ kind }/${ id }` );
		}
		// Slash-menu commands run an editor action instead of rendering a component.
		const behavior = kind === 'slash-command' ? 'action' : 'render';
		if (
			! definition ||
			typeof definition.label !== 'string' ||
			! definition.label.trim() ||
			typeof definition[ behavior ] !== 'function'
		) {
			throw new TypeError(
				`Extensions require a label and ${ behavior } ${ behavior === 'action' ? 'function' : 'component' }.`
			);
		}
		if (
			definition.apiVersion !== undefined &&
			definition.apiVersion !== API_VERSION
		) {
			throw new Error( 'Unsupported extension API version.' );
		}
		if (
			definition.priority !== undefined &&
			! Number.isFinite( definition.priority )
		) {
			throw new TypeError( 'priority must be finite.' );
		}
		const entry = Object.freeze( {
			...definition,
			id,
			kind,
			priority: definition.priority ?? 10,
			enabled: definition.enabled !== false,
		} );
		entries.get( kind ).set( id, entry );
		notify();
		return () => {
			if ( entries.get( kind ).get( id ) === entry ) {
				entries.get( kind ).delete( id );
				notify();
			}
		};
	}
	const registry = {
		version: API_VERSION,
		register,
		list: ( kind ) =>
			[ ...( entries.get( kind )?.values() || [] ) ]
				.filter( ( e ) => e.enabled )
				.sort(
					( a, b ) =>
						a.priority - b.priority ||
						( () => {
							if ( a.id < b.id ) {
								return -1;
							}
							if ( a.id > b.id ) {
								return 1;
							}
							return 0;
						} )()
				),
		get: ( kind, id ) => entries.get( kind )?.get( id ),
		subscribe( listener ) {
			listeners.add( listener );
			return () => listeners.delete( listener );
		},
		getRevision: () => revision,
	};
	for ( const [ method, kind ] of Object.entries( {
		registerAdminPage: 'admin-page',
		registerEditorPanel: 'editor-panel',
		registerQuestionEditor: 'question-editor',
		registerLessonEditor: 'lesson-editor',
		registerSlashCommand: 'slash-command',
		registerSlot: 'slot',
		registerMembershipSettings: 'membership-settings',
		registerCheckoutField: 'checkout-field',
		registerIntegration: 'integration',
	} ) ) {
		registry[ method ] = ( id, definition ) =>
			register( kind, id, definition );
	}
	return Object.freeze( registry );
}
