import { useRequest } from '../../hooks/useRequest';
import { listBankTerms } from './api';
import { folderPaths } from './model';
import type { BankTerms } from './types';

const NO_TERMS: BankTerms = { folders: [], tags: [] };

/**
 * Every Content Bank folder and tag, with the folders as paths for pickers.
 *
 * @return Terms, folder paths, any load error and a reload function.
 */
export function useBankTerms() {
	const request = useRequest( listBankTerms );
	const terms = request.data ?? NO_TERMS;
	return {
		terms,
		paths: folderPaths( terms.folders ),
		error: request.error,
		reload: request.reload,
	};
}
