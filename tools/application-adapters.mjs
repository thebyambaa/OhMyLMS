/** Explicit integration points into the recovered application. Fail on drift. */
import traverseModule from '@babel/traverse';
import { parseExpression } from '@babel/parser';
import * as t from '@babel/types';
const traverse = traverseModule.default || traverseModule;
export function adaptApplication( ast ) {
	const hits = {
		routes: 0,
		membership: 0,
		membershipValidation: 0,
		questions: 0,
		lessons: 0,
		lessonEditors: 0,
	};
	const sdk = ( method ) =>
		t.memberExpression(
			t.memberExpression(
				t.memberExpression(
					t.identifier( 'window' ),
					t.identifier( 'ohmylms' )
				),
				t.identifier( 'extensions' )
			),
			t.identifier( method )
		);
	traverse( ast, {
		VariableDeclarator( p ) {
			if (
				p.node.id.name !== 'B8' ||
				! t.isFunctionExpression( p.node.init )
			)
				return;
			p.traverse( {
				VariableDeclarator( rule ) {
					if (
						rule.node.id.name !== 'C' ||
						! t.isFunctionExpression( rule.node.init )
					)
						return;
					rule.node.init = parseExpression(
						'function(plan){var errors=window.ohmylms.extensions.validateMembership(plan,b.__);E(errors);return Object.keys(errors).length===0;}'
					);
					hits.membershipValidation++;
					rule.skip();
				},
			} );
		},
		ArrayExpression( p ) {
			if (
				! p.node.elements.some(
					( e ) =>
						t.isObjectExpression( e ) &&
						e.properties.some(
							( v ) =>
								v.key?.name === 'path' &&
								v.value?.value === '/memberships'
						)
				)
			)
				return;
			// Old Students URL: keep bookmarks working by forwarding to Account Hub.
			const hub = p.node.elements.findIndex(
				( e ) =>
					t.isObjectExpression( e ) &&
					e.properties.some(
						( v ) =>
							v.key?.name === 'path' &&
							v.value?.value === '/accounthub'
					)
			);
			if ( hub < 0 )
				throw new Error(
					'Application adapter drift: /accounthub route not found'
				);
			p.node.elements.splice(
				hub + 1,
				0,
				parseExpression(
					'({path:"/students",element:function(){React.useEffect(function(){var query=window.location.hash.split("?")[1];window.location.replace("#/accounthub"+(query?"?"+query:""));},[]);return null;}})'
				)
			);
			p.replaceWith(
				t.callExpression( sdk( 'extendRoutes' ), [ p.node ] )
			);
			hits.routes++;
			p.skip();
		},
		CallExpression( p ) {
			const n = p.node;
			if (
				t.isMemberExpression( n.callee ) &&
				n.callee.property.name === 'createElement' &&
				t.isIdentifier( n.arguments[ 0 ], { name: 'kr' } )
			) {
				p.replaceWith(
					t.callExpression( sdk( 'lessonEditor' ), [
						t.cloneNode( n.arguments[ 1 ], true ),
						n,
					] )
				);
				hits.lessonEditors++;
				p.skip();
				return;
			}
			if (
				t.isMemberExpression( n.callee ) &&
				n.callee.property.name === 'createElement' &&
				t.isIdentifier( n.arguments[ 0 ], { name: 'w8' } )
			) {
				const extension = parseExpression(
					'window.ohmylms.extensions.membershipPanels({plan:l,onChange:c.updateMembershipPlan})'
				);
				p.replaceWith(
					t.callExpression(
						t.memberExpression(
							t.identifier( 'React' ),
							t.identifier( 'createElement' )
						),
						[
							t.memberExpression(
								t.identifier( 'React' ),
								t.identifier( 'Fragment' )
							),
							t.nullLiteral(),
							n,
							extension,
						]
					)
				);
				hits.membership++;
				p.skip();
				return;
			}
			if (
				t.isIdentifier( n.callee, { name: '_m' } ) &&
				t.isIdentifier( n.arguments[ 0 ], { name: 'ym' } )
			) {
				p.replaceWith(
					t.sequenceExpression( [
						n,
						parseExpression(
							'window.ohmylms.extensions.questionTypes(T.default).forEach(bm)'
						),
					] )
				);
				hits.questions++;
				p.skip();
				return;
			}
			if (
				t.isMemberExpression( n.callee ) &&
				n.callee.property.name === 'reduce' &&
				t.isCallExpression( n.callee.object )
			) {
				const text = n.callee.object;
				// The lesson picker groups its concatenated menu definitions by type.
				if (
					! t.isMemberExpression( text.callee ) ||
					text.callee.property.name !== 'concat'
				)
					return;
				const first = text.callee.object;
				if (
					! t.isArrayExpression( first ) ||
					! first.elements.some(
						( e ) =>
							t.isObjectExpression( e ) &&
							e.properties.some(
								( v ) =>
									v.key?.name === 'lessonType' &&
									v.value?.value === 'text'
							)
					)
				)
					return;
				const blockLesson = first.elements.find(
					( element ) =>
						t.isObjectExpression( element ) &&
						element.properties.some(
							( property ) =>
								property.key?.name === 'lessonType' &&
								property.value?.value === 'text'
						)
				);
				for ( const [ key, label ] of [
					[ 'title', 'Block lesson' ],
					[
						'description',
						'Create content with the WordPress block editor',
					],
				] ) {
					const property = blockLesson.properties.find(
						( item ) => item.key?.name === key
					);
					if ( ! t.isCallExpression( property?.value ) ) {
						throw new Error(
							`Lesson picker adapter drift: ${ key }`
						);
					}
					property.value.arguments[ 0 ] = t.stringLiteral( label );
				}
				n.callee.object = t.callExpression(
					t.memberExpression( text, t.identifier( 'concat' ) ),
					[
						parseExpression(
							'window.ohmylms.extensions.lessonTypes(o)'
						),
					]
				);
				hits.lessons++;
			}
		},
	} );
	if (
		hits.routes !== 1 ||
		hits.membership !== 1 ||
		hits.membershipValidation !== 1 ||
		hits.questions < 1 ||
		hits.lessons !== 1 ||
		hits.lessonEditors < 1
	)
		throw new Error(
			'Application integration points changed: ' + JSON.stringify( hits )
		);
	return hits;
}
