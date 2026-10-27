import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, PanelBody, TextControl } from '@wordpress/components';
import { searchBank } from '../question-bank/api.mjs';
import { inlineShortcode } from './model.mjs';

/**
 * Lesson-editor helper: find an approved bank question and insert it as an inline check.
 * The retained lesson editor accepts the shortcode as text; the server renders it, so the
 * answer key never reaches the page. Inline checks never gate "Mark Complete".
 */
export function InlineCheckPanel() {
	const [ search, setSearch ] = useState( '' );
	const [ items, setItems ] = useState( [] );
	const [ copied, setCopied ] = useState( '' );
	useEffect( () => {
		let active = true;
		const timer = setTimeout( () => {
			searchBank( {
				search,
				status: 'approved',
				secure: 0,
				per_page: 10,
			} )
				.then( ( data ) => active && setItems( data.items || [] ) )
				.catch( () => active && setItems( [] ) );
		}, 300 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ search ] );
	async function copy( item ) {
		const code = inlineShortcode( item.uuid );
		try {
			await navigator.clipboard.writeText( code );
			setCopied( item.uuid );
		} catch {
			setCopied( `manual:${ item.uuid }` );
		}
	}
	return (
		<div
			className="ohmylms-inline-check-panel"
			style={ { margin: '16px 24px', background: '#fff' } }
		>
			<PanelBody
				title={ __( 'Inline question checks', 'ohmylms' ) }
				initialOpen={ false }
			>
				<p>
					{ __(
						'Copy a code and paste it into the lesson text. Learners answer in the lesson; answers count as practice evidence and never block lesson completion. Only approved, non-exam questions are listed.',
						'ohmylms'
					) }
				</p>
				<TextControl
					label={ __( 'Find a question', 'ohmylms' ) }
					value={ search }
					onChange={ setSearch }
				/>
				<ul>
					{ items.map( ( item ) => (
						<li key={ item.id } style={ { marginBottom: 8 } }>
							{ item.name }{ ' ' }
							<Button
								variant="secondary"
								size="small"
								onClick={ () => copy( item ) }
							>
								{ copied === item.uuid
									? __( 'Copied', 'ohmylms' )
									: __( 'Copy code', 'ohmylms' ) }
							</Button>
							{ copied === `manual:${ item.uuid }` && (
								<code style={ { display: 'block' } }>
									{ inlineShortcode( item.uuid ) }
								</code>
							) }
						</li>
					) ) }
				</ul>
			</PanelBody>
		</div>
	);
}
