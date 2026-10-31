export const EXTENDED_TYPES = [
	[ 'passage', 'Passage' ],
	[ 'graphing', 'Graphing' ],
	[ 'hot-text', 'Hot text' ],
	[ 'match-table-grid', 'Match table grid' ],
	[ 'labeling', 'Labeling' ],
	[ 'hotspot', 'Hotspot' ],
	[ 'draw', 'Draw' ],
	[ 'audio-response', 'Audio response' ],
	[ 'video-response', 'Video response' ],
	[ 'poll', 'Poll' ],
	[ 'word-cloud', 'Word cloud' ],
	[ 'discussion-board', 'Discussion board' ],
	[ 'interactive-video', 'Interactive video' ],
	[ 'spin-wheel', 'Spin the wheel' ],
	[ 'gameshow', 'Gameshow quiz' ],
	[ 'hangman', 'Hangman' ],
	[ 'open-box', 'Open the box' ],
	[ 'speaking-cards', 'Speaking cards' ],
	[ 'flip-tiles', 'Flip tiles' ],
	[ 'flashcards', 'Flashcards' ],
	[ 'timed-true-false', 'Timed True or false' ],
	[ 'whack-a-mole', 'Whack-a-mole' ],
	[ 'flying-fruit', 'Flying fruit' ],
];
/** Card decks: ungraded, content is a list of { text, back } cards. */
export const DECK_TYPES = [
	'spin-wheel',
	'open-box',
	'speaking-cards',
	'flip-tiles',
	'flashcards',
];
/** Timed answer-pick games share the gameshow choices and key. */
export const CHOICE_GAME_TYPES = [
	'gameshow',
	'timed-true-false',
	'whack-a-mole',
	'flying-fruit',
];
export const isExtendedType = ( type ) =>
	EXTENDED_TYPES.some( ( [ id ] ) => id === type );
/**
 * Add-on question types registered with the settings editor format keep their
 * own fields in settings and have no answer options, so the choice-question
 * checks do not apply. The server validates their settings on save.
 * @param type
 * @param manifest Question manifest from the extension REST route.
 */
export const isSettingsAddonType = (
	type,
	manifest = globalThis.window?.ohmylmsExtensionManifest?.question
) =>
	! isExtendedType( type ) &&
	manifest?.[ type ]?.editor?.format === 'ohmylms-settings';
export const isUngradedType = ( type ) =>
	[ 'poll', 'word-cloud', ...DECK_TYPES ].includes( type );
/** Types the server only accepts with zero points (`ExtendedQuestions::UNSCORED_TYPES`). */
export const UNSCORED_TYPES = Object.freeze( [
	'poll',
	'word-cloud',
	'slide',
	...DECK_TYPES,
] );

/**
 * Work out how an interactive video link should be played.
 *
 * Accepts a video file address, a YouTube, Google Drive or OneDrive link, or pasted
 * embed code. Only these hosts are ever put in an iframe. The learner player carries
 * the same rules (assets/interactivity/extended-questions.js).
 * @param  input Link or embed code.
 * @return {{provider: string, embed: string, id: string, timed: boolean}} provider is
 * file, youtube, drive, onedrive, onedrive-short or invalid. Only file and youtube can
 * report the playback time, so only they can pause at a checkpoint.
 */
