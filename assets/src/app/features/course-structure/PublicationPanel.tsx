import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import {
	previewPublication,
	publishCourse,
	type Publication,
} from './publication-api';

export function PublicationPanel( {
	course,
	disabled,
	onPublished,
}: {
	course: number;
	disabled: boolean;
	onPublished: () => void;
} ) {
	const operation = useOperation();
	const [ preview, setPreview ] = useState< Publication | null >( null );
	const [ confirm, setConfirm ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	return (
		<section aria-label={ __( 'Course publication', 'ohmylms' ) }>
			<h2>{ __( 'Preview and publish', 'ohmylms' ) }</h2>
			<p>
				{ __(
					'Save the outline before previewing. Publishing makes this course and the saved learning program available to new enrollments. Existing learners keep their current program version. Learning resources must already be published.',
					'ohmylms'
				) }
			</p>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			<Button
				disabled={ disabled || operation.busy }
				isBusy={ operation.busy }
				onClick={ () =>
					void operation.run(
						( signal ) => previewPublication( course, signal ),
						( result ) => {
							setPreview( result );
							setMessage( '' );
						}
					)
				}
			>
				{ __( 'Preview saved course', 'ohmylms' ) }
			</Button>
			{ preview && ! disabled && (
				<>
					<h3>{ preview.title }</h3>
					<p>
						{
							/* translators: %1$s: learning mode; %2$d: number of skill targets. */ sprintf(
								__(
									'Learning mode: %1$s. Skill targets: %2$d.',
									'ohmylms'
								),
								preview.mode,
								preview.outcomes
							)
						}
					</p>
					{ preview.units.map( ( unit ) => (
						<div key={ unit.id }>
							<h4>{ unit.title }</h4>
							<ul>
								{ unit.items.map( ( item ) => (
									<li key={ item.id }>
										{ item.title } ({ item.type })
									</li>
								) ) }
							</ul>
						</div>
					) ) }
					{ preview.readiness.length > 0 ? (
						<Notice status="warning" isDismissible={ false }>
							<p>
								{ __(
									'Resolve these items before publishing:',
									'ohmylms'
								) }
							</p>
							<ul>
								{ preview.readiness.map( ( issue, index ) => (
									<li key={ index }>{ issue }</li>
								) ) }
							</ul>
						</Notice>
					) : (
						<p role="status">
							{ __(
								'The saved learning program is ready to publish.',
								'ohmylms'
							) }
						</p>
					) }
					<Button
						variant="primary"
						disabled={
							operation.busy || preview.readiness.length > 0
						}
						onClick={ () => setConfirm( true ) }
					>
						{ __( 'Review publication', 'ohmylms' ) }
					</Button>
				</>
			) }
			{ confirm && preview && ! disabled && (
				<Modal
					title={ __( 'Publish this course?', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setConfirm( false );
						}
					} }
				>
					<p>{ preview.title }</p>
					<p>
						{ __(
							'This publishes the course and saved learning program. Pricing and access settings still apply. Existing learner versions are retained.',
							'ohmylms'
						) }
					</p>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<Button
						disabled={ operation.busy }
						onClick={ () => setConfirm( false ) }
					>
						{ __( 'Keep draft', 'ohmylms' ) }
					</Button>
					<Button
						variant="primary"
						disabled={ operation.busy }
						isBusy={ operation.busy }
						onClick={ () =>
							void operation.run(
								( signal ) => publishCourse( preview, signal ),
								( result ) => {
									setPreview( result );
									setConfirm( false );
									setMessage(
										__(
											'Course and learning program published.',
											'ohmylms'
										)
									);
									onPublished();
								}
							)
						}
					>
						{ __( 'Confirm publication', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}
