/**
 * Topic presets for the maths generator that are built from a construction: the numbers that make the
 * answer exact are drawn first, and the question is derived from them. Each returns the variables,
 * constraints, prompt and answer expression for the shared seeded template engine, or null when the
 * range is too small for the topic.
 *
 * @param {string} kind Preset name.
 * @param {number} min  Lower integer bound.
 * @param {number} max  Upper integer bound.
 * @return {Object|null|undefined} Definition; null for an unusable range; undefined for an unknown kind.
 */
export function topicPreset( kind, min, max ) {
	const int = ( name, low, high, step = 1 ) => ( {
		name,
		type: 'int',
		min: low,
		max: high,
		step,
	} );
	const expr = ( name, formula ) => ( {
		name,
		type: 'expr',
		expr: formula,
		places: 0,
	} );
	// Several topics need two different numbers in the range.
	const needsTwo = min === max;
	switch ( kind ) {
		case 'equivalent-fraction':
			// a/b = ?/c with c = b*k, so the missing numerator is a*k.
			return needsTwo
				? null
				: {
						variables: [
							int( 'a', min, max - 1 ),
							int( 'b', min + 1, max ),
							int( 'k', 2, 9 ),
							expr( 'c', 'b*k' ),
						],
						constraints: [ 'a<b' ],
						prompt: '{{a}}/{{b}} = ?/{{c}}. Find the missing numerator.',
						expr: 'a*k',
					};
		case 'simplify-fraction':
			// a/b in lowest terms multiplied up by k; the answer is the simplest denominator.
			return needsTwo
				? null
				: {
						variables: [
							int( 'a', min, max - 1 ),
							int( 'b', min + 1, max ),
							int( 'k', 2, 9 ),
							expr( 'n', 'a*k' ),
							expr( 'd', 'b*k' ),
						],
						constraints: [ 'a<b', 'gcd(a,b)=1' ],
						prompt: 'Simplify {{n}}/{{d}}. What is the denominator of the simplest form?',
						expr: 'b',
					};
		case 'unlike-fractions':
			// Two different denominators need three numbers in the range.
			return max - min < 2
				? null
				: {
						variables: [
							int( 'a', min, max - 1 ),
							int( 'b', min + 1, max ),
							int( 'c', min, max - 1 ),
							int( 'd', min + 1, max ),
							expr( 'p', 'b*d' ),
						],
						constraints: [ 'a<b', 'c<d', 'b!=d' ],
						prompt: '{{a}}/{{b}} + {{c}}/{{d}} = ?/{{p}}. Find the missing numerator.',
						expr: 'a*d+c*b',
					};
		case 'percent-increase':
			// A price of 20k and a percentage in steps of 5 keep the answer a whole number.
			return {
				variables: [
					int( 'k', min, max ),
					int( 'r', 5, 50, 5 ),
					expr( 'o', '20*k' ),
				],
				constraints: [],
				prompt: 'A price of {{o}} is increased by {{r}}%. What is the new price?',
				expr: 'o*(100+r)/100',
			};
		case 'percent-decrease':
			return {
				variables: [
					int( 'k', min, max ),
					int( 'r', 5, 50, 5 ),
					expr( 'o', '20*k' ),
				],
				constraints: [],
				prompt: 'A price of {{o}} is reduced by {{r}}%. What is the new price?',
				expr: 'o*(100-r)/100',
			};
		case 'reverse-percentage':
			return {
				variables: [
					int( 'k', min, max ),
					int( 'r', 5, 50, 5 ),
					expr( 'o', '20*k' ),
					expr( 'n', 'o*(100-r)/100' ),
				],
				constraints: [],
				prompt: 'After a {{r}}% discount a coat costs {{n}}. What was the original price?',
				expr: 'o',
			};
		case 'simple-interest':
			return {
				variables: [
					int( 'k', min, max ),
					int( 'r', 1, 10 ),
					int( 't', 1, 5 ),
					expr( 'p', '100*k' ),
				],
				constraints: [],
				prompt: '{{p}} is invested at {{r}}% simple interest a year for {{t}} years. How much interest is earned?',
				expr: 'k*r*t',
			};
		case 'gradient':
			// Start from the gradient m and the run d, so the rise is whole.
			return {
				variables: [
					int( 'a', -max, max ),
					int( 'b', -max, max ),
					int( 'm', -9, 9 ),
					int( 'd', min, max ),
					expr( 'c', 'a+d' ),
					expr( 'f', 'b+m*d' ),
				],
				constraints: [ 'm!=0' ],
				prompt: 'Find the gradient of the line through ({{a}}, {{b}}) and ({{c}}, {{f}}).',
				expr: 'm',
			};
		case 'y-intercept':
			return {
				variables: [
					int( 'm', -9, 9 ),
					int( 'a', min, max ),
					int( 'b', -max, max ),
				],
				constraints: [ 'm!=0' ],
				prompt: 'A line with gradient {{m}} passes through ({{a}}, {{b}}). Find its y-intercept.',
				expr: 'b-m*a',
			};
		case 'midpoint':
			// Points a distance h either side of the midpoint, so the midpoint is whole.
			return {
				variables: [
					int( 'u', -max, max ),
					int( 'v', -max, max ),
					int( 'h', min, max ),
					int( 'j', min, max ),
					expr( 'a', 'u-h' ),
					expr( 'c', 'u+h' ),
					expr( 'b', 'v-j' ),
					expr( 'f', 'v+j' ),
				],
				constraints: [],
				prompt: 'Find the x-coordinate of the midpoint of ({{a}}, {{b}}) and ({{c}}, {{f}}).',
				expr: 'u',
			};
		case 'share-ratio':
			return needsTwo
				? null
				: {
						variables: [
							int( 'a', min, max ),
							int( 'b', min, max ),
							int( 'k', 2, 12 ),
							expr( 't', '(a+b)*k' ),
						],
						constraints: [ 'a!=b', 'gcd(a,b)=1' ],
						prompt: 'Share {{t}} in the ratio {{a}}:{{b}}. How much is the first share?',
						expr: 'a*k',
					};
		case 'speed':
			return {
				variables: [
					int( 'v', min, max ),
					int( 't', 2, 9 ),
					expr( 'd', 'v*t' ),
				],
				constraints: [],
				prompt: 'A car travels {{d}} km in {{t}} hours. What is its average speed in km/h?',
				expr: 'v',
			};
		default:
			return undefined;
	}
}
