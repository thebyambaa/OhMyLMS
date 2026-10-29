/** Shared npm editor runtime. React is supplied by WordPress. */
import * as core from '@tiptap/core';
import * as react from '@tiptap/react';
import * as state from '@tiptap/pm/state';
import * as model from '@tiptap/pm/model';
import * as view from '@tiptap/pm/view';
import * as transform from '@tiptap/pm/transform';
import * as commands from '@tiptap/pm/commands';
import * as keymap from '@tiptap/pm/keymap';
import * as schemaList from '@tiptap/pm/schema-list';
import * as tables from '@tiptap/pm/tables';
import * as history from '@tiptap/pm/history';
import * as gapcursor from '@tiptap/pm/gapcursor';
import * as dropcursor from '@tiptap/pm/dropcursor';
import * as starterKit from '@tiptap/starter-kit';
import * as suggestion from '@tiptap/suggestion';
import * as table from '@tiptap/extension-table';
import * as tableHeader from '@tiptap/extension-table-header';
import * as tableRow from '@tiptap/extension-table-row';
import * as superscript from '@tiptap/extension-superscript';
import * as subscript from '@tiptap/extension-subscript';
import * as underline from '@tiptap/extension-underline';
import * as floatingMenu from '@tiptap/extension-floating-menu';
import * as bubbleMenu from '@tiptap/extension-bubble-menu';
import * as typography from '@tiptap/extension-typography';
import * as horizontalRule from '@tiptap/extension-horizontal-rule';
import * as highlight from '@tiptap/extension-highlight';
import * as placeholder from '@tiptap/extension-placeholder';
import * as textStyle from '@tiptap/extension-text-style';
import * as orderedList from '@tiptap/extension-ordered-list';
import * as taskList from '@tiptap/extension-task-list';
import * as paragraph from '@tiptap/extension-paragraph';
import * as codeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import * as textAlignPackage from '@tiptap/extension-text-align';
import * as taskItem from '@tiptap/extension-task-item';
import * as dropcursorExtension from '@tiptap/extension-dropcursor';
import * as documentExtension from '@tiptap/extension-document';
import * as image from '@tiptap/extension-image';
import * as characterCount from '@tiptap/extension-character-count';
import * as focus from '@tiptap/extension-focus';
import * as color from '@tiptap/extension-color';
import * as heading from '@tiptap/extension-heading';
import * as link from '@tiptap/extension-link';
import * as fontFamily from '@tiptap/extension-font-family';
import * as bulletList from '@tiptap/extension-bullet-list';
import * as blockquote from '@tiptap/extension-blockquote';
import * as codeBlock from '@tiptap/extension-code-block';
import { iconExports } from './icons';

// Preserve the recovered schema's default, which differs from current v2 defaults.
const alignedText = textAlignPackage.default.configure( {
	defaultAlignment: 'left',
} );
const textAlign = {
	...textAlignPackage,
	default: alignedText,
	TextAlign: alignedText,
};
const modules = {
	core,
	react,
	state,
	model,
	view,
	transform,
	commands,
	keymap,
	schemaList,
	tables,
	history,
	gapcursor,
	dropcursor,
	starterKit,
	suggestion,
	table,
	tableHeader,
	tableRow,
	superscript,
	subscript,
	underline,
	floatingMenu,
	bubbleMenu,
	typography,
	horizontalRule,
	highlight,
	placeholder,
	textStyle,
	orderedList,
	taskList,
	paragraph,
	codeBlockLowlight,
	textAlign,
	taskItem,
	dropcursorExtension,
	documentExtension,
	image,
	characterCount,
	focus,
	color,
	heading,
	link,
	fontFamily,
	bulletList,
	blockquote,
	codeBlock,
	icons: iconExports,
};
window.ohmylmsEditorPackages = {
	...window.ohmylmsEditorPackages,
	tiptap: modules,
};
