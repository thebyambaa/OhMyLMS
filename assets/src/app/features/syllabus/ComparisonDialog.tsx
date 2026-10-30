import { Button, Modal, Notice, SelectControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useRequest } from '../../hooks/useRequest';
import { readTree } from '../curriculum';
import { compare } from './api';

export function ComparisonDialog( {
	id,
	onClose,
}: {
	id: number;
	onClose: () => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => readTree( signal ),
		[]
	);
	const request = useRequest( load );
	const [ target, setTarget ] = useState( '' );
	const [ pair, setPair ] = useState( 0 );
	return (
		<Modal
			title={ __( 'Compare syllabuses', 'ohmylms' ) }
			onRequestClose={ onClose }
		>
			<p>
				{ __(
					'Comparison uses explicit shared-skill mappings. Unmapped objectives are listed separately and are not counted as learner gaps.',
					'ohmylms'
				) }
			</p>
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ request.loading && (
				<p role="status">{ __( 'Loading syllabuses…', 'ohmylms' ) }</p>
			) }
			<SelectControl
				label={ __( 'Compare with syllabus', 'ohmylms' ) }
				value={ target }
				options={ [
					{ value: '', label: __( 'Choose a syllabus', 'ohmylms' ) },
					...( request.data ?? [] )
						.filter(
							( item ) => item.is_syllabus && item.id !== id
						)
						.map( ( item ) => ( {
							value: String( item.id ),
							label: item.name,
						} ) ),
				] }
				onChange={ ( value ) => {
					setTarget( value );
					setPair( 0 );
				} }
			/>
			<Button
				variant="primary"
				disabled={ ! target }
				onClick={ () => setPair( Number( target ) ) }
			>
				{ __( 'Compare', 'ohmylms' ) }
			</Button>
			{ pair > 0 && (
				<ComparisonResult key={ pair } a={ id } b={ pair } />
			) }
		</Modal>
	);
}
function ComparisonResult( { a, b }: { a: number; b: number } ) {
	const load = useCallback(
		( signal: AbortSignal ) => compare( a, b, signal ),
		[ a, b ]
	);
	const request = useRequest( load );
	const data = request.data;
	if ( request.loading ) {
		return <p role="status">{ __( 'Comparing…', 'ohmylms' ) }</p>;
	}
	if ( request.error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ request.error }
				<Button onClick={ request.retry }>
					{ __( 'Retry comparison', 'ohmylms' ) }
				</Button>
			</Notice>
		);
	}
	if ( ! data ) {
		return null;
	}
	return (
		<section aria-label={ __( 'Comparison result', 'ohmylms' ) }>
			<h2>
				{ data.a.name } / { data.b.name }
			</h2>
			<p>
				{
					/* translators: %d: shared coverage overlap percentage, not a learner score. */ sprintf(
						__( 'Shared mapped coverage overlap: %d%%', 'ohmylms' ),
						data.overlap
					)
				}
			</p>
			<dl>
				<dt>{ __( 'Mapped shared skills in both', 'ohmylms' ) }</dt>
				<dd>{ data.both }</dd>
				<dt>{ __( 'Only in first syllabus', 'ohmylms' ) }</dt>
				<dd>{ data.onlyA }</dd>
				<dt>{ __( 'Only in second syllabus', 'ohmylms' ) }</dt>
				<dd>{ data.onlyB }</dd>
				<dt>{ __( 'Unmapped objectives in first', 'ohmylms' ) }</dt>
				<dd>{ data.unmappedA }</dd>
				<dt>{ __( 'Unmapped objectives in second', 'ohmylms' ) }</dt>
				<dd>{ data.unmappedB }</dd>
			</dl>
			<table className="widefat">
				<caption>
					{ __( 'Coverage through shared skills', 'ohmylms' ) }
				</caption>
				<thead>
					<tr>
						<th scope="col">{ __( 'Shared skill', 'ohmylms' ) }</th>
						<th scope="col">{ data.a.name }</th>
						<th scope="col">{ data.b.name }</th>
					</tr>
				</thead>
				<tbody>
					{ data.rows.map( ( row, index ) => (
						<tr key={ index }>
							<th scope="row">{ row.name }</th>
							<td>{ row.a.join( '; ' ) || '—' }</td>
							<td>{ row.b.join( '; ' ) || '—' }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</section>
	);
}
