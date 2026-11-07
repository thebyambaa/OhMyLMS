import { Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ListTableSelect } from '../../../ui/ListTable';
import type {
	ListTableBulkActions,
	ListTableColumn,
} from '../../../ui/ListTable';
import { useRequest } from '../../hooks/useRequest';
import { createBankTerm, organizeItems, readAssignments } from './api';
import { bulkRequest, organizationLabels } from './model';
import { useBankTerms } from './useBankTerms';
import type { BankAssignments } from './types';

interface Options {
	/** Current folder filter (a term ID as text, or `''`). */
	folder: string;
	/** Current tag filter. */
	tag: string;
	/** Apply a new folder and tag filter. */
	onFilter: ( folder: string, tag: string ) => void;
	/** IDs of the items on the page, to show what each has. */
	ids: readonly number[];
}

/**
 * Folders and tags for a Content Bank list: the filter selects, bulk actions, an organization column and a
 * form that creates folders and tags. Lists pass `folder` and `tag` to their REST request as `bank_folder`
 * and `bank_tag`.
 *
 * @param options          Current filters and the items shown.
 * @param options.folder   Current folder filter.
 * @param options.tag      Current tag filter.
 * @param options.onFilter Applies a new folder and tag filter.
 * @param options.ids      IDs of the items on the page.
 */
export function useBankControls( { folder, tag, onFilter, ids }: Options ) {
	const {
		terms,
		paths,
		error: termsError,
		reload: reloadTerms,
	} = useBankTerms();
	const key = ids.join( ',' );
	const assignmentRequest = useRequest(
		useCallback(
			( signal: AbortSignal ) =>
				ids.length
					? readAssignments( ids, signal )
					: Promise.resolve( {} as BankAssignments ),
			// `key` stands for `ids`.
			// eslint-disable-next-line react-hooks/exhaustive-deps
			[ key ]
		),
		{ key, keepData: true }
	);
	const assignments = assignmentRequest.data ?? {};
	const [ status, setStatus ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const filters = (
		<>
			<ListTableSelect
				label={ __( 'Filter by folder', 'ohmylms' ) }
				value={ folder }
				options={ [
					{ value: '', label: __( 'All folders', 'ohmylms' ) },
					...paths.map( ( item ) => ( {
						value: String( item.id ),
						label: item.label,
					} ) ),
				] }
				onChange={ ( value ) => onFilter( value, tag ) }
			/>
			<ListTableSelect
				label={ __( 'Filter by tag', 'ohmylms' ) }
				value={ tag }
				options={ [
					{ value: '', label: __( 'All tags', 'ohmylms' ) },
					...terms.tags.map( ( item ) => ( {
						value: String( item.id ),
						label: item.name,
					} ) ),
				] }
				onChange={ ( value ) => onFilter( folder, value ) }
			/>
		</>
	);

	const bulkActions: ListTableBulkActions< unknown > = {
		actions: [
			...paths.map( ( item ) => ( {
				value: `add-folder:${ item.id }`,
				label: sprintf(
					/* translators: %s: folder path. */
					__( 'Add to folder: %s', 'ohmylms' ),
					item.label
				),
			} ) ),
			...terms.tags.map( ( item ) => ( {
				value: `add-tag:${ item.id }`,
				label: sprintf(
					/* translators: %s: tag name. */
					__( 'Add tag: %s', 'ohmylms' ),
					item.name
				),
			} ) ),
			{
				value: 'clear-folders',
				label: __( 'Remove from all folders', 'ohmylms' ),
			},
			{ value: 'clear-tags', label: __( 'Remove all tags', 'ohmylms' ) },
		],
		onApply: async ( action, keys ) => {
			const request = bulkRequest( action, keys.map( Number ) );
			if ( ! request ) {
				return false;
			}
			setError( '' );
			try {
				const result = await organizeItems( request );
				setStatus(
					sprintf(
						/* translators: %d: number of items. */
						_n(
							'Updated %d item.',
							'Updated %d items.',
							result.updated.length,
							'ohmylms'
						),
						result.updated.length
					) +
						( result.skipped
							? ' ' +
								sprintf(
									/* translators: %d: number of items. */
									__( '%d could not be changed.', 'ohmylms' ),
									result.skipped
								)
							: '' )
				);
				assignmentRequest.reload();
				return true;
			} catch ( caught ) {
				setStatus( '' );
				setError(
					caught instanceof Error
						? caught.message
						: __( 'Could not update the items.', 'ohmylms' )
				);
				return false;
			}
		},
	};

	const column: ListTableColumn< { id: number } > = {
		id: 'organization',
		label: __( 'Folders and tags', 'ohmylms' ),
		render: ( row ) => {
			const labels = organizationLabels( terms, assignments[ row.id ] );
			const all = [
				...labels.folders,
				...labels.tags.map( ( t ) => `#${ t }` ),
			];
			return all.length ? all.join( ', ' ) : '—';
		},
	};

	/**
	 * Create a folder or tag.
	 *
	 * @param kind   `folder` or `tag`.
	 * @param name   Name.
	 * @param parent Parent folder ID (folders only).
	 */
	const addTerm = async (
		kind: 'folder' | 'tag',
		name: string,
		parent: number
	) => {
		setError( '' );
		try {
			await createBankTerm( kind, name, parent );
			setStatus(
				'folder' === kind
					? __( 'Folder created.', 'ohmylms' )
					: __( 'Tag created.', 'ohmylms' )
			);
			reloadTerms();
			return true;
		} catch ( caught ) {
			setError(
				caught instanceof Error
					? caught.message
					: __( 'Could not create it.', 'ohmylms' )
			);
			return false;
		}
	};

	const notices = (
		<>
			{ termsError && (
				<Notice status="error" isDismissible={ false }>
					{ termsError }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ status && (
				<Notice
					status="success"
					onRemove={ () => setStatus( '' ) }
					politeness="polite"
				>
					{ status }
				</Notice>
			) }
		</>
	);

	return {
		terms,
		paths,
		filters,
		bulkActions,
		column,
		notices,
		addTerm,
	};
}
