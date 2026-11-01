/**
 * Types for the WordPress editor packages the native editors import but that are not npm
 * dependencies (webpack maps them to the wp-block-editor, wp-blocks, wp-editor, wp-edit-post and
 * wp-plugins script handles). Only the API used under assets/src/features is declared.
 */
declare module '@wordpress/blocks' {
	export interface BlockInstance {
		clientId: string;
		name: string;
		attributes: Record< string, any >;
		innerBlocks: BlockInstance[];
		isValid?: boolean;
	}
	export interface BlockType {
		name: string;
		title?: string;
		attributes?: Record< string, any >;
		[ key: string ]: any;
	}
	export function createBlock(
		name: string,
		attributes?: Record< string, any >,
		innerBlocks?: BlockInstance[]
	): BlockInstance;
	export function getBlockType( name: string ): BlockType | undefined;
	export function registerBlockType(
		name: string,
		settings: Record< string, any >
	): BlockType | undefined;
}

declare module '@wordpress/block-editor' {
	import type { ComponentType, ReactNode } from 'react';

	type Children = { children?: ReactNode };
	export const InspectorControls: ComponentType<
		Children & { group?: string }
	>;
	export const BlockControls: ComponentType<
		Children & { group?: string; controls?: unknown[] }
	>;
	export const InnerBlocks: ComponentType< Record< string, any > > & {
		Content: ComponentType< Record< string, any > >;
		ButtonBlockAppender: ComponentType< Record< string, any > >;
		DefaultBlockAppender: ComponentType< Record< string, any > >;
	};
	export const RichText: ComponentType< Record< string, any > > & {
		Content: ComponentType< Record< string, any > >;
	};
	export interface MediaUploadProps {
		onSelect: ( media: any ) => void;
		allowedTypes?: string[];
		multiple?: boolean | 'add';
		value?: number | number[];
		title?: ReactNode;
		gallery?: boolean;
		mode?: string;
		render: ( props: { open: () => void } ) => ReactNode;
	}
	export const MediaUpload: ComponentType< MediaUploadProps >;
	export const MediaUploadCheck: ComponentType<
		Children & { fallback?: ReactNode }
	>;
	export function useBlockProps(
		props?: Record< string, any >
	): Record< string, any >;
	export function useInnerBlocksProps(
		props?: Record< string, any >,
		options?: Record< string, any >
	): Record< string, any > & { children: ReactNode };
}

declare module '@wordpress/editor' {
	import type { ComponentType, ReactNode } from 'react';

	export const PluginDocumentSettingPanel: ComponentType< {
		name?: string;
		title?: ReactNode;
		className?: string;
		icon?: unknown;
		initialOpen?: boolean;
		children?: ReactNode;
	} >;
}

declare module '@wordpress/edit-post' {
	import type { ComponentType, ReactNode } from 'react';

	export const __experimentalMainDashboardButton: ComponentType< {
		children?: ReactNode;
	} >;
}

declare module '@wordpress/plugins' {
	import type { ComponentType } from 'react';

	export function registerPlugin(
		name: string,
		settings: {
			render: ComponentType< any >;
			icon?: unknown;
			scope?: string;
		}
	): unknown;
}
