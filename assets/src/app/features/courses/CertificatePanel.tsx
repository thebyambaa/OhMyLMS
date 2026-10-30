import { Button, Notice, SelectControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import { getCourseCertificate, saveCourseCertificate } from './api';
import type { CertificateAssignment } from './types';

/**
 * Certificate template the course awards. Saved on its own, separately from the course form.
 *
 * @param props        Component props.
 * @param props.course Course ID.
 */
export function CertificatePanel( { course }: { course: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => getCourseCertificate( course, signal ),
		[ course ]
	);
	const request = useRequest( load );
	return (
		<section aria-label={ __( 'Certificate', 'ohmylms' ) }>
			<h3>{ __( 'Certificate', 'ohmylms' ) }</h3>
			{ request.loading && (
				<p role="status">
					{ __( 'Loading certificates…', 'ohmylms' ) }
				</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }{ ' ' }
					<Button variant="link" onClick={ request.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.data && (
				<CertificateForm
					key={ request.data.certificate_id }
					initial={ request.data }
					reload={ request.retry }
				/>
			) }
		</section>
	);
}

function CertificateForm( {
	initial,
	reload,
}: {
	initial: CertificateAssignment;
	reload: () => void;
} ) {
	const [ saved, setSaved ] = useState( initial );
	const [ choice, setChoice ] = useState( String( initial.certificate_id ) );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	const dirty = choice !== String( saved.certificate_id );
	return (
		<form
			onSubmit={ ( event ) => {
				event.preventDefault();
				setMessage( '' );
				void operation.run(
					( signal ) =>
						saveCourseCertificate(
							saved.course_id,
							Number( choice ),
							saved.certificate_id,
							signal
						),
					( result ) => {
						setSaved( result );
						setChoice( String( result.certificate_id ) );
						setMessage( __( 'Certificate saved.', 'ohmylms' ) );
					}
				);
			} }
		>
			{ operation.error && (
				<Notice status="error" isDismissible={ false }>
					{ operation.error }{ ' ' }
					<Button variant="link" onClick={ reload }>
						{ __( 'Reload certificate', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			<SelectControl< string >
				label={ __( 'Certificate template', 'ohmylms' ) }
				value={ choice }
				options={ [
					{ value: '0', label: __( 'No certificate', 'ohmylms' ) },
					...saved.choices.map( ( item ) => ( {
						value: String( item.id ),
						label:
							'publish' === item.status
								? item.title
								: sprintf(
										/* translators: %s: certificate template name. */
										__( '%s (draft)', 'ohmylms' ),
										item.title
									),
					} ) ),
				] }
				help={ __(
					'A course awards at most one certificate. Choosing a template here moves the course away from its previous template.',
					'ohmylms'
				) }
				disabled={ operation.busy }
				onChange={ setChoice }
			/>
			<Button
				type="submit"
				variant="secondary"
				disabled={ operation.busy || ! dirty }
				isBusy={ operation.busy }
			>
				{ __( 'Save certificate', 'ohmylms' ) }
			</Button>
		</form>
	);
}
