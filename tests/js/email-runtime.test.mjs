import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { parse } from '@babel/parser';
import generatorModule from '@babel/generator';
import { adaptEmailRuntime } from '../../tools/email-runtime-adapters.mjs';

const generate = generatorModule.default || generatorModule;
test( 'template previews wait for libraries while certificate startup avoids editor imports', async () => {
	const ast = parse( `({1841:(e,t,n)=>{
    var FP=n(49050),NP=n(44098),DP=n.n(NP);
    var lO=function(props){return DP()(FP.JsonToMjml(props)).html}, Preview=memo(lO);
    var ZO=function(props){return DP()(FP.JsonToMjml(props)).html}, Gallery=memo(ZO);
    n(47909);n(43203);t.Preview=Preview;t.Gallery=Gallery;
  }})` );
	adaptEmailRuntime( ast );
	let ready = false;
	const requests = [];
	const require = ( id ) => {
		requests.push( id );
		if ( id === 49050 ) return { JsonToMjml: ( data ) => data.content };
		assert.equal( id, 44098 );
		assert.equal(
			ready,
			true,
			'Renderer requested before its chunk was loaded'
		);
		return ( content ) => ( { html: `<html>${ content }</html>` } );
	};
	require.n = ( module ) => () => module;
	require.e = async ( id ) => {
		assert.equal( id, 355 );
		ready = true;
	};
	const entries = vm.runInNewContext( generate( ast ).code, {
		memo: ( component ) => component,
		window: {
			ohmylms: {
				extensions: {
					lazyEmailComponent:
						( component, load ) => async ( props ) => {
							await load();
							return component( props );
						},
				},
			},
		},
	} );
	const exports = {};
	entries[ 1841 ]( {}, exports, require );
	assert.deepEqual( requests, [ 49050 ] );
	assert.equal(
		await exports.Preview( { content: 'Preview text' } ),
		'<html>Preview text</html>'
	);
	assert.equal(
		await exports.Gallery( { content: 'Saved template' } ),
		'<html>Saved template</html>'
	);
	assert.deepEqual( requests, [ 49050, 44098, 44098 ] );
} );
