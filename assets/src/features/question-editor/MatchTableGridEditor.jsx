/** @jsx createElement */
import { createElement, useId, useRef } from '@wordpress/element';
import { Button, SelectControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { RichContentControl } from '../math/RichContentControl';
import {
	addGridItem,
	gridSelectionMode,
	moveGridItem,
	removeGridItem,
	renameGridItem,
} from './matchGridModel.mjs';

/**
 * Compact row and column lists with a separate private answer key.
 *
 * @param {Object}                  root0          Component properties.
 * @param {Object}                  root0.value    Grid settings.
 * @param {(patch: Object) => void} root0.onChange Settings writer.
 * @return {Element} Grid editor.
 */
export function MatchTableGridEditor( { value, onChange } ) {
	const dragged = useRef( null );
	const controlId = useId();
	const complete = ( value.rows || [] ).filter( ( row ) =>
		value.multiple ? value.key?.[ row.id ]?.length : value.key?.[ row.id ]
	).length;
	return (
		<div className="ohmylms-grid-editor">
			<div className="ohmylms-grid-lists">
				{ [ 'rows', 'columns' ].map( ( list ) => (
					<section
						className="ohmylms-grid-list"
						key={ list }
						aria-label={
							list === 'rows'
								? __( 'Rows', 'ohmylms' )
								: __( 'Columns', 'ohmylms' )
						}
					>
						<h4>
							{ list === 'rows'
								? __( 'Rows', 'ohmylms' )
								: __( 'Columns', 'ohmylms' ) }
						</h4>
						{ ( value[ list ] || [] ).map( ( item, index ) => {
							const label =
								list === 'rows'
									? sprintf(
											/* translators: %d: row position. */ __(
												'Row %d',
												'ohmylms'
											),
											index + 1
										)
									: sprintf(
											/* translators: %d: column position. */ __(
												'Column %d',
												'ohmylms'
											),
											index + 1
										);
							return (
								<div
									className="ohmylms-grid-item"
									key={ item.id }
									onDragOver={ ( event ) =>
										event.preventDefault()
									}
									onDrop={ ( event ) => {
										event.preventDefault();
										if ( dragged.current?.list === list ) {
											onChange(
												moveGridItem(
													value,
													list,
													dragged.current.id,
													index
												)
											);
										}
										dragged.current = null;
									} }
								>
									<Button
										className="ohmylms-grid-drag"
										icon="menu"
										label={ sprintf(
											/* translators: %s: row or column label. */ __(
												'Reorder %s (Alt+Up/Down)',
												'ohmylms'
											),
											label
										) }
										draggable
										onDragStart={ () => {
											dragged.current = {
												list,
												id: item.id,
											};
										} }
										onDragEnd={ () => {
											dragged.current = null;
										} }
										onKeyDown={ ( event ) => {
											if (
												event.altKey &&
												[
													'ArrowUp',
													'ArrowDown',
												].includes( event.key )
											) {
												event.preventDefault();
												onChange(
													moveGridItem(
														value,
														list,
														item.id,
														index +
															( event.key ===
															'ArrowUp'
																? -1
																: 1 )
													)
												);
											}
										} }
									/>
									<span
										className={
											list === 'columns'
												? 'ohmylms-grid-column-marker'
												: 'ohmylms-grid-row-number'
										}
										aria-hidden="true"
									>
										{ list === 'rows'
											? `${ index + 1 }.`
											: '' }
									</span>
									<RichContentControl
										compact
										html={ false }
										label={ label }
										placeholder={ label }
										value={ item.label }
										onChange={ ( text ) =>
											onChange(
												renameGridItem(
													value,
													list,
													item.id,
													text
												)
											)
										}
									/>
									<Button
										icon="no-alt"
										label={ sprintf(
											/* translators: %s: row or column label. */ __(
												'Remove %s',
												'ohmylms'
											),
											label
										) }
										disabled={ value[ list ].length <= 1 }
										onClick={ () =>
											onChange(
												removeGridItem(
													value,
													list,
													item.id
												)
											)
										}
									/>
								</div>
							);
						} ) }
						<Button
							className="ohmylms-grid-add"
							variant="tertiary"
							onClick={ () =>
								onChange( addGridItem( value, list ) )
							}
						>
							{ list === 'rows'
								? __( 'Add row', 'ohmylms' )
								: __( 'Add column', 'ohmylms' ) }
						</Button>
					</section>
				) ) }
			</div>
			<details className="ohmylms-grid-key">
				<summary>
					{ sprintf(
						/* translators: 1: completed rows, 2: total rows. */ __(
							'Answer key (%1$d/%2$d rows)',
							'ohmylms'
						),
						complete,
						value.rows?.length || 0
					) }
				</summary>
				<ToggleControl
					label={ __( 'Multiple selections per row', 'ohmylms' ) }
					checked={ !! value.multiple }
					onChange={ ( multiple ) =>
						onChange( gridSelectionMode( value, multiple ) )
					}
					__nextHasNoMarginBottom
				/>
				<div className="ohmylms-grid-key-rows">
					{ ( value.rows || [] ).map( ( row, index ) =>
						value.multiple ? (
							<fieldset
								className="ohmylms-grid-key-row"
								key={ row.id }
							>
								<legend>
									{ row.label ||
										sprintf(
											/* translators: %d: row position. */ __(
												'Row %d',
												'ohmylms'
											),
											index + 1
										) }
								</legend>
								{ ( value.columns || [] ).map( ( column ) => (
									<label
										key={ column.id }
										htmlFor={ `${ controlId }-${ row.id }-${ column.id }` }
									>
										<input
											id={ `${ controlId }-${ row.id }-${ column.id }` }
											type="checkbox"
											checked={ (
												value.key?.[ row.id ] || []
											).includes( column.id ) }
											onChange={ ( event ) => {
												const previous =
													value.key?.[ row.id ] || [];
												onChange( {
													key: {
														...value.key,
														[ row.id ]: event.target
															.checked
															? [
																	...previous,
																	column.id,
																]
															: previous.filter(
																	( id ) =>
																		id !==
																		column.id
																),
													},
												} );
											} }
										/>
										{ column.label }
									</label>
								) ) }
							</fieldset>
						) : (
							<SelectControl
								key={ row.id }
								label={
									row.label ||
									sprintf(
										/* translators: %d: row position. */ __(
											'Row %d',
											'ohmylms'
										),
										index + 1
									)
								}
								value={ value.key?.[ row.id ] || '' }
								options={ [
									{
										value: '',
										label: __(
											'Choose correct column',
											'ohmylms'
										),
									},
									...( value.columns || [] ).map(
										( column ) => ( {
											value: column.id,
											label: column.label,
										} )
									),
								] }
								onChange={ ( answer ) =>
									onChange( {
										key: {
											...value.key,
											[ row.id ]: answer,
										},
									} )
								}
								__nextHasNoMarginBottom
							/>
						)
					) }
				</div>
			</details>
		</div>
	);
}
