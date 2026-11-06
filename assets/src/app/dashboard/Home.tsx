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
	}, [ school, range, level, classId ] );

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
	const quiet =
		data.kpis.length > 0 &&
		data.kpis.every( ( card ) => {
			if ( ! card.available ) {
				return true;
			}
			const lead = card.value.match( /[\d.]+/ );
			return lead ? Number( lead[ 0 ] ) === 0 : true;
		} );
	const shown = quiet
		? []
		: data.kpis.filter( ( card ) => {
				if ( ! card.available ) {
					return false;
				}
				const lead = card.value.match( /[\d.]+/ );
				return lead ? Number( lead[ 0 ] ) > 0 : false;
			} );
	return (
		<>
			<header className="oml-page-bar">
				<h1 className="oml-dash-title">{ __( 'Home', 'ohmylms' ) }</h1>
				<details className="oml-more">
					<summary>{ __( 'Export', 'ohmylms' ) }</summary>
					<button
						type="button"
						className="oml-dash-btn is-ghost"
						onClick={ () => setExporting( true ) }
					>
						{ __( 'Download CSV', 'ohmylms' ) }
					</button>
				</details>
			</header>
			{ quiet ? (
				<p className="oml-dash-note">
					{ __(
						'No learning activity in this period yet.',
						'ohmylms'
					) }
				</p>
			) : (
				<section className="oml-dash-kpis" aria-live="polite">
					{ shown.map( ( card ) => (
						<article
							key={ card.id }
							className="oml-dash-card oml-dash-kpi"
						>
							<div className="oml-kpi-label">
								<span
									className="oml-kpi-mark"
									aria-hidden="true"
								>
									{ card.label.slice( 0, 1 ) }
								</span>
								{ card.label }
							</div>
							<div className="value">{ card.value }</div>
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
										__(
											'%s%% vs previous period',
											'ohmylms'
										),
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
			) }
			<section className="oml-dash-card">
				<h2>{ __( 'Needs attention', 'ohmylms' ) }</h2>
				{ data.alerts.length ? (
					data.alerts.map( ( alert ) => (
						<p key={ alert.id }>
							{ alert.text }{ ' ' }
							{ canManage && (
								<button
									type="button"
									className="oml-dash-btn is-primary"
									onClick={ onPeople }
								>
									{ alert.label }
								</button>
							) }
						</p>
					) )
				) : (
					<p className="oml-dash-note">
						{ canManage
							? __( 'Nothing needs attention.', 'ohmylms' )
							: __( 'Nothing needs attention.', 'ohmylms' ) }
					</p>
				) }
				{ canManage && ! data.alerts.length && (
					<button
						type="button"
						className="oml-dash-btn is-primary"
						onClick={ onPeople }
					>
						{ __( 'Invite someone', 'ohmylms' ) }
					</button>
				) }
			</section>
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
			{ data.heatmap.available ? (
				<section className="oml-dash-card">
					<h2>
						{ __(
							'Mastery heatmap — skill levels × classes',
							'ohmylms'
						) }
					</h2>
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
				</section>
			) : (
				<p className="oml-dash-note">{ data.heatmap.note }</p>
			) }
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
