// Public WordPress script externals supplied by core, never bundled copies.
declare module '@wordpress/blocks' {
	export interface BlockInstance {
		clientId: string;
		name: string;
		attributes: Record< string, unknown >;
		innerBlocks: BlockInstance[];
		isValid?: boolean;
	}
	/** @param content Serialized block markup. */
	export function parse( content: string ): BlockInstance[];
	/** @param blocks Parsed block values. */
	export function serialize( blocks: BlockInstance[] ): string;
	/** @param name Registered block name. */
	export function getBlockType( name: string ): unknown;
}
declare module '@wordpress/block-library' {
	export function registerCoreBlocks(): void;
}
declare module '@wordpress/block-editor' {
	import type { ComponentType, PropsWithChildren } from 'react';
	import type { BlockInstance } from '@wordpress/blocks';
	export const BlockEditorProvider: ComponentType<
		PropsWithChildren< {
			value: BlockInstance[];
			onInput: ( blocks: BlockInstance[] ) => void;
			onChange: ( blocks: BlockInstance[] ) => void;
			settings?: Record< string, unknown >;
		} >
	>;
	export const BlockList: ComponentType;
	export const BlockInspector: ComponentType;
	export const BlockToolbar: ComponentType;
	export const BlockTools: ComponentType< PropsWithChildren >;
	export const WritingFlow: ComponentType< PropsWithChildren >;
	export const Inserter: ComponentType;
}
