/** @jsx createElement */
import { createElement, useEffect, useState } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	Notice,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

/**
 * Select a quiz by metadata only; editor previews never create learner attempts.
 *
 * @param {Object}                props               Block editor properties.
 * @param {Object}                props.attributes    Saved quiz ID and frame height.
 * @param {function(Object):void} props.setAttributes Update saved attributes.
 * @return {Element} Quiz selector and optional author preview.
 */
export function QuizPlayerEdit( { attributes, setAttributes } ) {
	const { quizId } = attributes;
	const [ search, setSearch ] = useState( '' );
	const [ choices, setChoices ] = useState( [] );
	const [ selected, setSelected ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const blockProps = useBlockProps();
	useEffect( () => {
		let active = true;
		const timer = setTimeout( () => {
			setLoading( true );
			apiFetch( {
				path: `/ohmylms/v1/quiz-player/quizzes?search=${ encodeURIComponent( search ) }`,
			} )
				.then( ( items ) => {
					if ( active ) {
						setChoices( items );
						setError( '' );
					}
				} )
				.catch( ( failure ) => active && setError( failure.message ) )
				.finally( () => active && setLoading( false ) );
		}, 250 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ search ] );
	useEffect( () => {
		let active = true;
		setSelected( null );
		if ( ! quizId ) {
			return undefined;
		}
		apiFetch( {
			path: `/ohmylms/v1/quiz-player/quizzes?include=${ Number( quizId ) }`,
		} )
			.then( ( items ) => active && setSelected( items[ 0 ] || null ) )
			.catch( ( failure ) => active && setError( failure.message ) );
		return () => {
			active = false;
		};
	}, [ quizId ] );
	const items =
		selected && ! choices.some( ( item ) => item.id === selected.id )
			? [ selected, ...choices ]
			: choices;
	return (
		<div { ...blockProps }>
			<Placeholder
				icon="welcome-learn-more"
				label={ __( 'OhMyLMS Quiz Player', 'ohmylms' ) }
				instructions={ __(
					'Choose an existing quiz to play here. Students use its existing access and attempt settings.',
					'ohmylms'
				) }
			>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				<ComboboxControl
					label={ __( 'Quiz', 'ohmylms' ) }
					value={ quizId ? String( quizId ) : null }
					options={ items.map( ( item ) => ( {
						value: String( item.id ),
						label: item.title,
					} ) ) }
					onFilterValueChange={ setSearch }
					onChange={ ( value ) =>
						setAttributes( { quizId: Number( value ) || 0 } )
					}
				/>
				{ loading && <Spinner /> }
				{ ! loading && ! items.length && (
					<p>{ __( 'No quizzes found.', 'ohmylms' ) }</p>
				) }
				{ selected && selected.status !== 'publish' && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Publish this quiz before students can play it.',
							'ohmylms'
						) }
					</Notice>
				) }
				{ selected?.previewUrl && (
					<Button
						variant="secondary"
						href={ selected.previewUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Open instructor preview', 'ohmylms' ) }
					</Button>
				) }
			</Placeholder>
		</div>
	);
}

registerBlockType( metadata, { edit: QuizPlayerEdit, save: () => null } );