export function videoSource( input ) {
	const text = String( input || '' ).trim();
	const frame = /<iframe[^>]+src\s*=\s*["']([^"']+)["']/i.exec( text );
	const raw = frame ? frame[ 1 ].replace( /&amp;/g, '&' ) : text;
	let url;
	try {
		url = new URL( raw );
	} catch {
		return { provider: 'invalid', embed: '', id: '', timed: false };
	}
	if ( ! /^https?:$/.test( url.protocol ) ) {
		return { provider: 'invalid', embed: '', id: '', timed: false };
	}
	const host = url.hostname.toLowerCase().replace( /^www\./, '' );
	const parts = url.pathname.split( '/' ).filter( Boolean );
	const youtube = ( id ) =>
		/^[\w-]{11}$/.test( id || '' )
			? {
					provider: 'youtube',
					embed: 'https://www.youtube.com/embed/' + id,
					id,
					timed: true,
				}
			: null;
	if ( host === 'youtu.be' ) {
		return (
			youtube( parts[ 0 ] ) || {
				provider: 'invalid',
				embed: '',
				id: '',
				timed: false,
			}
		);
	}
	if (
		[
			'youtube.com',
			'm.youtube.com',
			'music.youtube.com',
			'youtube-nocookie.com',
		].includes( host )
	) {
		const id = [ 'embed', 'shorts', 'live', 'v' ].includes( parts[ 0 ] )
			? parts[ 1 ]
			: url.searchParams.get( 'v' );
		return (
			youtube( id ) || {
				provider: 'invalid',
				embed: '',
				id: '',
				timed: false,
			}
		);
	}
	if ( host === 'drive.google.com' ) {
		const id =
			parts[ 0 ] === 'file' && parts[ 1 ] === 'd'
				? parts[ 2 ]
				: url.searchParams.get( 'id' );
		return /^[\w-]{10,}$/.test( id || '' )
			? {
					provider: 'drive',
					embed: 'https://drive.google.com/file/d/' + id + '/preview',
					id,
					timed: false,
				}
			: { provider: 'invalid', embed: '', id: '', timed: false };
	}
	if ( host === 'onedrive.live.com' ) {
		if (
			[ 'embed', 'redir' ].includes( parts[ 0 ] ) &&
			url.searchParams.get( 'resid' )
		) {
			return {
				provider: 'onedrive',
				embed:
					'https://onedrive.live.com/embed?' +
					url.searchParams.toString(),
				id: url.searchParams.get( 'resid' ),
				timed: false,
			};
		}
		return { provider: 'invalid', embed: '', id: '', timed: false };
	}
	if ( host.endsWith( '.sharepoint.com' ) ) {
		return url.pathname.toLowerCase().endsWith( '/embed.aspx' )
			? {
					provider: 'onedrive',
					embed: url.href,
					id: url.searchParams.get( 'UniqueId' ) || '',
					timed: false,
				}
			: { provider: 'invalid', embed: '', id: '', timed: false };
	}
	if ( host === '1drv.ms' ) {
		// A short share link cannot be embedded; OneDrive's Embed option gives the right address.
		return { provider: 'onedrive-short', embed: '', id: '', timed: false };
	}
	return { provider: 'file', embed: url.href, id: '', timed: true };
}
/**
 * Initial examples use the same settings shapes as the frozen server writer.
 * @param type
 */
