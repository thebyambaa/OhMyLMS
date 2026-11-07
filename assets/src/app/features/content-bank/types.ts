export interface BankTerm {
	readonly id: number;
	readonly name: string;
	/** Parent folder ID; always 0 for a tag. */
	readonly parent: number;
}

export interface BankTerms {
	readonly folders: readonly BankTerm[];
	readonly tags: readonly BankTerm[];
}

/** Folder and tag IDs one item has. */
export interface BankAssignment {
	readonly folders: readonly number[];
	readonly tags: readonly number[];
}

export type BankAssignments = Readonly< Record< number, BankAssignment > >;

export interface OrganizeRequest {
	ids: number[];
	folder_ids?: number[];
	tag_ids?: number[];
	mode: 'add' | 'replace' | 'remove';
}
