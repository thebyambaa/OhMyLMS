import { useDispatch, useSelect } from '@wordpress/data';
import { homeStore } from './store';
import type { ContentType, LoadError, LoadStatus } from './types';

interface ContentTypesResult {
	readonly status: LoadStatus;
	readonly items: readonly ContentType[];
	readonly error: LoadError | null;
	/** Reload the list, for example from a Retry button. */
	readonly reload: () => void;
}

/**
 * Content types of the site with their loading state. Selecting `getContentTypes` starts the load.
 *
 * @return Status, items, error and a reload function.
 */
export function useContentTypes(): ContentTypesResult {
	const { status, items, error } = useSelect( ( select ) => {
		const store = select( homeStore );
		// Call the data selector first: it triggers the resolver that loads the list.
		const loaded = store.getContentTypes();
		return {
			items: loaded,
			status: store.getStatus(),
			error: store.getError(),
		};
	}, [] );
	const { reload } = useDispatch( homeStore );
	return { status, items, error, reload: () => void reload() };
}
