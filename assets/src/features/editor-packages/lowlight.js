/** npm-backed syntax support, loaded only with the recovered admin application. */
import { all, common, createLowlight } from 'lowlight';
import highlight from 'highlight.js/lib/core';

window.ohmylmsEditorPackages = {
	...window.ohmylmsEditorPackages,
	lowlight: { all, common, createLowlight },
	highlight,
};
