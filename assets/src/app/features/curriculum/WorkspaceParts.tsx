import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { useRequest } from '../../hooks/useRequest';
import { listSkills } from '../content-hub';
import type { ContentKind, SkillRef } from './workspace';

/**
 * Library search: syllabus objectives and shared skills alike.
 * @param root0
 * @param root0.label
 * @param root0.exclude
 * @param root0.disabled
 * @param root0.onChoose
 */
export function SkillChooser( {
	label,
	exclude = [],
	disabled = false,
	onChoose,
}: {
	label: string;
	exclude?: readonly number[];
	disabled?: boolean;
	onChoose: ( skill: SkillRef ) => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState< string | null >( null );
	const load = useCallback(
		( signal: AbortSignal ) =>
			null === search
				? Promise.resolve( null )
				: listSkills( 1, search, signal ),
		[ search ]
	);
	const request = useRequest( load );
	const found = request.data?.items.filter(
		( skill ) => ! exclude.includes( skill.id ) && ! skill.archived
	);
	return (
		<div className="ohmylms-skill-chooser">
			<form
				role="search"
				aria-label={ label }
				onSubmit={ ( event ) => {
					event.preventDefault();
					setSearch( query.trim() );
				} }
			>
				<TextControl
					label={ label }
					value={ query }
					maxLength={ 100 }
					disabled={ disabled }
					onChange={ setQuery }
				/>
				<Button type="submit" variant="secondary" disabled={ disabled }>
					{ __( 'Search skills', 'ohmylms' ) }
				</Button>
			</form>
			{ request.loading && null !== search && (
				<p role="status">{ __( 'Searching…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ found && ! found.length && (
				<p>{ __( 'No matching skills.', 'ohmylms' ) }</p>
			) }
			{ found && found.length > 0 && (
				<ul aria-label={ __( 'Matching skills', 'ohmylms' ) }>
					{ found.map( ( skill ) => (
						<li key={ skill.id }>
							<Button
								variant="link"
								disabled={ disabled }
								onClick={ () =>
									onChoose( {
										id: skill.id,
										name: skill.name,
										code: skill.code,
									} )
								}
							>
								{ skill.code
									? `${ skill.code } ${ skill.name }`
									: skill.name }
							</Button>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

/**
 * An explicit confirmation step before a change that removes something.
 * @param root0
 * @param root0.title
 * @param root0.children
 * @param root0.confirm
 * @param root0.destructive
 * @param root0.busy
 * @param root0.error
 * @param root0.onConfirm
 * @param root0.onClose
 */
export function ConfirmModal( {
	title,
	children,
	confirm,
	busy,
	error,
	destructive = true,
	onConfirm,
	onClose,
}: {
	title: string;
	children: ReactNode;
	confirm: string;
	destructive?: boolean;
	busy: boolean;
	error: string;
	onConfirm: () => void;
	onClose: () => void;
} ) {
	return (
		<Modal
			title={ title }
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
		>
			{ children }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<Button variant="secondary" disabled={ busy } onClick={ onClose }>
				{ __( 'Cancel', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="primary"
				isDestructive={ destructive }
				isBusy={ busy }
				disabled={ busy }
				onClick={ onConfirm }
			>
				{ confirm }
			</Button>
		</Modal>
	);
}

export function sourceLabel( source: string, confidence: number | null ) {
	const name =
		{
			manual: __( 'Manual', 'ohmylms' ),
			syllabus: __( 'Syllabus', 'ohmylms' ),
			import: __( 'Import', 'ohmylms' ),
			ai: __( 'AI run', 'ohmylms' ),
		}[ source ] ?? source;
	return null === confidence
		? name
		: sprintf(
				/* translators: 1: source, 2: confidence percentage. */
				__( '%1$s (%2$d%%)', 'ohmylms' ),
				name,
				Math.round( confidence * 100 )
			);
}

export function relationLabel( relation: string ) {
	return (
		{
			equivalent: __( 'Equivalent', 'ohmylms' ),
			broader: __( 'Source is broader', 'ohmylms' ),
			narrower: __( 'Source is narrower', 'ohmylms' ),
			related: __( 'Related', 'ohmylms' ),
			combined: __( 'Required together', 'ohmylms' ),
			prerequisite: __( 'Needs first', 'ohmylms' ),
			encompasses: __( 'Includes', 'ohmylms' ),
		}[ relation ] ?? relation
	);
}

export function reviewLabel( review: string ) {
	return (
		{
			pending: __( 'Pending review', 'ohmylms' ),
			approved: __( 'Approved', 'ohmylms' ),
			rejected: __( 'Rejected', 'ohmylms' ),
			accepted: __( 'Accepted', 'ohmylms' ),
		}[ review ] ?? review
	);
}

/**
 * Route of a content item's editor in the app.
 * @param kind
 * @param id
 */
export function contentRoute( kind: ContentKind | 'course', id: number ) {
	return {
		lesson: `/content-hub/lessons/${ id }`,
		quiz: `/quizzes/${ id }`,
		assignment: `/assignments/${ id }`,
		course: `/courses/${ id }`,
		question: `/content-hub/question-bank/${ id }`,
	}[ kind ];
}

export function kindLabel( kind: ContentKind ) {
	return {
		lesson: __( 'Lesson', 'ohmylms' ),
		quiz: __( 'Quiz', 'ohmylms' ),
		assignment: __( 'Assignment', 'ohmylms' ),
		course: __( 'Course', 'ohmylms' ),
		question: __( 'Question', 'ohmylms' ),
	}[ kind ];
}
