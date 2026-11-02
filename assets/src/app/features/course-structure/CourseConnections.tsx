import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import {
	ContentSkillConnections,
	type ContentKind,
} from '../curriculum/public';

export interface ConnectionTarget {
	kind: ContentKind;
	id: number;
}

/**
 * One selected record's persisted connections; outline placements keep their own draft/save contract.
 * @param root0
 * @param root0.target
 * @param root0.course
 * @param root0.dirty
 * @param root0.onDirtyChange
 * @param root0.onCourse
 */
export function CourseConnections( {
	target,
	course,
	dirty,
	onDirtyChange,
	onCourse,
}: {
	target: ConnectionTarget;
	course: number;
	dirty: boolean;
	onDirtyChange: ( dirty: boolean ) => void;
	onCourse: () => void;
} ) {
	return (
		<section
			className="oml-course-connections"
			aria-label={ __( 'Course skill connections', 'ohmylms' ) }
		>
			<h2>{ __( 'Skills and connections', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Choose Skills beside an activity to connect what it teaches or assesses. Save connections separately from the outline draft.',
					'ohmylms'
				) }
			</p>
			{ ( target.kind !== 'course' || target.id !== course ) && (
				<Button variant="secondary" onClick={ onCourse }>
					{ __( 'Course skills', 'ohmylms' ) }
				</Button>
			) }
			<p role="status">
				{ dirty
					? __( 'Unsaved skill connections', 'ohmylms' )
					: __( 'Skill connections are up to date', 'ohmylms' ) }
			</p>
			<ContentSkillConnections
				key={ `${ target.kind }:${ target.id }` }
				kind={ target.kind }
				id={ target.id }
				onDirtyChange={ onDirtyChange }
			/>
			<p>
				<Link to="/content-hub/skills/content-links?kind=question">
					{ __( 'Connect questions and other content', 'ohmylms' ) }
				</Link>
			</p>
		</section>
	);
}

/**
 * Selection changes cannot silently discard staged connection edits.
 * @param course
 * @param opening
 */
export function useConnectionTarget(
	course: number,
	opening?: ConnectionTarget
) {
	const [ target, setTarget ] = useState< ConnectionTarget >(
		opening ?? { kind: 'course', id: course }
	);
	const [ dirty, setDirty ] = useState( false );
	const [ pending, setPending ] = useState< ConnectionTarget | null >( null );
	function choose( next: ConnectionTarget ) {
		if ( next.kind === target.kind && next.id === target.id ) {
			return;
		}
		if ( dirty ) {
			setPending( next );
		} else {
			setTarget( next );
		}
	}
	return {
		target,
		dirty,
		setDirty,
		choose,
		confirmation: pending && (
			<Modal
				title={ __( 'Discard unsaved skill connections?', 'ohmylms' ) }
				onRequestClose={ () => setPending( null ) }
			>
				<Button onClick={ () => setPending( null ) }>
					{ __( 'Keep editing', 'ohmylms' ) }
				</Button>
				<Button
					onClick={ () => {
						setDirty( false );
						setTarget( pending );
						setPending( null );
					} }
				>
					{ __( 'Discard and switch', 'ohmylms' ) }
				</Button>
			</Modal>
		),
	};
}
