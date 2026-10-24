import traverseModule from '@babel/traverse';
import * as t from '@babel/types';

const traverse = traverseModule.default || traverseModule;

/**
 * Recovered factory-1841 components whose replacements no longer reach them.
 * Their source factories were deleted; stubbing the recovered definitions keeps
 * the dead originals out of the source build. The parity build keeps them.
 */
export const RETIRED_BINDINGS = {
	// Chapter course builder, replaced by the unified course outline editor.
	Sf: 'CourseCurriculum',
	Xl: 'CourseChapter',
	// Form quiz editor children, replaced by Gutenberg quiz and question authoring.
	qc: 'QuestionList',
	tp: 'QuizSettings',
	Jm: 'QuizSettingsFields',
	Fu: 'QuestionCanvas',
	bs: 'QuestionSettings',
	Bs: 'ChoiceOptionsEditor',
	cd: 'TextAnswerEditor',
	Vs: 'MultipleChoiceEditor',
	Ys: 'SingleChoiceEditor',
	td: 'TrueFalseEditor',
	sd: 'ShortTextEditor',
	wd: 'LongTextEditor',
	Pd: 'StatementEditor',
	Dd: 'FillInTheBlankEditor',
	tm: 'ReorderEditor',
	gm: 'MatchingEditor',
};

/**
 * Find the top-level binding of factory 1841 whose definition contains a reference.
 *
 * @param {Object} factory   Factory function path.
 * @param {Object} reference Identifier path.
 * @return {string|null} Binding name, or null for top-level statements.
 */
function ownerOf( factory, reference ) {
	let statement = reference;
	while ( statement.parentPath.node !== factory.node.body ) {
		statement = statement.parentPath;
	}
	if ( statement.isFunctionDeclaration() || statement.isClassDeclaration() ) {
		return statement.node.id?.name ?? null;
	}
	if ( statement.isVariableDeclaration() ) {
		const declarator = statement.node.declarations.find( ( node ) =>
			reference.findParent( ( parent ) => parent.node === node )
		);
		return declarator?.id.name ?? null;
	}
	return null;
}

/**
 * Bindings of factory 1841 reachable from its top-level statements.
 *
 * @param {Object} factory Factory function path.
 * @return {Set<string>} Reachable binding names.
 */
function reachable( factory ) {
	const { bindings } = factory.scope;
	const uses = new Map();
	const live = [];
	for ( const [ name, binding ] of Object.entries( bindings ) ) {
		for ( const reference of [
			...binding.referencePaths,
			...binding.constantViolations,
		] ) {
			const owner = ownerOf( factory, reference );
			if ( null === owner ) {
				live.push( name );
			} else if ( owner !== name ) {
				uses.set( owner, [ ...( uses.get( owner ) || [] ), name ] );
			}
		}
	}
	const seen = new Set();
	while ( live.length ) {
		const name = live.pop();
		if ( ! seen.has( name ) ) {
			seen.add( name );
			live.push( ...( uses.get( name ) || [] ) );
		}
	}
	return seen;
}

/**
 * Replace the retired definitions in application factory 1841 with inert stubs.
 * Fails when one is missing or still reachable from live code, so drift cannot
 * stub out a component that renders. Unreachable recovered code may still name
 * a retired binding, so the binding stays declared.
 *
 * @param {Object} ast Parsed application bundle.
 * @return {{retired: number}} Number of stubbed definitions.
 */
export function adaptRetired( ast ) {
	let factory;
	traverse( ast, {
		ObjectProperty( nodePath ) {
			if (
				'1841' === String( nodePath.node.key.value ) &&
				nodePath.get( 'value' ).isFunction()
			) {
				factory = nodePath.get( 'value' );
				nodePath.stop();
			}
		},
	} );
	if ( ! factory ) {
		throw new Error(
			'Retired adapter: application factory 1841 not found'
		);
	}
	factory.scope.crawl();
	const live = reachable( factory );
	let retired = 0;
	for ( const [ name, component ] of Object.entries( RETIRED_BINDINGS ) ) {
		const binding = factory.scope.getOwnBinding( name );
		if ( ! binding ) {
			throw new Error( `Retired adapter: ${ component } not found` );
		}
		if ( live.has( name ) ) {
			throw new Error(
				`Retired adapter: ${ component } is still reachable`
			);
		}
		const stub = t.functionExpression(
			null,
			[],
			t.blockStatement( [ t.returnStatement( t.nullLiteral() ) ] )
		);
		if ( binding.path.isFunctionDeclaration() ) {
			binding.path.replaceWith(
				t.functionDeclaration( t.identifier( name ), [], stub.body )
			);
		} else if ( binding.path.isVariableDeclarator() ) {
			binding.path.node.init = stub;
		} else {
			throw new Error(
				`Retired adapter: ${ component } has an unexpected definition`
			);
		}
		retired++;
	}
	return { retired };
}
