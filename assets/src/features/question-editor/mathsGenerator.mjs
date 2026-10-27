import { topicPreset } from './mathsTopics.mjs';

/**
 * Create a bounded preset over the existing seeded template engine.
 *
 * @param {string} kind Preset name.
 * @param {number} min  Lower integer bound.
 * @param {number} max  Upper integer bound.
 * @return {Object|null} Generated authoring definition.
 */
export function mathsPreset( kind, min, max ) {
	if (
		! Number.isInteger( min ) ||
		! Number.isInteger( max ) ||
		min < 1 ||
		max > 1000 ||
		min > max
	) {
		return null;
	}
	const variable = ( name ) => ( { name, type: 'int', min, max, step: 1 } );
	let variables = [ variable( 'a' ), variable( 'b' ) ];
	let constraints = [];
	let prompt;
	let expr;
	switch ( kind ) {
		case 'addition':
			prompt = '{{a}} + {{b}} = ?';
			expr = 'a+b';
			break;
		case 'subtraction':
			prompt = '{{a}} − {{b}} = ?';
			expr = 'a-b';
			constraints = [ 'a>=b' ];
			break;
		case 'multiplication':
			prompt = '{{a}} × {{b}} = ?';
			expr = 'a*b';
			break;
		case 'division':
			variables.push( {
				name: 'c',
				type: 'expr',
				expr: 'a*b',
				places: 0,
			} );
			prompt = '{{c}} ÷ {{b}} = ?';
			expr = 'a';
			break;
		case 'percentage':
			prompt = '{{a}}% of {{b}} = ?';
			expr = 'a*b/100';
			break;
		case 'fractions':
			variables.push( {
				name: 'd',
				type: 'int',
				min: 2,
				max: 12,
				step: 1,
			} );
			prompt = '{{a}}/{{d}} + {{b}}/{{d}} = ? (decimal answer)';
			expr = '(a+b)/d';
			break;
		case 'algebra':
			variables = [
				{ name: 'a', type: 'int', min: 1, max: 9, step: 1 },
				{ name: 'b', type: 'int', min: 1, max: 9, step: 1 },
				variable( 'c' ),
			];
			prompt = '{{a}}x + {{b}} = {{a*c+b}}. Find x.';
			expr = 'c';
			break;
		case 'pythagorean':
		case 'pythagorean-leg':
			// Primitive triples from m > n, coprime, of opposite parity: a = m²-n², b = 2mn, c = m²+n².
			if ( min === max ) {
				return null;
			}
			variables = [
				{ name: 'm', type: 'int', min: min + 1, max, step: 1 },
				{ name: 'n', type: 'int', min, max: max - 1, step: 1 },
				{ name: 'a', type: 'expr', expr: 'm*m-n*n', places: 0 },
				{ name: 'b', type: 'expr', expr: '2*m*n', places: 0 },
				{ name: 'c', type: 'expr', expr: 'm*m+n*n', places: 0 },
			];
			constraints = [ 'm>n', 'gcd(m,n)=1', 'mod(m-n,2)=1' ];
			prompt =
				kind === 'pythagorean'
					? 'A right-angled triangle has shorter sides {{a}} and {{b}}. Find the hypotenuse.'
					: 'A right-angled triangle has hypotenuse {{c}} and one shorter side {{a}}. Find the other shorter side.';
			expr = kind === 'pythagorean' ? 'c' : 'b';
			break;
		case 'quadratic':
			// Build from the two solutions, so they are whole numbers and distinct by construction.
			variables = [
				{ name: 'p', type: 'int', min: -max, max, step: 1 },
				{ name: 'q', type: 'int', min: -max, max, step: 1 },
				{ name: 'b', type: 'expr', expr: '-(p+q)', places: 0 },
				{ name: 'c', type: 'expr', expr: 'p*q', places: 0 },
			];
			constraints = [
				'p!=q',
				`abs(p)>=${ min }`,
				`abs(q)>=${ min }`,
				'b!=0',
			];
			prompt = 'Solve x² {{b:+}}x {{c:+}} = 0. Give the larger solution.';
			expr = 'max(p,q)';
			break;
		case 'quadratic-repeated':
			// (x - p)² = 0: one repeated whole solution by construction.
			variables = [
				{ name: 'p', type: 'int', min: -max, max, step: 1 },
				{ name: 'b', type: 'expr', expr: '-2*p', places: 0 },
				{ name: 'c', type: 'expr', expr: 'p*p', places: 0 },
			];
			constraints = [ `abs(p)>=${ min }` ];
			prompt =
				'Solve x² {{b:+}}x {{c:+}} = 0. It has one repeated solution; give it.';
			expr = 'p';
			break;
		case 'discriminant':
			variables = [
				{ name: 'a', type: 'int', min, max, step: 1 },
				{ name: 'b', type: 'int', min: -max, max, step: 1 },
				{ name: 'c', type: 'int', min: -max, max, step: 1 },
			];
			constraints = [ 'b!=0', 'c!=0' ];
			prompt = 'Find the discriminant of {{a}}x² {{b:+}}x {{c:+}}.';
			expr = 'b*b-4*a*c';
			break;
		case 'simultaneous':
			// Pick the solution first, then the equations, so x and y are whole numbers.
			variables = [
				{ name: 'x', type: 'int', min, max, step: 1 },
				{ name: 'y', type: 'int', min, max, step: 1 },
				{ name: 'a', type: 'int', min: 1, max: 9, step: 1 },
				{ name: 'b', type: 'int', min: 1, max: 9, step: 1 },
				{ name: 'c', type: 'int', min: 1, max: 9, step: 1 },
				{ name: 'd', type: 'int', min: 1, max: 9, step: 1 },
				{ name: 'p', type: 'expr', expr: 'a*x+b*y', places: 0 },
				{ name: 'q', type: 'expr', expr: 'c*x+d*y', places: 0 },
			];
			constraints = [ 'a*d-b*c!=0' ];
			prompt =
				'{{a}}x + {{b}}y = {{p}} and {{c}}x + {{d}}y = {{q}}. Find x.';
			expr = 'x';
			break;
		default: {
			const topic = topicPreset( kind, min, max );
			if ( ! topic ) {
				return null;
			}
			variables = topic.variables;
			constraints = topic.constraints;
			prompt = topic.prompt;
			expr = topic.expr;
		}
	}
	return {
		prompt,
		settings: {
			type: 'numerical',
			answer: 0,
			answers: undefined,
			tolerance: 0.0001,
			tolerance_type: 'absolute',
			unit: '',
			maths_generator: { kind, min, max },
			template: {
				variables,
				constraints,
				set: [ { path: 'answer', expr } ],
			},
		},
	};
}
