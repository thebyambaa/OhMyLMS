import { Button, TextControl, TextareaControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { decideProposal, proposeFigure } from './api';
import { proposalLabel, readFigureSpec, withObjectLabel } from './model';
import type {
	FigureSpec,
	ImportFigure,
	ImportJobDetail,
	ProposalDisplay,
} from './types';

declare global {
	interface Window {
		OhMyLMSFigures?: { scan: () => void };
	}
}

interface FigureProposalPanelProps {
	jobId: number;
	rowIndex: number;
	revision: number;
	/** The figure as stored; proposals work on saved figures only. */
	figure: ImportFigure;
	/** Protected crop URL for the owner. */
	cropUrl: string;
	canGenerate: boolean;
	/** The row has unsaved edits, which a proposal change would discard. */
	dirty: boolean;
	busy: boolean;
	onRun: (
		work: ( signal: AbortSignal ) => Promise< ImportJobDetail >,
		done: string
	) => void;
}

/**
 * The reconstruction drawn by the same fixed JSXGraph runtime learners get (`assets/js/import-figures.js`).
 * The original crop stays inside the figure as the fallback when the runtime is missing or fails.
 * @param root0
 * @param root0.spec
 * @param root0.url
 * @param root0.alt
 */
export function ReconstructionPreview( {
	spec,
	url,
	alt,
}: {
	spec: FigureSpec;
	url: string;
	alt: string;
} ) {
	const encoded = JSON.stringify( spec );
	useEffect( () => {
		window.OhMyLMSFigures?.scan();
	}, [ encoded ] );
	return (
		<figure
			key={ encoded }
			className="ohmylms-import-figure"
			data-figure-spec={ encoded }
		>
			<img src={ url } alt={ alt } />
		</figure>
	);
}

/**
 * Review a figure's JSXGraph proposal: generate it with the exam converter or write its configuration,
 * edit labels, then accept it (instead of or beside the original crop) or reject it. The server
 * validates every configuration; nothing here runs code from a proposal.
 * @param root0
 * @param root0.jobId
 * @param root0.rowIndex
 * @param root0.revision
 * @param root0.figure
 * @param root0.cropUrl
 * @param root0.canGenerate
 * @param root0.dirty
 * @param root0.busy
 * @param root0.onRun
 */
export default function FigureProposalPanel( {
	jobId,
	rowIndex,
	revision,
	figure,
	cropUrl,
	canGenerate,
	dirty,
	busy,
	onRun,
}: FigureProposalPanelProps ) {
	const proposal = figure.proposal;
	const [ labels, setLabels ] = useState< FigureSpec | null >(
		proposal?.spec ?? null
	);
	const [ source, setSource ] = useState( '' );
	const [ invalid, setInvalid ] = useState< string | null >( null );
	const stored = JSON.stringify( proposal?.spec ?? null );
	useEffect( () => {
		const spec = JSON.parse( stored ) as FigureSpec | null;
		setLabels( spec );
		setSource( spec ? JSON.stringify( spec, null, 2 ) : '' );
		setInvalid( null );
	}, [ stored ] );
	const locked = busy || dirty;
	const propose = ( value: 'generate' | FigureSpec, done: string ) =>
		onRun(
			( signal ) =>
				proposeFigure(
					jobId,
					rowIndex,
					figure.id,
					revision,
					value,
					signal
				),
			done
		);
	const decide = ( decision: ProposalDisplay | 'reject', done: string ) =>
		onRun(
			( signal ) =>
				decideProposal(
					jobId,
					rowIndex,
					figure.id,
					revision,
					decision,
					signal
				),
			done
		);
	function saveSource() {
		const read = readFigureSpec( source );
		if ( 'error' in read ) {
			setInvalid( read.error );
			return;
		}
		setInvalid( null );
		propose(
			read.spec,
			__( 'Proposal saved. Accept it to use it.', 'ohmylms' )
		);
	}
	const labelled = ( labels?.objects ?? [] ).filter(
		( object ) =>
			'point' === object.type || 'string' === typeof object.label
	);
	const labelsDirty = null !== labels && JSON.stringify( labels ) !== stored;
	return (
		<fieldset className="ohmylms-figure-proposal ohmylms-import-figures">
			<legend>{ __( 'JSXGraph reconstruction', 'ohmylms' ) }</legend>
			<p role="status">
				{ proposal
					? proposalLabel( proposal )
					: __( 'No reconstruction proposed yet.', 'ohmylms' ) }
			</p>
			{ dirty && (
				<p>
					{ __(
						'Save or discard the changes to this question before working on its reconstruction.',
						'ohmylms'
					) }
				</p>
			) }
			{ proposal && cropUrl && (
				<div className="ohmylms-figure-comparison">
					<figure>
						<img src={ cropUrl } alt={ figure.alt } />
						<figcaption>
							{ __( 'Original crop', 'ohmylms' ) }
						</figcaption>
					</figure>
					<div>
						<ReconstructionPreview
							spec={ proposal.spec }
							url={ cropUrl }
							alt={ figure.alt }
						/>
						<p>{ __( 'Proposed reconstruction', 'ohmylms' ) }</p>
					</div>
				</div>
			) }
			{ labels && labelled.length > 0 && (
				<fieldset disabled={ locked }>
					<legend>{ __( 'Labels', 'ohmylms' ) }</legend>
					{ labelled.map( ( object ) => (
						<TextControl
							key={ object.id }
							label={ sprintf(
								/* translators: 1: object type such as point; 2: object ID. */
								__( 'Label of %1$s %2$s', 'ohmylms' ),
								object.type,
								object.id
							) }
							value={
								'string' === typeof object.label
									? object.label
									: ''
							}
							onChange={ ( label ) =>
								setLabels(
									withObjectLabel( labels, object.id, label )
								)
							}
						/>
					) ) }
					<Button
						variant="secondary"
						size="small"
						disabled={ locked || ! labelsDirty }
						onClick={ () =>
							propose(
								labels,
								__(
									'Labels saved. Accept the proposal to use it.',
									'ohmylms'
								)
							)
						}
					>
						{ __( 'Save labels', 'ohmylms' ) }
					</Button>
				</fieldset>
			) }
			<TextareaControl
				label={ __( 'Configuration (JSON)', 'ohmylms' ) }
				help={ __(
					'Version 1: bounds [left, top, right, bottom] and objects (point, segment, polygon, angle, circle, axes, function). Plain-text labels only; the server rejects anything else.',
					'ohmylms'
				) }
				value={ source }
				rows={ 6 }
				disabled={ locked }
				aria-invalid={ invalid ? true : undefined }
				onChange={ setSource }
			/>
			{ invalid && <p role="alert">{ invalid }</p> }
			<p>
				<Button
					variant="secondary"
					size="small"
					disabled={ locked || ! source.trim() }
					onClick={ saveSource }
				>
					{ proposal
						? __( 'Save configuration', 'ohmylms' )
						: __( 'Propose this configuration', 'ohmylms' ) }
				</Button>{ ' ' }
				{ canGenerate && (
					<Button
						variant="secondary"
						size="small"
						disabled={ locked }
						onClick={ () =>
							propose(
								'generate',
								__(
									'Reconstruction generated. Check it against the original before accepting.',
									'ohmylms'
								)
							)
						}
					>
						{ proposal
							? __(
									'Regenerate with the exam converter',
									'ohmylms'
								)
							: __(
									'Generate with the exam converter',
									'ohmylms'
								) }
					</Button>
				) }
			</p>
			{ proposal && (
				<p>
					<Button
						variant="primary"
						size="small"
						disabled={
							locked ||
							( 'accepted' === proposal.status &&
								'replace' === proposal.display )
						}
						onClick={ () =>
							decide(
								'replace',
								__(
									'Reconstruction accepted. Approve the figure again.',
									'ohmylms'
								)
							)
						}
					>
						{ __( 'Accept instead of the original', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="secondary"
						size="small"
						disabled={
							locked ||
							( 'accepted' === proposal.status &&
								'accompany' === proposal.display )
						}
						onClick={ () =>
							decide(
								'accompany',
								__(
									'Reconstruction accepted. Approve the figure again.',
									'ohmylms'
								)
							)
						}
					>
						{ __( 'Accept beside the original', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						size="small"
						isDestructive
						disabled={ locked || 'rejected' === proposal.status }
						onClick={ () =>
							decide(
								'reject',
								__(
									'Reconstruction rejected. Learners see the original crop.',
									'ohmylms'
								)
							)
						}
					>
						{ __( 'Reject', 'ohmylms' ) }
					</Button>
				</p>
			) }
		</fieldset>
	);
}
