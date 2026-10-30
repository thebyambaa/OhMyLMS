import { __ } from '@wordpress/i18n';
import {
	buildRows,
	mappingProblems,
	parseCsv,
	type ParsedCsv,
} from '../../../features/curriculum/csv.mjs';

export const MAX_BYTES = 5 * 1024 * 1024;
/**
 * Bound input before parsing; reject broken UTF-8 and incomplete quoted fields.
 * @param source
 */
export function readCsv( source: string ): ParsedCsv {
	if ( new TextEncoder().encode( source ).length > MAX_BYTES ) {
		throw new Error(
			__( 'Choose a CSV file of at most 5 MB.', 'ohmylms' )
		);
	}
	const csvSource = source.replace( /^\uFEFF/, '' );
	let recordCount = 1;
	let quoted = false;
	let atStart = true;
	for ( let index = 0; index < csvSource.length; index++ ) {
		const character = csvSource[ index ];
		if ( character === '"' ) {
			if ( quoted && csvSource[ index + 1 ] === '"' ) {
				index++;
			} else if ( quoted || atStart ) {
				quoted = ! quoted;
			}
		}
		if ( ! quoted ) {
			if (
				( character === '\n' ||
					( character === '\r' &&
						csvSource[ index + 1 ] !== '\n' ) ) &&
				++recordCount > 5002
			) {
				throw new Error(
					__( 'Use at most 5000 data rows.', 'ohmylms' )
				);
			}
			atStart = [ ',', ';', '\t', '\n', '\r' ].includes(
				character ?? ''
			);
		}
	}
	if ( quoted ) {
		throw new Error( __( 'A quoted CSV field is unfinished.', 'ohmylms' ) );
	}
	const result = parseCsv( csvSource );
	if ( result.error || result.replaced ) {
		throw new Error(
			__(
				'The CSV is empty or contains invalid text. Use UTF-8.',
				'ohmylms'
			)
		);
	}
	if ( result.records.length > 5001 || result.width > 50 ) {
		throw new Error(
			__( 'Use at most 5000 data rows and 50 columns.', 'ohmylms' )
		);
	}
	return result;
}
export function mappedRows(
	parsed: ParsedCsv,
	roles: string[],
	header: boolean,
	fillDown: boolean
) {
	if ( mappingProblems( roles ).length ) {
		throw new Error(
			__(
				'Map the Skill column once and avoid duplicate column roles.',
				'ohmylms'
			)
		);
	}
	const rows = buildRows( parsed.records, roles, { header, fillDown } );
	if ( ! rows.length || rows.length > 5000 ) {
		throw new Error( __( 'Use between 1 and 5000 data rows.', 'ohmylms' ) );
	}
	return rows;
}
