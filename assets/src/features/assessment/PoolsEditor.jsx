import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import {
	NativeSaveGuard,
	markNativeQuizDirty,
} from '../learning/NativeSaveGuard';
import { loadPools, savePools } from './api.mjs';
import { listSkills } from '../question-bank/api.mjs';
import { flattenTree, skillTree } from '../question-bank/model.mjs';
const EMPTY = {
	title: '',
	term_id: 0,
	count: 1,
	marks: 1,
	difficulty: '',
	include_secure: true,
};

/**
 * Random question pools: each attempt draws `count` approved questions for a skill.
 * The server validates pools together and refuses shortages with an explanation.
 * @param root0
 * @param root0.quizId
 * @param root0.native
 */
export function PoolsEditor( { quizId, native = false } ) {
	const [ savedState, setSavedState ] = useState( null );
	const [ revision, setRevision ] = useState( '' );
	const [ pools, setPools ] = useState( null );
	const [ skills, setSkills ] = useState( [] );
	const [ message, setMessage ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	useEffect( () => {
		let active = true;
		Promise.all( [ loadPools( quizId ), listSkills() ] )
			.then( ( [ loaded, catalogue ] ) => {
				if ( ! active ) {
					return;
				}
				setPools( loaded.pools );
				setSavedState( JSON.stringify( loaded.pools ) );
				setRevision( loaded.revision );
				setSkills( catalogue.skills || [] );
			} )
			.catch(
				( cause ) =>
					active &&
					setMessage( {
						status: 'error',
						text: cause.message,
					} )
			);
		return () => {
			active = false;
		};
	}, [ quizId ] );
	if ( ! pools ) {
		return null;
	}
	const update = ( index, fields ) =>
		setPools(
			pools.map( ( pool, position ) =>
				position === index
					? {
							...pool,
							...fields,
						}
					: pool
			)
		);
	async function save() {
		setSaving( true );
		setMessage( null );
		try {
			let saved;
			try {
				saved = await savePools( quizId, pools, revision );
			} catch ( cause ) {
				if ( cause?.code !== 'ohmylms_quiz_questions_conflict' ) {
					throw cause;
				}
				// Question placement also changes the revision (every native save does); only a
				// change to the pools themselves since they were loaded is a real conflict.
				const fresh = await loadPools( quizId );
				if ( JSON.stringify( fresh.pools ) !== savedState ) {
					throw new Error(
						__(
							'The pools were changed elsewhere. Reload the editor to see them; nothing was saved.',
							'ohmylms'
						)
					);
				}
				saved = await savePools( quizId, pools, fresh.revision );
			}
			setPools( saved.pools );
			setRevision( saved.revision );
			if ( native ) {
				markNativeQuizDirty();
			}
			setSavedState( JSON.stringify( saved.pools ) );
			setMessage( {
				status: 'success',
				text: __(
					'Pools saved. Publish to apply them to new attempts.',
					'ohmylms'
				),
			} );
		} catch ( cause ) {
			setMessage( {
				status: 'error',
				text: cause.message || __( 'Could not save pools.', 'ohmylms' ),
			} );
		} finally {
			setSaving( false );
		}
	}
	const options = flattenTree( skillTree( skills ) );
	return (
		<fieldset disabled={ saving } className="ohmylms-pools-editor">
			{ native && (
				<NativeSaveGuard
					dirty={
						savedState !== null &&
						savedState !== JSON.stringify( pools )
					}
					busy={ saving }
				/>
			) }
			<h3>{ __( 'Random question pools', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Each attempt draws different approved questions from the bank. Drawn questions come from different families and never repeat a fixed question.',
					'ohmylms'
				) }
			</p>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			{ pools.map( ( pool, index ) => (
				<div
					key={ index }
					style={ {
						display: 'flex',
						flexWrap: 'wrap',
						gap: 8,
						alignItems: 'end',
						borderBottom: '1px solid #eee',
						paddingBottom: 8,
					} }
				>
					<TextControl
						label={ __( 'Section title', 'ohmylms' ) }
						value={ pool.title }
						onChange={ ( title ) =>
							update( index, {
								title,
							} )
						}
					/>
					<SelectControl
						label={ __( 'Skill', 'ohmylms' ) }
						value={ String( pool.term_id || '' ) }
						options={ [
							{
								value: '',
								label: __( 'Choose a skill', 'ohmylms' ),
							},
							...options.map( ( skill ) => ( {
								value: String( skill.id ),
								label: `${ '— '.repeat( skill.depth ) }${ skill.name }`,
							} ) ),
						] }
						onChange={ ( value ) =>
							update( index, {
								term_id: Number( value ),
							} )
						}
					/>
					<SelectControl
						label={ __( 'Difficulty', 'ohmylms' ) }
						value={ pool.difficulty || '' }
						options={ [
							{
								value: '',
								label: __( 'Any', 'ohmylms' ),
							},
							{
								value: 'easy',
								label: 'easy',
							},
							{
								value: 'standard',
								label: 'standard',
							},
							{
								value: 'challenge',
								label: 'challenge',
							},
						] }
						onChange={ ( difficulty ) =>
							update( index, {
								difficulty,
							} )
						}
					/>
					<TextControl
						type="number"
						min={ 1 }
						max={ 50 }
						label={ __( 'Questions', 'ohmylms' ) }
						value={ String( pool.count ) }
						onChange={ ( count ) =>
							update( index, {
								count: Number( count ),
							} )
						}
					/>
					<TextControl
						type="number"
						min={ 0 }
						step="0.5"
						label={ __( 'Marks each', 'ohmylms' ) }
						value={ String( pool.marks ) }
						onChange={ ( marks ) =>
							update( index, {
								marks: Number( marks ),
							} )
						}
					/>
					<CheckboxControl
						label={ __( 'Include exam-only questions', 'ohmylms' ) }
						checked={ pool.include_secure !== false }
						onChange={ ( _includeSecure ) =>
							update( index, {
								include_secure: _includeSecure,
							} )
						}
					/>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () =>
							setPools(
								pools.filter(
									( _, position ) => position !== index
								)
							)
						}
					>
						{ sprintf(
							/* translators: %d: pool number. */ __(
								'Remove pool %d',
								'ohmylms'
							),
							index + 1
						) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					setPools( [
						...pools,
						{
							...EMPTY,
						},
					] )
				}
			>
				{ __( 'Add pool', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button variant="primary" isBusy={ saving } onClick={ save }>
				{ __( 'Save pools', 'ohmylms' ) }
			</Button>
		</fieldset>
	);
}
