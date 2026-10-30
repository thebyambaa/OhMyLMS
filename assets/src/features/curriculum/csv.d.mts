/** Type boundary for the existing pure CSV utilities. No runtime factories or UI are imported. */
export interface CsvRow {
	line: number;
	[ key: string ]: string | number;
}
export interface ParsedCsv {
	delimiter: string;
	width: number;
	records: string[][];
	error?: string;
	replaced?: boolean;
}
export const ROLES: { id: string; label: string; hint: string }[];
export const ROLE_IDS: string[];
export function parseCsv( text: string ): ParsedCsv;
export function guessRoles( headers: string[] ): string[];
export function mappingProblems( roles: string[] ): string[];
export function buildRows(
	records: string[][],
	roles: string[],
	options?: { header?: boolean; fillDown?: boolean }
): CsvRow[];
export function templateCsv(): string;
