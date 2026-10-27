/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, TextControl } from '@wordpress/components';
import {
	UNIT_NUMBER_MAX,
	saveUnitNumber,
	unitLabel,
	unitNumberChanges,
} from './unitNumbers.mjs';

/**
 * Display number of each unit. Units are numbered by position unless an author types a label such
 * as 3 or 2A. Saved on its own, apart from the learning program.
 * @param {Object}                    root0
 * @param {number}                    root0.courseId Course being edited.
 * @param {Object[]}                  root0.chapters Units: [{id, name, number}].
 * @param {(units: Object[]) => void} root0.onSaved  Called with the saved changes [{id, number}].
 */
export function UnitNumbers( { courseId, chapters, onSaved } ) {
	const [ edits, setEdits ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );
	const { changes, invalid } = unitNumberChanges( chapters, edits );

	async function save() {
		setBusy( true );
		setError( '' );
		setNotice( '' );
		const saved = [];
		try {
			for ( const change of changes ) {
				await saveUnitNumber( courseId, change.id, change.number );
				saved.push( change );
			}
			setNotice( __( 'Unit numbers saved.', 'ohmylms' ) );
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not save unit numbers.', 'ohmylms' )
			);
		} finally {
			if ( saved.length ) {
				onSaved( saved );
				setEdits( ( value ) => {
					const rest = { ...value };
					saved.forEach( ( change ) => delete rest[ change.id ] );
					return rest;
				} );
			}
			setBusy( false );
		}
	}

	if ( ! chapters.length ) {
		return null;
	}
	return (
		<section className="ohmylms-unit-numbers" aria-busy={ busy }>
			<div className="ohmylms-learning-editor-toolbar">
				<h3>{ __( 'Unit numbers', 'ohmylms' ) }</h3>
				<Button
					variant="secondary"
					disabled={ busy || ! changes.length || invalid.length > 0 }
					onClick={ save }
				>
					{ __( 'Save unit numbers', 'ohmylms' ) }
				</Button>
			</div>
			<p className="description">
				{ __(
					'Units are numbered 1, 2, 3 in course order. Type a number or label, such as 3 or 2A, to show it instead. Leave it empty for the automatic number.',
					'ohmylms'
				) }
			</p>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( '' ) }>
					{ notice }
				</Notice>
			) }
			<ul className="ohmylms-unit-numbers-list">
				{ chapters.map( ( chapter, index ) => {
					const typed = Object.prototype.hasOwnProperty.call(
						edits,
						chapter.id
					)
						? edits[ chapter.id ]
						: chapter.number || '';
					const bad = invalid.includes( chapter.id );
					return (
						<li key={ chapter.id }>
							<TextControl
								label={ sprintf(
									/* translators: %s: unit name. */
									__( 'Unit number for %s', 'ohmylms' ),
									chapter.name
								) }
								value={ typed }
								maxLength={ UNIT_NUMBER_MAX }
								placeholder={ unitLabel( '', index ) }
								help={
									bad
										? __(
												'Use up to 10 letters, digits, dots or dashes.',
												'ohmylms'
											)
										: undefined
								}
								disabled={ busy }
								onChange={ ( value ) =>
									setEdits( ( current ) => ( {
										...current,
										[ chapter.id ]: value,
									} ) )
								}
							/>
						</li>
					);
				} ) }
			</ul>
		</section>
	);
}
