import { createElement, useState } from '@wordpress/element';
import { Button, Dropdown, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { groupQuestionTypes } from './questionTypeGroups.mjs';

/**
 * Grouped, searchable replacement for the flat question type list.
 * @param root0
 * @param root0.value    Current type ID.
 * @param root0.options  Available { value, label } types.
 * @param root0.onChange Called with the chosen type ID.
 * @param root0.disabled Whether the type is locked.
 */
export function QuestionTypeMenu( { value, options, onChange, disabled } ) {
	const [ search, setSearch ] = useState( '' );
	const current = options.find( ( option ) => option.value === value );
	const query = search.toLowerCase().trim();
	const groups = groupQuestionTypes( options )
		.map( ( [ heading, items ] ) => [
			heading,
			items.filter( ( item ) =>
				item.label.toLowerCase().includes( query )
			),
		] )
		.filter( ( [ , items ] ) => items.length );
	return (
		<Dropdown
			className="ohmylms-type-menu"
			popoverProps={ { placement: 'bottom-end' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					variant="secondary"
					className="ohmylms-type-menu-toggle"
					onClick={ () => {
						setSearch( '' );
						onToggle();
					} }
					aria-expanded={ isOpen }
					aria-label={ __( 'Question type', 'ohmylms' ) }
					disabled={ disabled }
					icon="arrow-down-alt2"
					iconPosition="right"
				>
					{ current?.label || value }
				</Button>
			) }
			renderContent={ ( { onClose } ) => (
				<div className="ohmylms-type-menu-panel">
					<TextControl
						label={ __( 'Search question types', 'ohmylms' ) }
						hideLabelFromVision
						placeholder={ __( 'Search question types', 'ohmylms' ) }
						value={ search }
						onChange={ setSearch }
						__nextHasNoMarginBottom
					/>
					{ groups.map( ( [ heading, items ] ) => (
						<section key={ heading } aria-label={ heading }>
							<h3>{ heading }</h3>
							<div className="ohmylms-type-menu-grid">
								{ items.map( ( item ) => (
									<Button
										key={ item.value }
										variant={
											item.value === value
												? 'primary'
												: 'tertiary'
										}
										onClick={ () => {
											onClose();
											setSearch( '' );
											if ( item.value !== value ) {
												onChange( item.value );
											}
										} }
									>
										{ item.label }
									</Button>
								) ) }
							</div>
						</section>
					) ) }
					{ ! groups.length && (
						<p role="status">
							{ __( 'No question types match.', 'ohmylms' ) }
						</p>
					) }
				</div>
			) }
		/>
	);
}
