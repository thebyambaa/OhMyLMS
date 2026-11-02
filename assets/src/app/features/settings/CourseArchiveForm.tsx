import { Button, CheckboxControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	ARCHIVE_FILTERS,
	LAYOUT_STYLES,
	MAX_ROWS,
	ROW_CRITERIA,
	criteriaLabel,
	filterLabel,
	getArchive,
	isDirty,
	saveArchive,
	validateArchive,
	type ArchiveValues,
	type RowCriteria,
} from './designSettings';
import { NumberField, SelectField, TextField, ToggleField } from './fields';
import { FormFrame } from './FormFrame';
import { useResource } from './useResource';

type Toggle = 'show_filters' | 'show_sorting' | 'show_search' | 'show_category';

/**
 * Course archive tab (`/settings/course-archive`): layout, columns, filters and carousel rows of the
 * course archive and course list widgets.
 *
 * @param props         Component props.
 * @param props.active  Whether the tab is selected (loads on first selection).
 * @param props.onDirty Reports unsaved edits.
 */
export function CourseArchiveForm( {
	active,
	onDirty,
}: {
	active: boolean;
	onDirty: ( dirty: boolean ) => void;
} ) {
	const state = useResource< ArchiveValues, ArchiveValues >(
		{
			load: async ( signal ) => ( await getArchive( signal ) ).values,
			toDraft: ( stored ) => structuredClone( stored ),
			isDirty,
			validate: validateArchive,
			save: async ( stored, draft, signal ) => {
				const result = await saveArchive( stored, draft, signal );
				return { snapshot: result.values, changed: result.changed };
			},
		},
		active
	);
	useEffect( () => onDirty( state.dirty ), [ state.dirty, onDirty ] );
	const draft = state.draft;
	const set = < K extends keyof ArchiveValues >(
		key: K,
		value: ArchiveValues[ K ]
	) => state.update( ( current ) => ( { ...current, [ key ]: value } ), key );
	const toggles: [ Toggle, string ][] = [
		[ 'show_filters', __( 'Show the filter panel', 'ohmylms' ) ],
		[ 'show_search', __( 'Show the search box', 'ohmylms' ) ],
		[ 'show_sorting', __( 'Show sorting', 'ohmylms' ) ],
		[ 'show_category', __( 'Show category links', 'ohmylms' ) ],
	];

	return (
		<FormFrame
			title={ __( 'Course archive', 'ohmylms' ) }
			description={ __(
				'Layout of the course archive page and the default look of course list blocks and widgets.',
				'ohmylms'
			) }
			state={ state }
		>
			{ draft && (
				<>
					<SelectField
						label={ __( 'Layout', 'ohmylms' ) }
						value={ draft.layout }
						options={ [
							{ value: 'grid', label: __( 'Grid', 'ohmylms' ) },
							{ value: 'list', label: __( 'List', 'ohmylms' ) },
						] }
						onChange={ ( value ) =>
							set( 'layout', value as ArchiveValues[ 'layout' ] )
						}
					/>
					{ 'grid' === draft.layout && (
						<SelectField
							label={ __( 'Card style', 'ohmylms' ) }
							value={ draft.layout_style }
							options={ LAYOUT_STYLES.map( ( style, index ) => ( {
								value: style,
								label: sprintf(
									/* translators: %d: style number. */
									__( 'Style %d', 'ohmylms' ),
									index + 1
								),
							} ) ) }
							onChange={ ( value ) =>
								set(
									'layout_style',
									value as ArchiveValues[ 'layout_style' ]
								)
							}
						/>
					) }
					<NumberField
						label={ __( 'Courses per page', 'ohmylms' ) }
						value={ draft.courses_per_page }
						min={ 1 }
						max={ 100 }
						step={ 1 }
						error={ state.errors.courses_per_page }
						onChange={ ( value ) =>
							set( 'courses_per_page', value )
						}
					/>
					<NumberField
						label={ __( 'Columns per row', 'ohmylms' ) }
						value={ draft.columns_per_row }
						min={ 1 }
						max={ 6 }
						step={ 1 }
						error={ state.errors.columns_per_row }
						onChange={ ( value ) =>
							set( 'columns_per_row', value )
						}
					/>
					{ toggles.map( ( [ key, label ] ) => (
						<ToggleField
							key={ key }
							label={ label }
							checked={ draft[ key ] }
							onChange={ ( value ) => set( key, value ) }
						/>
					) ) }
					{ draft.show_filters && (
						<fieldset>
							<legend>{ __( 'Filters', 'ohmylms' ) }</legend>
							{ ARCHIVE_FILTERS.map( ( filter ) => (
								<CheckboxControl
									key={ filter }
									__nextHasNoMarginBottom
									label={ filterLabel( filter ) }
									checked={ draft.filters.includes( filter ) }
									onChange={ ( on ) =>
										set(
											'filters',
											ARCHIVE_FILTERS.filter( ( one ) =>
												one === filter
													? on
													: draft.filters.includes(
															one
														)
											)
										)
									}
								/>
							) ) }
							{ state.errors.filters && (
								<p role="alert">{ state.errors.filters }</p>
							) }
						</fieldset>
					) }
					<fieldset>
						<legend>{ __( 'Carousel rows', 'ohmylms' ) }</legend>
						<p>
							{ __(
								'Rows of the course carousel and popup layouts, each listing courses chosen by a fixed rule.',
								'ohmylms'
							) }
						</p>
						{ draft.rows.map( ( row, index ) => (
							<div
								key={ index }
								role="group"
								aria-label={ sprintf(
									/* translators: %d: row number. */
									__( 'Row %d', 'ohmylms' ),
									index + 1
								) }
							>
								<TextField
									label={ __( 'Heading', 'ohmylms' ) }
									value={ row.heading }
									maxLength={ 100 }
									error={ state.errors[ `rows.${ index }` ] }
									onChange={ ( heading ) =>
										set(
											'rows',
											draft.rows.map( ( one, at ) =>
												at === index
													? { ...one, heading }
													: one
											)
										)
									}
								/>
								<SelectField
									label={ __( 'Courses', 'ohmylms' ) }
									value={ row.criteria }
									options={ ROW_CRITERIA.map(
										( criteria ) => ( {
											value: criteria,
											label: criteriaLabel( criteria ),
										} )
									) }
									onChange={ ( criteria ) =>
										set(
											'rows',
											draft.rows.map( ( one, at ) =>
												at === index
													? {
															...one,
															criteria:
																criteria as RowCriteria,
														}
													: one
											)
										)
									}
								/>
								<Button
									variant="link"
									isDestructive
									onClick={ () =>
										set(
											'rows',
											draft.rows.filter(
												( _, at ) => at !== index
											)
										)
									}
								>
									{ __( 'Remove row', 'ohmylms' ) }
								</Button>
							</div>
						) ) }
						<Button
							variant="secondary"
							disabled={ draft.rows.length >= MAX_ROWS }
							onClick={ () =>
								set( 'rows', [
									...draft.rows,
									{
										heading: __( 'Courses', 'ohmylms' ),
										criteria: 'all',
									},
								] )
							}
						>
							{ __( 'Add row', 'ohmylms' ) }
						</Button>
					</fieldset>
				</>
			) }
		</FormFrame>
	);
}
