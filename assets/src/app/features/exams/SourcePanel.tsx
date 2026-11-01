import {
	Button,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CropSelector, { type CropRegion } from './CropSelector';
import {
	DEFAULT_BOUNDS,
	fromPercentText,
	sameBounds,
	toPercentText,
	type Bounds,
} from './cropGeometry';
import {
	documentUrl,
	pageImageUrl,
	rendererMessage,
	type SourceInfo,
} from './sourceModel';
import './SourcePanel.css';

/** What the viewer follows: a question, or one of its figures. */
export interface SourceFocus {
	/** Changes when the reviewer selects another question or figure. */
	key: string;
	/** Heading of the panel, for example "Question 2". */
	title: string;
	page: number;
	/** The figure whose crop is shown and replaced, or null for the question. */
	figure: string | null;
	/** The figure's crop, when it has one. */
	bounds: Bounds | null;
}

export interface FigureRegion extends CropRegion {
	page: number;
}

interface SourcePanelProps {
	source: SourceInfo | null;
	/** Why the descriptor could not be loaded. */
	error: string | null;
	focus: SourceFocus;
	/** Saved crops of the focused question, drawn on their pages. */
	regions: readonly FigureRegion[];
	/** Crops can be saved: the review is open and the server can render them. */
	canCrop: boolean;
	busy: boolean;
	onCrop: ( page: number, bounds: Bounds, figure: string | null ) => void;
}

type Mode = 'image' | 'pdf';

const ZOOMS = [ 1, 1.5, 2 ] as const;

function fieldLabels(): string[] {
	return [
		__( 'Left (%)', 'ohmylms' ),
		__( 'Top (%)', 'ohmylms' ),
		__( 'Right (%)', 'ohmylms' ),
		__( 'Bottom (%)', 'ohmylms' ),
	];
}

/**
 * The source paper next to the review: the rendered page of the focused question with its crops, or
 * the PDF in the browser's viewer when the server cannot render pages. Drawing (or typing) a crop and
 * choosing where it goes queues it through `POST /exam-imports/jobs/{id}/figures`.
 *
 * @param props         Component properties.
 * @param props.source  Source descriptor, or null while loading.
 * @param props.error   Why the descriptor could not be loaded.
 * @param props.focus   The question or figure to show.
 * @param props.regions Saved crops of the question.
 * @param props.canCrop Crops can be saved.
 * @param props.busy    Another request is running.
 * @param props.onCrop  Queue the drawn crop for a figure, or as a new one.
 * @return The panel.
 */
export default function SourcePanel( {
	source,
	error,
	focus,
	regions,
	canCrop,
	busy,
	onCrop,
}: SourcePanelProps ) {
	const rendered = !! source?.renderer.available;
	const last = source?.page_count || 500;
	const [ page, setPage ] = useState( focus.page );
	const [ bounds, setBounds ] = useState< Bounds >(
		focus.bounds ?? DEFAULT_BOUNDS
	);
	const [ fields, setFields ] = useState( toPercentText( bounds ) );
	const [ mode, setMode ] = useState< Mode >( 'image' );
	const [ zoom, setZoom ] = useState< number >( 1 );
	const [ broken, setBroken ] = useState< string | null >( null );

	// Follow the focused question or figure.
	useEffect( () => {
		const next = focus.bounds ?? DEFAULT_BOUNDS;
		setPage( Math.max( 1, focus.page ) );
		setBounds( next );
		setFields( toPercentText( next ) );
		// Only a new focus resets the page and selection; edits keep them.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ focus.key ] );

	useEffect( () => {
		if ( source && ! source.renderer.available ) {
			setMode( 'pdf' );
		}
	}, [ source ] );

	if ( error ) {
		return (
			<aside
				className="ohmylms-source-panel"
				aria-label={ __( 'Source paper', 'ohmylms' ) }
			>
				<Notice status="warning" isDismissible={ false }>
					{ error }
				</Notice>
			</aside>
		);
	}
	if ( ! source ) {
		return (
			<aside
				className="ohmylms-source-panel"
				aria-label={ __( 'Source paper', 'ohmylms' ) }
			>
				<p role="status">
					{ __( 'Loading the source paper…', 'ohmylms' ) }
				</p>
			</aside>
		);
	}

	const message = rendererMessage( source.renderer );
	const imageUrl = pageImageUrl( source, page );
	const showImage = rendered && 'image' === mode;
	const typed = fromPercentText( fields );
	const editable = showImage && canCrop;

	function select( next: Bounds ) {
		setBounds( next );
		setFields( toPercentText( next ) );
	}

	function type( index: number, value: string ) {
		const next = fields.map( ( item, at ) =>
			at === index ? value : item
		) as typeof fields;
		setFields( next );
		const parsed = fromPercentText( next );
		if ( parsed ) {
			setBounds( parsed );
		}
	}

	function go( next: number ) {
		setPage( Math.min( last, Math.max( 1, next ) ) );
		setBroken( null );
	}

	return (
		<aside
			className="ohmylms-source-panel"
			aria-labelledby="oml-source-title"
		>
			<h3 id="oml-source-title">
				{ focus.figure
					? sprintf(
							/* translators: 1: question, for example "Question 2"; 2: figure ID; 3: page number. */
							__(
								'Source: %1$s, figure %2$s, page %3$d',
								'ohmylms'
							),
							focus.title,
							focus.figure,
							page
						)
					: sprintf(
							/* translators: 1: question, for example "Question 2"; 2: page number. */
							__( 'Source: %1$s, page %2$d', 'ohmylms' ),
							focus.title,
							page
						) }
			</h3>
			{ 'success' === message.tone ? (
				<p className="ohmylms-source-renderer">{ message.text }</p>
			) : (
				<Notice status="warning" isDismissible={ false }>
					{ message.text }
				</Notice>
			) }
			<div className="ohmylms-source-toolbar">
				<Button
					variant="secondary"
					size="small"
					disabled={ page <= 1 }
					onClick={ () => go( page - 1 ) }
				>
					{ __( 'Previous page', 'ohmylms' ) }
				</Button>
				<span>
					{ source.page_count
						? sprintf(
								/* translators: 1: page; 2: number of pages. */
								__( 'Page %1$d of %2$d', 'ohmylms' ),
								page,
								source.page_count
							)
						: sprintf(
								/* translators: %d: page. */
								__( 'Page %d', 'ohmylms' ),
								page
							) }
				</span>
				<Button
					variant="secondary"
					size="small"
					disabled={ page >= last }
					onClick={ () => go( page + 1 ) }
				>
					{ __( 'Next page', 'ohmylms' ) }
				</Button>
				{ rendered && (
					<Button
						variant="tertiary"
						size="small"
						onClick={ () =>
							setMode( 'image' === mode ? 'pdf' : 'image' )
						}
					>
						{ 'image' === mode
							? __( 'Open in the PDF viewer', 'ohmylms' )
							: __( 'Show the page image', 'ohmylms' ) }
					</Button>
				) }
				{ showImage && (
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Zoom', 'ohmylms' ) }
						hideLabelFromVision
						value={ String( zoom ) }
						options={ ZOOMS.map( ( value ) => ( {
							value: String( value ),
							label: `${ value * 100 }%`,
						} ) ) }
						onChange={ ( value ) => setZoom( Number( value ) ) }
					/>
				) }
			</div>
			{ showImage && broken === imageUrl && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'This page could not be rendered. Try the PDF viewer.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ showImage ? (
				<CropSelector
					src={ imageUrl }
					alt={ sprintf(
						/* translators: %d: page number. */
						__( 'Page %d of the source paper', 'ohmylms' ),
						page
					) }
					bounds={ bounds }
					regions={ regions.filter(
						( region ) =>
							region.page === page &&
							! (
								region.id === focus.figure &&
								sameBounds( region.bounds, bounds )
							)
					) }
					zoom={ zoom }
					focusKey={ `${ focus.key }:${ page }` }
					readOnly={ ! editable }
					onChange={ select }
					onError={ () => setBroken( imageUrl ) }
				/>
			) : (
				<iframe
					key={ page }
					className="ohmylms-source-pdf"
					title={ __( 'Source paper (PDF)', 'ohmylms' ) }
					src={ documentUrl( source, page ) }
				/>
			) }
			<p className="ohmylms-source-links">
				<a
					href={ documentUrl( source, page ) }
					target="_blank"
					rel="noreferrer"
				>
					{ __( 'Open the paper in a new tab', 'ohmylms' ) }
				</a>
				{ source.mark_scheme && (
					<a
						href={ source.mark_scheme }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Open the mark scheme', 'ohmylms' ) }
					</a>
				) }
			</p>
			{ editable && (
				<fieldset className="ohmylms-source-crop" disabled={ busy }>
					<legend>{ __( 'Crop', 'ohmylms' ) }</legend>
					<div className="ohmylms-source-crop-fields">
						{ fieldLabels().map( ( label, index ) => (
							<TextControl
								key={ label }
								__nextHasNoMarginBottom
								label={ label }
								value={ fields[ index ] ?? '' }
								inputMode="decimal"
								onChange={ ( value ) => type( index, value ) }
							/>
						) ) }
					</div>
					{ ! typed && (
						<p role="alert">
							{ __(
								'Enter numbers from 0 to 100, with left before right and top before bottom.',
								'ohmylms'
							) }
						</p>
					) }
					<p>
						{ focus.figure && (
							<>
								<Button
									variant="primary"
									disabled={ busy || ! typed }
									onClick={ () =>
										onCrop( page, bounds, focus.figure )
									}
								>
									{ sprintf(
										/* translators: %s: figure ID. */
										__(
											'Use this area for figure %s',
											'ohmylms'
										),
										focus.figure
									) }
								</Button>{ ' ' }
							</>
						) }
						<Button
							variant={ focus.figure ? 'secondary' : 'primary' }
							disabled={ busy || ! typed }
							onClick={ () => onCrop( page, bounds, null ) }
						>
							{ sprintf(
								/* translators: %s: question, for example "Question 2". */
								__( 'Add as a new figure of %s', 'ohmylms' ),
								focus.title
							) }
						</Button>
					</p>
				</fieldset>
			) }
		</aside>
	);
}
