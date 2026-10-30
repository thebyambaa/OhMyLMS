/** Types for registry.mjs, the public extension API v1 registry (docs/EXTENSIONS.md). */

export declare const API_VERSION: 1;

export type ExtensionKind =
	| 'admin-page'
	| 'editor-panel'
	| 'question-editor'
	| 'lesson-editor'
	| 'slash-command'
	| 'slot'
	| 'membership-settings'
	| 'checkout-field'
	| 'integration';

export declare const KINDS: readonly ExtensionKind[];

/** Public, JSON-safe data a host passes to slots, panels and pages. */
export type ExtensionContext = Record< string, unknown >;

export interface ExtensionDefinition {
	readonly label: string;
	/** React component. Required for every kind except `slash-command`. */
	readonly render?: unknown;
	/** Editor action. Required for `slash-command`. */
	readonly action?: unknown;
	readonly apiVersion?: number;
	readonly priority?: number;
	readonly enabled?: boolean;
	readonly slot?: string;
	readonly description?: string;
	readonly when?: ( context: ExtensionContext ) => boolean;
	readonly [ key: string ]: unknown;
}

export interface ExtensionEntry extends ExtensionDefinition {
	readonly id: string;
	readonly kind: ExtensionKind;
	readonly priority: number;
	readonly enabled: boolean;
}

export type Unregister = () => void;
type Register = ( id: string, definition: ExtensionDefinition ) => Unregister;

export interface ExtensionRegistry {
	readonly version: 1;
	register(
		kind: ExtensionKind,
		id: string,
		definition: ExtensionDefinition
	): Unregister;
	/** Enabled entries of a kind, ordered by priority, then id. */
	list( kind: ExtensionKind ): ExtensionEntry[];
	/** Any entry, including disabled ones. */
	get( kind: ExtensionKind, id: string ): ExtensionEntry | undefined;
	subscribe( listener: () => void ): Unregister;
	getRevision(): number;
	registerAdminPage: Register;
	registerEditorPanel: Register;
	registerQuestionEditor: Register;
	registerLessonEditor: Register;
	registerSlashCommand: Register;
	registerSlot: Register;
	registerMembershipSettings: Register;
	registerCheckoutField: Register;
	registerIntegration: Register;
}

export declare function createRegistry(): ExtensionRegistry;
