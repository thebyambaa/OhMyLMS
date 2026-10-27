import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { transformSync } from '@babel/core';
import { blankIds } from '../../assets/src/features/question-editor/interactiveModel.mjs';
import { numbersToList, listToNumbers } from '../../assets/src/features/question-bank/model.mjs';
import { dropdownAnswers, dropdownSettings, renameDropdownChoice } from '../../assets/src/features/question-editor/dropdownModel.mjs';

const scope = {
	createElement: ( type, props, ...children ) => ( { type, props, children } ),
	Fragment: 'fragment',
	__: text => text,
	sprintf: ( text, id ) => text.replace( '%s', id ),
	useState: () => [ 0, () => {} ],
	RichContentControl: 'rich',
	Button: 'button',
	SelectControl: 'select',
	CheckboxControl: 'checkbox',
	TextControl: 'text',
	TextareaControl: 'textarea',
	StructuredEditor: 'parts',
	blankIds,
	numbersToList,
	listToNumbers,
	dropdownAnswers,
	dropdownSettings,
	renameDropdownChoice,
};
function editor( file, name ) {
	const source = fs.readFileSync( `assets/src/features/question-editor/${ file }.jsx`, 'utf8' )
		.replace( /^import [\s\S]*?;$/gm, '' ).replaceAll( 'export function', 'function' );
	const { code } = transformSync( source, { configFile: false, babelrc: false,
		presets: [ [ '@babel/preset-react', { pragma: 'createElement', pragmaFrag: 'Fragment' } ] ],
	} );
	return new Function( ...Object.keys(scope), `${ code };return ${ name };` )( ...Object.values(scope) );
}
const nodes = tree => [ tree, ...(tree?.children || []).flat(Infinity)
	.filter(child => child && typeof child === 'object').flatMap(nodes) ];

test( 'main statement replaces secondary editors with instructions and keeps answer controls', () => {
	const cases = [
		[ editor('InteractiveEditors', 'DropdownBlanksEditor'), {text:'Choose {1}', slots:[{id:'1',choices:['yes'],answer:'yes'}]} ],
		[ editor('InteractiveEditors', 'MultiBlankEditor'), {text:'Fill {a}', blanks:{a:{kind:'numerical',answer:4,tolerance:0.1}}} ],
		[ editor('ExtendedEditor', 'ExtendedEditor'), {passage:'Reading text',parts:[]} ],
	];
	for ( const [ render, value ] of cases ) {
		const props = { value, type:'passage', onChange:()=>{} };
		assert.equal(nodes(render(props)).filter(node=>node.type==='rich' && ['Sentence','Text','Reading passage'].includes(node.props.label)).length,1);
		const main = nodes(render({...props,mainStatement:true}));
		assert.equal(main.filter(node=>node.type==='rich' && ['Sentence','Text','Reading passage'].includes(node.props.label)).length,0);
		assert.ok(main.some(node=>node.type==='p' && node.children.join('').includes('Question field')));
		assert.ok(main.some(node=>['fieldset','parts'].includes(node.type)));
	}
} );
