import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { api, boot } from './api';
import type { Summary } from './types';

function drillTitle( level: string ) {
	if ( level === 'students' ) {
		return __( 'Students', 'ohmylms' );
	}
	if ( level === 'classes' ) {
		return __( 'Classes', 'ohmylms' );
	}
	return __( 'Schools', 'ohmylms' );
}

/**
 * Learning-first home. Export is two clicks: open the dialog, then download.
 *
 * @param props          Props.
 * @param props.school   School filter.
 * @param props.range    Period.
 * @param props.onPeople Open the people section.
 * @param props.onSchool Select a school from the drill.
 */
export function Home( {
	school,
	range,
	onPeople,
	onSchool,
}: {
	school: number;
	range: string;
	onPeople: () => void;
	onSchool: ( id: number ) => void;
} ) {
	const [ level, setLevel ] = useState( 'schools' );
	const [ classId, setClassId ] = useState( 0 );
	const [ data, setData ] = useState< Summary | null >( null );
	const [ error, setError ] = useState( '' );
	const [ started ] = useState( () => performance.now() );
	const [ loaded, setLoaded ] = useState( 0 );
	const [ exporting, setExporting ] = useState( false );
	const canManage = boot().canManage;
	const [ csvReady, setCsvReady ] = useState( '' );

	useEffect( () => {
		let live = true;
		setError( '' );
		const query = `?school=${ school }&range=${ range }&level=${ level }&class=${ classId }`;
		api< Summary >( `/summary${ query }` )
			.then( ( summary ) => {
				if ( live ) {
					setData( summary );
					setLoaded( Math.round( performance.now() - started ) );
				}
			} )
			.catch( ( failure: Error ) => {
				if ( live ) {
					setError( failure.message );
				}
			} );
		return () => {
			live = false;
		};
	}, [ school, range, level, classId, started ] );

	async function download() {
		const query = `?school=${ school }&range=${ range }&level=${ level }&class=${ classId }`;
		const csv = await api< string >( `/export${ query }` );
		const blob = new Blob( [ csv ], { type: 'text/csv' } );
		const link = document.createElement( 'a' );
		link.href = URL.createObjectURL( blob );
		link.download = 'ohmylms-dashboard.csv';
		link.click();
		URL.revokeObjectURL( link.href );
		setCsvReady( __( 'CSV downloaded.', 'ohmylms' ) );
	}

	if ( error ) {
		return <p role="alert">{ error }</p>;
	}
	if ( ! data ) {
		return <p>{ __( 'Loading the learning summary…', 'ohmylms' ) }</p>;
	}
	return (
		<>
			<p className="oml-dash-note">
				{ sprintf(
					/* translators: %d: milliseconds. */
					__( 'Summary loaded in %d ms.', 'ohmylms' ),
					loaded
				) }
			</p>
			<section className="oml-dash-kpis" aria-live="polite">
				{ data.kpis.map( ( card ) => (
					<article
						key={ card.id }
						className="oml-dash-card oml-dash-kpi"
					>
						<div className="oml-kpi-label">
							<span className="oml-kpi-mark" aria-hidden="true">
								{ card.label.slice( 0, 1 ) }
							</span>
							{ card.label }
						</div>
						{ card.available ? (
							<div className="value">{ card.value }</div>
						) : null }
						{ card.delta === null ? null : (
							<div
								className={
									card.delta >= 0
										? 'oml-dash-delta is-up'
										: 'oml-dash-delta is-down'
								}
							>
								{ sprintf(
									/* translators: %s: percent change versus the previous period. */
									__( '%s%% vs previous period', 'ohmylms' ),
									card.delta > 0
										? `+${ card.delta }`
										: String( card.delta )
								) }
							</div>
						) }
						<p className="oml-dash-note">{ card.note }</p>
					</article>
				) ) }
			</section>
			<div className="oml-dash-grid">
				<section className="oml-dash-card">
					<h2>{ __( 'Needs attention', 'ohmylms' ) }</h2>
					{ data.alerts.length ? (
						data.alerts.map( ( alert ) => (
							<p key={ alert.id }>
								{ alert.text }{ ' ' }
								{ canManage && (
									<button
										type="button"
										className="oml-dash-btn"
										onClick={ onPeople }
									>
										{ alert.label }
									</button>
								) }
							</p>
						) )
					) : (
						<p className="oml-dash-note">
							{ __( 'Nothing needs attention.', 'ohmylms' ) }
						</p>
					) }
				</section>
				<section className="oml-dash-card">
					<h2>{ __( 'Quick actions', 'ohmylms' ) }</h2>
					<div className="oml-dash-actions">
						{ canManage && (
							<button
								type="button"
								className="oml-dash-btn is-primary"
								onClick={ onPeople }
							>
								{ __( 'Invite someone', 'ohmylms' ) }
							</button>
						) }
						<button
							type="button"
							className="oml-dash-btn"
							onClick={ () => setExporting( true ) }
						>
							{ __( 'Export', 'ohmylms' ) }
						</button>
					</div>
				</section>
			</div>
			<section className="oml-dash-card">
				<div className="oml-dash-actions">
					{ level !== 'schools' && (
						<button
							type="button"
							className="link"
							onClick={ () => {
								setLevel( 'schools' );
								setClassId( 0 );
							} }
						>
							{ __( 'All schools', 'ohmylms' ) }
						</button>
					) }
					<h2>{ drillTitle( level ) }</h2>
				</div>
				<table className="oml-dash-table">
					<thead>
						<tr>
							<th>{ __( 'Name', 'ohmylms' ) }</th>
							{ data.drill.columns.map( ( column ) => (
								<th key={ column }>{ column }</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ data.drill.rows.length ? (
							data.drill.rows.map( ( row ) => (
								<tr key={ row.id }>
									<td>
										{ level === 'students' ? (
											row.name
										) : (
											<button
												type="button"
												className="link"
												onClick={ () => {
													if ( level === 'classes' ) {
														setLevel( 'students' );
														setClassId( row.id );
													} else {
														onSchool( row.id );
														setLevel( 'classes' );
													}
												} }
											>
												{ row.name }
											</button>
										) }
									</td>
									{ row.cells.map( ( cell, index ) => (
										<td key={ `${ row.id }-${ index }` }>
											{ cell }
										</td>
									) ) }
								</tr>
							) )
						) : (
							<tr>
								<td colSpan={ 4 }>
									{ __(
										'No schools or classes are in this view yet.',
										'ohmylms'
									) }
								</td>
							</tr>
						) }
					</tbody>
				</table>
			</section>
			<section className="oml-dash-card">
				<h2>
					{ __(
						'Mastery heatmap — skill levels × classes',
						'ohmylms'
					) }
				</h2>
				{ data.heatmap.available ? (
					<table className="oml-dash-table oml-dash-heat">
						<thead>
							<tr>
								<th>{ __( 'Level', 'ohmylms' ) }</th>
								{ data.heatmap.columns.map( ( column ) => (
									<th key={ column }>{ column }</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ data.heatmap.rows.map( ( row ) => (
								<tr key={ row.label }>
									<th>{ row.label }</th>
									{ row.cells.map( ( cell, index ) => (
										<td
											key={ `${ row.label }-${ index }` }
											className={ `is-${ row.label }` }
										>
											{ cell }
										</td>
									) ) }
								</tr>
							) ) }
						</tbody>
					</table>
				) : null }
				<p className="oml-dash-note">{ data.heatmap.note }</p>
			</section>
			{ exporting && (
				<dialog className="oml-dash-dialog" open>
					<h2>{ __( 'Export', 'ohmylms' ) }</h2>
					<p className="oml-dash-note">
						{ __(
							'The file uses the school, period and drill you are looking at.',
							'ohmylms'
						) }
					</p>
					<div className="oml-dash-actions">
						<button
							type="button"
							className="oml-dash-btn is-primary"
							onClick={ () => void download() }
						>
							{ __( 'Download CSV', 'ohmylms' ) }
						</button>
						<button
							type="button"
							className="oml-dash-btn"
							onClick={ () => setExporting( false ) }
						>
							{ __( 'Close', 'ohmylms' ) }
						</button>
					</div>
					{ csvReady && <p role="status">{ csvReady }</p> }
				</dialog>
			) }
		</>
	);
}
