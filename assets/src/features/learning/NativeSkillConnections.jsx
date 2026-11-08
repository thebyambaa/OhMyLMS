import { createElement, useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { loadContentSkills, saveContentSkills } from '../curriculum/public.mjs';
import { SkillField } from './SkillField';

/**
 * @typedef {Object} LinkedSkill
 * @property {number} id     Skill term ID.
 * @property {string} name   Skill name.
 * @property {string} [code] Skill code.
 */

/**
 * @typedef {Object} Links
 * @property {{kind: string, id: number}} content  The lesson.
 * @property {LinkedSkill[]}              skills   Skills it teaches.
 * @property {string}                     revision Revision a save must quote.
 */

/**
 * Skills a lesson teaches, chosen in the lesson editor itself.
 *
 * @param {Object} props
 * @param {number} props.id Lesson post ID.
 * @return {React.ReactElement} Skill field with a save button.
 */
function LessonSkills( { id } ) {
	const [ links, setLinks ] = useState( /** @type {Links|null} */ ( null ) );
	const [ wanted, setWanted ] = useState( /** @type {number[]} */ ( [] ) );
	const [ names, setNames ] = useState(
		/** @type {Record<number, LinkedSkill>} */ ( {} )
	);
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ saved, setSaved ] = useState( false );

	/**
	 * Show links as they are stored.
	 *
	 * @param {Links} loaded Links read from the site.
	 */
	function show( loaded ) {
		setLinks( loaded );
		setWanted( loaded.skills.map( ( skill ) => skill.id ) );
		setNames( ( current ) => ( {
			...current,
			...Object.fromEntries(
				loaded.skills.map( ( skill ) => [ skill.id, skill ] )
			),
		} ) );
	}

	useEffect( () => {
		let active = true;
		setLinks( null );
		setError( '' );
		loadContentSkills( 'lesson', id )
			.then(
				( loaded ) => active && show( /** @type {Links} */ ( loaded ) )
			)
			.catch(
				( /** @type {{message?: string}} */ cause ) =>
					active &&
					setError(
						cause.message ||
							__( 'Skills could not be loaded.', 'ohmylms' )
					)
			);
		return () => {
			active = false;
		};
	}, [ id ] );

	if ( error && ! links ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! links ) {
		return <Spinner />;
	}
	const have = links.skills.map( ( skill ) => skill.id );
	const changed =
		wanted.length !== have.length ||
		wanted.some( ( skillId ) => ! have.includes( skillId ) );

	/** Save the chosen skills, or load the latest ones when someone else changed them first. */
	async function save() {
		if ( ! links ) {
			return;
		}
		setBusy( true );
		setError( '' );
		setSaved( false );
		try {
			show(
				/** @type {Links} */ (
					await saveContentSkills( links, wanted )
				)
			);
			setSaved( true );
		} catch ( cause ) {
			setError(
				/** @type {{message?: string}} */ ( cause ).message ||
					__( 'Skills could not be saved.', 'ohmylms' )
			);
			// A newer version exists: show it so the next try starts from the stored skills.
			loadContentSkills( 'lesson', id )
				.then( ( latest ) => show( /** @type {Links} */ ( latest ) ) )
				.catch( () => {} );
		}
		setBusy( false );
	}

	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<SkillField
				label={ __( 'Lesson skills', 'ohmylms' ) }
				value={ wanted }
				names={ names }
				empty={ __( 'No skills yet.', 'ohmylms' ) }
				disabled={ busy }
				onChange={ ( ids, picked ) => {
					setSaved( false );
					setWanted( ids );
					setNames( ( current ) => ( {
						...current,
						...Object.fromEntries(
							picked.map( ( skill ) => [ skill.id, skill ] )
						),
					} ) );
				} }
			/>
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={ busy || ! changed }
				onClick={ save }
			>
				{ __( 'Save skills', 'ohmylms' ) }
			</Button>
			{ saved && (
				<p role="status">{ __( 'Skills saved.', 'ohmylms' ) }</p>
			) }
		</>
	);
}

/**
 * Lesson skills in the lesson editor's sidebar. A quiz chooses its skills in its own panel and a question in its
 * block sidebar, so only lessons need this one.
 *
 * @return {React.ReactElement|null} Document sidebar panel for a saved lesson.
 */
function NativeSkillConnections() {
	const { type, id } = useSelect(
		( select ) => ( {
			type: select( 'core/editor' ).getCurrentPostType(),
			id: select( 'core/editor' ).getCurrentPostId(),
		} ),
		[]
	);
	if ( 'ohmylms-lesson' !== type || ! id ) {
		return null;
	}
	return (
		<PluginDocumentSettingPanel
			name="ohmylms-skill-connections"
			title={ __( 'Skills', 'ohmylms' ) }
		>
			<p>
				{ __(
					'The skills this lesson teaches. Finishing a lesson does not count as mastery; quizzes do.',
					'ohmylms'
				) }
			</p>
			<LessonSkills id={ id } />
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'ohmylms-native-skill-connections', {
	render: NativeSkillConnections,
} );