export function extendedDefaults( type ) {
	switch ( type ) {
		case 'passage':
			return {
				passage:
					'A rectangle has a length of 4 cm and a width of 3 cm.',
				parts: [
					{
						id: 'a',
						label: '(a)',
						kind: 'numerical',
						marks: 1,
						prompt: 'Find the area.',
						answer: 12,
					},
				],
			};
		case 'graphing':
			return {
				mode: 'points',
				min: -5,
				max: 5,
				points: [ { x: 2, y: 3 } ],
				tolerance: 0.25,
			};
		case 'hot-text':
			return {
				tokens: [
					{ id: 't1', text: '2' },
					{ id: 't2', text: '3' },
					{ id: 't3', text: '4' },
				],
				correct: [ 't1', 't3' ],
			};
		case 'match-table-grid':
			return {
				rows: [
					{ id: 'r1', label: '2 + 2' },
					{ id: 'r2', label: '3 + 3' },
				],
				columns: [
					{ id: 'c1', label: '4' },
					{ id: 'c2', label: '6' },
				],
				key: { r1: 'c1', r2: 'c2' },
			};
		case 'labeling':
			return {
				image_url: '',
				targets: [
					{ id: 'a', x: 25, y: 50, answer: 'A' },
					{ id: 'b', x: 75, y: 50, answer: 'B' },
				],
				distractors: [],
			};
		case 'hotspot':
			return { image_url: '', zones: [ { x: 50, y: 50, radius: 12 } ] };
		case 'poll':
			return {
				choices: [
					{ id: 'c1', text: 'Yes' },
					{ id: 'c2', text: 'No' },
				],
			};
		case 'open-box':
		case 'speaking-cards':
		case 'flip-tiles':
		case 'flashcards':
		case 'spin-wheel':
			return {
				cards: [
					{ id: 'c1', text: 'Card 1' },
					{ id: 'c2', text: 'Card 2' },
					{ id: 'c3', text: 'Card 3' },
				],
			};
		case 'timed-true-false':
			return {
				choices: [
					{ id: 'c1', text: 'True' },
					{ id: 'c2', text: 'False' },
				],
				correct: 'c1',
				seconds: 15,
			};
		case 'whack-a-mole':
		case 'flying-fruit':
		case 'gameshow':
			return {
				choices: [
					{ id: 'c1', text: 'Answer 1' },
					{ id: 'c2', text: 'Answer 2' },
				],
				correct: 'c1',
				seconds: 20,
			};
		case 'hangman':
			return { accepted: [ 'word' ], max_misses: 6 };
		case 'interactive-video':
			return {
				video_url: '',
				checkpoints: [
					{ id: 'a', at: 5, prompt: 'What is 2 + 2?', answer: '4' },
				],
			};
		case 'audio-response':
			return { max_seconds: 60 };
		case 'video-response':
			return { max_seconds: 60, sources: [ 'camera', 'screen' ] };
		default:
			return {};
	}
}
/**
 * Only public configuration reaches learner widgets, including in editor preview.
 * @param type
 * @param settings
 */
export function extendedPublicView( type, settings ) {
	const keys =
		{
			passage: [ 'passage' ],
			graphing: [ 'mode', 'min', 'max' ],
			'hot-text': [ 'tokens' ],
			'match-table-grid': [ 'rows', 'columns', 'multiple' ],
			labeling: [ 'image_url' ],
			hotspot: [ 'image_url' ],
			'audio-response': [ 'max_seconds' ],
			'video-response': [ 'max_seconds', 'sources' ],
			poll: [ 'choices' ],
			'spin-wheel': [ 'cards' ],
			'open-box': [ 'cards' ],
			'speaking-cards': [ 'cards' ],
			'flip-tiles': [ 'cards' ],
			flashcards: [ 'cards' ],
			gameshow: [ 'choices', 'seconds' ],
			'timed-true-false': [ 'choices', 'seconds' ],
			'whack-a-mole': [ 'choices', 'seconds' ],
			'flying-fruit': [ 'choices', 'seconds' ],
			hangman: [ 'max_misses' ],
			'interactive-video': [ 'video_url' ],
		}[ type ] || [];
	const view = structuredClone(
		Object.fromEntries(
			keys
				.filter( ( key ) => settings[ key ] !== undefined )
				.map( ( key ) => [ key, settings[ key ] ] )
		)
	);
	if ( type === 'labeling' ) {
		view.choices = [
			...( settings.targets || [] ).map( ( target ) => target.answer ),
			...( settings.distractors || [] ),
		].sort();
		view.targets = ( settings.targets || [] ).map( ( { id, x, y } ) => ( {
			id,
			x,
			y,
		} ) );
		delete view.distractors;
	}
	if ( type === 'hangman' ) {
		view.shape = String( ( settings.accepted || [] )[ 0 ] || '' )
			.normalize( 'NFD' )
			.replace( /\p{M}/gu, '' )
			.replace( /[\p{L}\p{N}]/gu, '_' );
	}
	if ( type === 'interactive-video' ) {
		view.checkpoints = ( settings.checkpoints || [] ).map(
			( { id, at, prompt } ) => ( { id, at, prompt } )
		);
	}
	if ( type === 'passage' ) {
		view.parts = ( settings.parts || [] ).map(
			( { id, label, kind, marks, prompt } ) => ( {
				id,
				label,
				kind,
				marks,
				prompt,
			} )
		);
	}
	return view;
}
/**
 * Whether a graphing question's settings pass the server's rules, so a save is never refused for them:
 * axes inside -100 to 100 with the minimum below the maximum, one to fifty finite points inside the axes,
 * a tolerance from 0 to 1, and for a line exactly two different points.
 *
 * @param {Object} settings Graphing settings.
 * @return {boolean} True when the server will accept them.
 */
