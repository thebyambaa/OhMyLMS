import { Button, Modal, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import {
	PageTitleAction,
	PageTitleActions,
} from '../../layout/PageTitleActions';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	createSampleSchool,
	getSampleSchool,
	removeSampleSchool,
} from './operationsApi';

/**
 * The sample school: a page-title action that creates it (two classes, six made-up learners, a teacher,
 * a school administrator and a parent, all marked and unable to sign in) or removes it in one step.
 *
 * @param props          Component props.
 * @param props.onChange Called after the sample school was created or removed.
 */
export function SampleSchoolActions( { onChange }: { onChange: () => void } ) {
	const state = useRequest(
		useCallback( ( signal: AbortSignal ) => getSampleSchool( signal ), [] )
	);
	const operation = useOperation();
	const [ open, setOpen ] = useState< 'create' | 'remove' | null >( null );
	const [ message, setMessage ] = useState( '' );
	const sample = state.data;

	function done( text: string ) {
		setOpen( null );
		setMessage( text );
		state.retry();
		onChange();
	}

	return (
		<>
			{ sample && (
				<PageTitleActions>
					<PageTitleAction
						onClick={ () => {
							operation.clearError();
							setOpen( sample.exists ? 'remove' : 'create' );
						} }
					>
						{ sample.exists
							? __( 'Remove sample school', 'ohmylms' )
							: __( 'Add sample school', 'ohmylms' ) }
					</PageTitleAction>
				</PageTitleActions>
			) }
			{ message && (
				<Notice
					status="success"
					spokenMessage={ message }
					onRemove={ () => setMessage( '' ) }
				>
					{ message }{ ' ' }
					{ sample?.exists && sample.school_id > 0 && (
						<Link
							to={ `/account-hub/schools/${ sample.school_id }` }
						>
							{ __( 'Open the sample school', 'ohmylms' ) }
						</Link>
					) }
				</Notice>
			) }
			{ 'create' === open && (
				<Modal
					title={ __( 'Add a sample school?', 'ohmylms' ) }
					onRequestClose={ () => ! operation.busy && setOpen( null ) }
				>
					<p>
						{ __(
							'Creates “Sample Preview School” with one academic year, two classes, six made-up students, a teacher, a school administrator and a parent linked to one student. The accounts are marked as samples, use undeliverable addresses and cannot sign in. Remove everything again with one click.',
							'ohmylms'
						) }
					</p>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						variant="primary"
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ () =>
							void operation.run(
								( signal ) => createSampleSchool( signal ),
								( created ) =>
									done(
										sprintf(
											/* translators: 1: classes, 2: people. */
											__(
												'Sample school ready: %1$d classes and %2$d sample people.',
												'ohmylms'
											),
											created.classes.length,
											created.people.length
										)
									)
							)
						}
					>
						{ __( 'Add sample school', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setOpen( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ 'remove' === open && sample && (
				<Modal
					title={ __( 'Remove the sample school?', 'ohmylms' ) }
					onRequestClose={ () => ! operation.busy && setOpen( null ) }
				>
					{ sample.removal.allowed ? (
						<>
							<p>
								{ __(
									'These sample records are deleted. Nothing else is touched.',
									'ohmylms'
								) }
							</p>
							<ul className="ul-disc">
								{ sample.removal.remove.map( ( line ) => (
									<li key={ line }>{ line }</li>
								) ) }
							</ul>
						</>
					) : (
						<Notice status="warning" isDismissible={ false }>
							<p>
								{ __(
									'The sample school is in use, so it is not removed:',
									'ohmylms'
								) }
							</p>
							<ul className="ul-disc">
								{ sample.removal.blockers.map( ( line ) => (
									<li key={ line }>{ line }</li>
								) ) }
							</ul>
						</Notice>
					) }
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						variant="primary"
						isDestructive
						disabled={ operation.busy || ! sample.removal.allowed }
						isBusy={ operation.busy }
						onClick={ () =>
							void operation.run(
								( signal ) => removeSampleSchool( signal ),
								() =>
									done(
										__(
											'The sample school and its sample accounts were removed.',
											'ohmylms'
										)
									)
							)
						}
					>
						{ __( 'Remove sample school', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setOpen( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</>
	);
}
