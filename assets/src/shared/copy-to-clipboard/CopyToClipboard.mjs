import { __ } from '@wordpress/i18n';
import {
	Fragment,
	createElement,
	memo,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { RESET_DELAY, copyText } from './model.mjs';

const buttonStyle = {
	position: 'relative',
	background: 'none',
	border: 'none',
	boxShadow: 'none',
	cursor: 'pointer',
	gap: '6px',
	padding: 0,
	color: '#6E42D3',
};

const iconStyle = { display: 'block' };

const tipStyle = {
	position: 'absolute',
	bottom: 'calc(100% + 10px)',
	left: '50%',
	transform: 'translateX(-50%)',
	backgroundColor: 'rgba(0, 0, 0, 0.85)',
	color: '#ffffff',
	fontSize: '12px',
	padding: '4px 12px',
	borderRadius: '50px',
	whiteSpace: 'nowrap',
	fontWeight: '400',
	zIndex: 10,
};

const iconPaths = [
	'M5.25 6.287h.775v1.55H5.25a.974.974 0 00-.975.976v8.75c0 .538.437.974.975.974h7a.974.974 0 00.975-.974v-.776h1.55v.776a2.526 2.526 0 01-2.525 2.525h-7a2.526 2.526 0 01-2.525-2.526v-8.75A2.526 2.526 0 015.25 6.287z',
	'M8.75 2.787h5.038l.204.009h.009c.591.051 1.15.308 1.572.731l1.962 1.962c.423.423.68.981.73 1.573l.002.008c.005.068.008.136.008.204v6.788a2.526 2.526 0 01-2.525 2.526h-7a2.526 2.526 0 01-2.525-2.526v-8.75A2.526 2.526 0 018.75 2.788zm5.35 1.599l-.076-.02a.975.975 0 00-.236-.028H8.75a.974.974 0 00-.975.974v8.75c0 .539.437.975.975.975h7a.974.974 0 00.975-.975V7.274c0-.08-.01-.16-.029-.236l-.02-.075h-.926a1.65 1.65 0 01-1.65-1.65v-.927z',
];

/**
 * Icon button that copies `textToCopy` and briefly shows a "Copied!" tip.
 *
 * @param {Object} props            Component props.
 * @param {string} props.textToCopy Text placed on the clipboard.
 * @return {import('react').ReactElement} Button.
 */
function CopyToClipboardButton( { textToCopy } ) {
	const [ copied, setCopied ] = useState( false );
	const timer = useRef( null );

	useEffect( () => () => clearTimeout( timer.current ), [] );

	const onClick = useCallback( async () => {
		try {
			await copyText( textToCopy, {
				navigator: window.navigator,
				window,
				document: window.document,
			} );
			setCopied( true );
			timer.current = setTimeout( () => setCopied( false ), RESET_DELAY );
		} catch ( error ) {
			console.error( error );
		}
	}, [ textToCopy ] );

	return createElement(
		Fragment,
		null,
		createElement(
			'button',
			{
				onClick,
				title: 'Copy',
				className: 'ohmylms-copy-to-clipboard',
				style: buttonStyle,
			},
			createElement(
				'svg',
				{
					style: iconStyle,
					width: '21',
					height: '22',
					fill: 'none',
					viewBox: '0 0 21 22',
					xmlns: 'http://www.w3.org/2000/svg',
				},
				iconPaths.map( ( d ) =>
					createElement( 'path', {
						key: d,
						fill: '#7A8B9A',
						stroke: '#fff',
						'stroke-width': '.2',
						d,
					} )
				)
			),
			copied &&
				createElement(
					'div',
					{ style: tipStyle },
					__( 'Copied!', 'ohmylms' )
				)
		)
	);
}

export const CopyToClipboard = memo( CopyToClipboardButton );
