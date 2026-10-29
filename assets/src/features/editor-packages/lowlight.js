/** npm-backed syntax support, loaded only with the recovered admin application. */
import { all, common, createLowlight } from 'lowlight';

window.ohmylmsEditorPackages = {
	...window.ohmylmsEditorPackages,
	lowlight: { all, common, createLowlight },
};
