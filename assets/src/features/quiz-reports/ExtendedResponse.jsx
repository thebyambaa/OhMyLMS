import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
/**
 * Bounded teacher response display for recordings, drawings and additional formats.
 * @param root0
 * @param root0.type
 * @param root0.answer
 * @param root0.settings
 */
export function ExtendedResponse( { type, answer = {}, settings = {} } ) {
	const media = answer.media;
	if ( [ 'audio-response', 'video-response' ].includes( type ) ) {
		const kind = type === 'audio-response' ? 'audio' : 'video';
		const safe =
			typeof media === 'string' &&
			( /^https?:\/\/[^\s<>]+$/i.test( media ) ||
				new RegExp(
					'^data:' +
						kind +
						'/(?:webm|mp4|mpeg|ogg|wav);base64,[a-zA-Z0-9+/=]+$'
				).test( media ) );
		return safe
			? createElement( kind, {
					controls: true,
					src: media,
					style: { maxWidth: '100%' },
				} )
			: createElement(
					'p',
					null,
					__( 'No playable media response.', 'ohmylms' )
				);
	}
	if ( type === 'draw' ) {
		let points = [];
		try {
			points = JSON.parse( answer.points || '[]' );
		} catch {
			points = [];
		}
		points = Array.isArray( points )
			? points
					.filter(
						( point ) =>
							Number.isFinite( Number( point.x ) ) &&
							Number.isFinite( Number( point.y ) ) &&
							point.x >= 0 &&
							point.x <= 100 &&
							point.y >= 0 &&
							point.y <= 100
					)
					.slice( 0, 5000 )
			: [];
		const path = points
			.map(
				( point, index ) =>
					`${ index === 0 || point.start ? 'M' : 'L' }${ Number( point.x ) },${ Number( point.y ) }`
			)
			.join( ' ' );
		return createElement(
			'svg',
			{
				viewBox: '0 0 100 100',
				role: 'img',
				'aria-label': __( 'Student drawing', 'ohmylms' ),
				style: {
					maxWidth: 480,
					width: '100%',
					border: '1px solid #dcdcde',
				},
			},
			createElement( 'path', {
				d: path,
				fill: 'none',
				stroke: '#7153c6',
				strokeWidth: 0.5,
			} )
		);
	}
	if ( type === 'poll' ) {
		return createElement(
			'p',
			null,
			( settings.choices || [] ).find(
				( choice ) => choice.id === answer.choice
			)?.text || __( 'No response', 'ohmylms' )
		);
	}
	return createElement(
		'pre',
		{ style: { whiteSpace: 'pre-wrap' } },
		answer.text || JSON.stringify( answer, null, 2 )
	);
}
