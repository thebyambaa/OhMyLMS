/**
 * `@wordpress/data` for the native editors (tsconfig.json maps the package here). The package's
 * own types only know stores by descriptor; these modules address WordPress' editor stores and
 * `ohmylms/paper` by name, which the package types as `never`/`unknown`. This adds string
 * overloads for exactly the selectors and actions used under assets/src/features; everything else
 * is the package's own export.
 */
import type {
	AnyConfig,
	CurriedSelectorsOf,
	DataRegistry,
	DispatchReturn,
	StoreDescriptor,
	UseDispatchReturn,
} from '../../../../node_modules/@wordpress/data/build-types/types';
import type { BlockInstance } from '@wordpress/blocks';

export * from '../../../../node_modules/@wordpress/data/build-types/index';

export interface EditorSelectors {
	getCurrentPostId(): number;
	getCurrentPostType(): string;
	getEditedPostAttribute( attribute: string ): any;
	isEditedPostDirty(): boolean;
	isPostSavingLocked(): boolean;
	isSavingPost(): boolean;
	didPostSaveRequestFail(): boolean;
}

export interface BlockEditorSelectors {
	getBlock( clientId: string ): BlockInstance | null;
	getBlocks( rootClientId?: string ): BlockInstance[];
	getBlockAttributes( clientId: string ): Record< string, any > | null;
	/** `rootClientId` is the pre-5.9 signature; current WordPress ignores it. */
	getBlockIndex( clientId: string, rootClientId?: string ): number;
	getBlockName( clientId: string ): string | null;
	getBlockOrder( rootClientId?: string ): string[];
	getBlockRootClientId( clientId: string ): string | null;
	getClientIdsWithDescendants(): string[];
}

export interface CoreSelectors {
	getEntityRecord(
		kind: string,
		name: string,
		key: number | string
	): Record< string, any > | null | undefined;
	getMedia( id: number ): Record< string, any > | null | undefined;
}

export interface PaperSelectors {
	isPaperView(): boolean;
	showsAnswers(): boolean;
	getQuestionType( clientId: string ): string;
	getQuestionMarks( clientId: string ): number;
}

export interface EditorActions {
	editPost(
		edits: Record< string, unknown >,
		options?: { undoIgnore?: boolean }
	): void;
	savePost( options?: Record< string, unknown > ): Promise< void >;
	lockPostSaving( lockName: string ): void;
	unlockPostSaving( lockName: string ): void;
}

export interface BlockEditorActions {
	insertBlock(
		block: BlockInstance,
		index?: number,
		rootClientId?: string,
		updateSelection?: boolean
	): void;
	insertBlocks(
		blocks: BlockInstance | BlockInstance[],
		index?: number,
		rootClientId?: string,
		updateSelection?: boolean
	): void;
	moveBlockToPosition(
		clientId: string,
		fromRootClientId: string,
		toRootClientId: string,
		index: number
	): void;
	removeBlock( clientId: string, selectPrevious?: boolean ): void;
	replaceBlocks(
		clientIds: string | string[],
		blocks: BlockInstance | BlockInstance[]
	): void;
	selectBlock( clientId: string, initialPosition?: number | null ): void;
}

export interface PaperActions {
	setView( view: boolean ): void;
	setAnswers( answers: boolean ): void;
	setQuestion( clientId: string, questionType: string, marks: number ): void;
}

export interface EditPostActions {
	openGeneralSidebar( name: string ): void;
}

interface NamedSelectors {
	core: CoreSelectors;
	'core/editor': EditorSelectors;
	'core/block-editor': BlockEditorSelectors;
	'ohmylms/paper': PaperSelectors;
}

interface NamedActions {
	'core/editor': EditorActions;
	'core/block-editor': BlockEditorActions;
	'core/edit-post': EditPostActions;
	'ohmylms/paper': PaperActions;
}

export interface NamedSelect {
	< N extends keyof NamedSelectors >( store: N ): NamedSelectors[ N ];
	< S extends StoreDescriptor< AnyConfig > >(
		store: S
	): CurriedSelectorsOf< S >;
}

export declare const select: NamedSelect;

export declare function dispatch< N extends keyof NamedActions >(
	store: N
): NamedActions[ N ];
export declare function dispatch<
	S extends StoreDescriptor< AnyConfig > | string,
>( store: S ): DispatchReturn< S >;

export declare function useSelect< T >(
	mapSelect: ( select: NamedSelect, registry: DataRegistry ) => T,
	deps?: unknown[]
): T;

export declare function useDispatch< N extends keyof NamedActions >(
	store: N
): NamedActions[ N ];
export declare function useDispatch<
	S extends undefined | string | StoreDescriptor< AnyConfig > = undefined,
>( store?: S ): UseDispatchReturn< S >;
