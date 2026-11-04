/** @jsx createElement */
import { createElement } from '@wordpress/element';
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import menuMetadata from '../explore-menu/block.json';

/**
 * Choose what the visitor subject browser shows; visitors see the live published subjects.
 *
 * @param {Object}                props               Block editor properties.
 * @param {Object}                props.attributes    Saved subject ID, zero for every subject.
 * @param {function(Object):void} props.setAttributes Update saved attributes.
 * @return {Element} Subject choice.
 */
export function BrowseSubjectsEdit( { attributes, setAttributes } ) {
	const subjects = window.ohmylmsBrowseSubjects?.subjects || [];
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<Placeholder
				icon="category"
				label={ __( 'Browse subjects', 'ohmylms' ) }
				instructions={ __(
					'Visitors see published subjects and their courses here, with a sign up button on each course.',
					'ohmylms'
				) }
			>
				<SelectControl
					label={ __( 'Show', 'ohmylms' ) }
					value={ String( attributes.subjectId ) }
					options={ [
						{ value: '0', label: __( 'All subjects', 'ohmylms' ) },
						...subjects.map( ( subject ) => ( {
							value: String( subject.id ),
							label: subject.name,
						} ) ),
					] }
					onChange={ ( value ) =>
						setAttributes( { subjectId: Number( value ) || 0 } )
					}
				/>
				<SelectControl
					label={ __( 'Layout', 'ohmylms' ) }
					value={ attributes.display }
					options={ [
						{
							value: 'panel',
							label: __(
								'Explore panel (all columns open)',
								'ohmylms'
							),
						},
						{
							value: 'dropdown',
							label: __( 'Explore dropdown button', 'ohmylms' ),
						},
					] }
					onChange={ ( value ) =>
						setAttributes( { display: value } )
					}
				/>
			</Placeholder>
		</div>
	);
}

/**
 * The header Explore item; visitors get the full-width subject panel when they open it.
 *
 * @param {Object}                props               Block editor properties.
 * @param {Object}                props.attributes    Saved button label.
 * @param {function(Object):void} props.setAttributes Update saved attributes.
 * @return {Element} Navigation item preview and label control.
 */
export function ExploreMenuEdit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( { className: 'oml-explore-item' } );
	return (
		<li { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Explore menu', 'ohmylms' ) }>
					<TextControl
						label={ __( 'Label', 'ohmylms' ) }
						value={ attributes.label }
						onChange={ ( label ) => setAttributes( { label } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<span className="oml-explore-menu-preview">
				{ attributes.label || __( 'Explore', 'ohmylms' ) }
			</span>
		</li>
	);
}

registerBlockType( metadata, { edit: BrowseSubjectsEdit, save: () => null } );
registerBlockType( menuMetadata, { edit: ExploreMenuEdit, save: () => null } );
