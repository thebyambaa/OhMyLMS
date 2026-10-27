import { createElement, useEffect, useState, useId } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { IconPicker } from './IconPicker';
import { useWorkspace } from './context';
function CategoryAppearance( { category, style, pending, onSave } ) {
	const _controlId = useId();
	const savedColor = style.color || '#6e42d3';
	const [ color, setColor ] = useState( savedColor );
	useEffect( () => setColor( savedColor ), [ savedColor ] );
	return (
		<div className="ohmylms-category-appearance">
			<strong>{ category }</strong>
			<IconPicker
				kind="skill"
				label={ sprintf(
					/* translators: %s: category or icon name. */ __(
						'%s category icon',
						'ohmylms'
					),
					category
				) }
				value={ style.icon || 'awards' }
				color={ color }
				disabled={ pending }
				onChange={ ( icon ) =>
					onSave( {
						...style,
						icon,
					} )
				}
			/>
			<label htmlFor={ _controlId + '-field-1' }>
				<span>{ __( 'Icon color', 'ohmylms' ) }</span>
				<input
					type="color"
					value={ color }
					disabled={ pending }
					onChange={ ( event ) => setColor( event.target.value ) }
					aria-label={ sprintf(
						/* translators: %s: category or icon name. */ __(
							'%s icon color',
							'ohmylms'
						),
						category
					) }
					id={ _controlId + '-field-1' }
				/>
			</label>
			<Button
				variant="secondary"
				disabled={ pending || color === savedColor }
				onClick={ () =>
					onSave( {
						...style,
						color,
					} )
				}
			>
				{ __( 'Save color', 'ohmylms' ) }
			</Button>
		</div>
	);
}

/** Categories are created at syllabus level and selected, rather than typed, on each skill. */
export function SkillCategories() {
	const w = useWorkspace();
	const categories = w.outline.settings.categories || [];
	const [ text, setText ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const add = async () => {
		const category = text.trim().replace( /\s+/g, ' ' );
		if ( ! category ) {
			return;
		}
		if ( category.length > 60 || categories.length >= 50 ) {
			setError(
				__(
					'Use up to 50 categories, each with at most 60 characters.',
					'ohmylms'
				)
			);
			return;
		}
		if ( categories.includes( category ) ) {
			setText( '' );
			setError( '' );
			return;
		}
		if (
			await w.saveSettings( {
				categories: [ ...categories, category ],
			} )
		) {
			setText( '' );
			setError( '' );
		}
	};
	return (
		<div className="ohmylms-ws-field ohmylms-skill-categories">
			<label
				className="ohmylms-ws-label"
				htmlFor={ `syllabus-categories-${ w.syllabusId }` }
			>
				{ __( 'Skill categories', 'ohmylms' ) }
			</label>
			<div className="ohmylms-category-tags">
				{ categories.map( ( category ) => (
					<span className="ohmylms-category-token" key={ category }>
						{ category }
						<button
							type="button"
							disabled={ w.pending }
							aria-label={ sprintf(
								/* translators: %s: category or icon name. */ __(
									'Remove %s category',
									'ohmylms'
								),
								category
							) }
							onClick={ () =>
								w.saveSettings( {
									categories: categories.filter(
										( value ) => value !== category
									),
								} )
							}
						>
							<span aria-hidden="true">×</span>
						</button>
					</span>
				) ) }
				<input
					id={ `syllabus-categories-${ w.syllabusId }` }
					value={ text }
					disabled={ w.pending }
					placeholder={ __( 'Type a category…', 'ohmylms' ) }
					aria-describedby={ `syllabus-categories-help-${ w.syllabusId }` }
					aria-invalid={ Boolean( error ) }
					onChange={ ( event ) => {
						setText( event.target.value );
						setError( '' );
					} }
					onKeyDown={ ( event ) => {
						if (
							event.key === 'Enter' &&
							! event.nativeEvent.isComposing
						) {
							event.preventDefault();
							add();
						}
					} }
				/>
				<button
					className="ohmylms-category-add"
					type="button"
					disabled={ w.pending || ! text.trim() }
					onClick={ add }
				>
					{ __( 'Add', 'ohmylms' ) }
				</button>
			</div>
			{ categories.map( ( category ) => (
				<CategoryAppearance
					key={ category }
					category={ category }
					style={
						w.outline.settings.category_styles?.[ category ] || {}
					}
					pending={ w.pending }
					onSave={ ( style ) =>
						w.saveSettings( {
							category_styles: {
								...w.outline.settings.category_styles,
								[ category ]: style,
							},
						} )
					}
				/>
			) ) }
			<span
				className={ `ohmylms-ws-note${ error ? ' is-error' : '' }` }
				id={ `syllabus-categories-help-${ w.syllabusId }` }
				role="status"
			>
				{ error ||
					__(
						'Press Enter to create a category. Skills can only choose from these categories. Remove unused categories with ×.',
						'ohmylms'
					) }
			</span>
		</div>
	);
}