export function graphIsValid( settings = {} ) {
	const min = Number( settings.min );
	const max = Number( settings.max );
	const points = Array.isArray( settings.points ) ? settings.points : [];
	const tolerance = settings.tolerance ?? 0.25;
	const finite = ( value ) =>
		value !== '' && value !== null && Number.isFinite( Number( value ) );
	if (
		! finite( settings.min ) ||
		! finite( settings.max ) ||
		! ( min < max ) ||
		min < -100 ||
		max > 100
	) {
		return false;
	}
	if (
		! points.length ||
		points.length > 50 ||
		points.some(
			( point ) =>
				! finite( point?.x ) ||
				! finite( point?.y ) ||
				Number( point.x ) < min ||
				Number( point.x ) > max ||
				Number( point.y ) < min ||
				Number( point.y ) > max
		)
	) {
		return false;
	}
	if (
		! finite( tolerance ) ||
		Number( tolerance ) < 0 ||
		Number( tolerance ) > 1
	) {
		return false;
	}
	if ( settings.mode === 'line' ) {
		return (
			points.length === 2 &&
			( Number( points[ 0 ].x ) !== Number( points[ 1 ].x ) ||
				Number( points[ 0 ].y ) !== Number( points[ 1 ].y ) )
		);
	}
	return true;
}

/**
 * Blocking authoring problems for new extended questions.
 * @param type
 * @param settings
 */
export function extendedIssues( type, settings = {} ) {
	if (
		[ 'labeling', 'hotspot' ].includes( type ) &&
		! /^https?:\/\//.test( settings.image_url || '' )
	) {
		return [ 'image' ];
	}
	if (
		type === 'interactive-video' &&
		( ! /^https?:\/\//.test( settings.video_url || '' ) ||
			[ 'invalid', 'onedrive-short' ].includes(
				videoSource( settings.video_url ).provider
			) )
	) {
		return [ 'video' ];
	}
	if ( DECK_TYPES.includes( type ) && ( settings.cards || [] ).length < 2 ) {
		return [ 'cards' ];
	}
	if (
		CHOICE_GAME_TYPES.includes( type ) &&
		( ( settings.choices || [] ).length < 2 ||
			( settings.choices || [] ).length > 6 ||
			! ( settings.choices || [] ).some(
				( choice ) => choice.id === settings.correct
			) )
	) {
		return [ 'choices' ];
	}
	if (
		type === 'hangman' &&
		! ( settings.accepted || [] ).some( ( word ) => /\p{L}/u.test( word ) )
	) {
		return [ 'word' ];
	}
	if (
		type === 'hot-text' &&
		( ! ( settings.tokens || [] ).length ||
			! ( settings.correct || [] ).length )
	) {
		return [ 'tokens' ];
	}
	if ( type === 'graphing' && ! graphIsValid( settings ) ) {
		return [ 'graph' ];
	}
	if (
		type === 'match-table-grid' &&
		( ! ( settings.rows || [] ).length ||
			! ( settings.columns || [] ).length ||
			settings.rows.some( ( row ) => {
				const answers = settings.multiple
					? settings.key?.[ row.id ]
					: [ settings.key?.[ row.id ] ];
				return (
					! Array.isArray( answers ) ||
					! answers.length ||
					answers.some(
						( id ) =>
							! settings.columns.some(
								( column ) => column.id === id
							)
					)
				);
			} ) )
	) {
		return [ 'table' ];
	}
	return [];
}
