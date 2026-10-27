/**
 * LaTeX to function compiler for the interactive activity graph.
 *
 * A small recursive-descent parser builds closures directly; no code is generated or
 * evaluated, so learner-typed LaTeX can never run script.
 */

const FUNCTIONS = {
	sin: Math.sin,
	cos: Math.cos,
	tan: Math.tan,
	arcsin: Math.asin,
	arccos: Math.acos,
	arctan: Math.atan,
	sinh: Math.sinh,
	cosh: Math.cosh,
	tanh: Math.tanh,
	ln: Math.log,
	log: Math.log10,
	exp: Math.exp,
	abs: Math.abs,
	floor: Math.floor,
	ceil: Math.ceil,
	sgn: Math.sign,
};
const CONSTANTS = { pi: Math.PI };
const SPACING = new Set( [ ',', ';', '!', ':', ' ', '>' ] );

/**
 * Split LaTeX into tokens.
 *
 * @param {string} source LaTeX.
 * @return {Array<{type: string, value: string}>} Tokens.
 */
function tokenize( source ) {
	const tokens = [];
	const pattern =
		/\s+|\\([a-zA-Z]+)|\\(.)|(\d+(?:\.\d+)?|\.\d+)|([a-zA-Z])|([+\-*/^_(){}[\]|=])/gy;
	let match;
	let last = 0;
	while ( ( match = pattern.exec( source ) ) !== null ) {
		last = pattern.lastIndex;
		if ( match[ 1 ] ) {
			tokens.push( { type: 'command', value: match[ 1 ] } );
		} else if ( match[ 2 ] !== undefined ) {
			if ( ! SPACING.has( match[ 2 ] ) ) {
				tokens.push( { type: 'symbol', value: match[ 2 ] } );
			}
		} else if ( match[ 3 ] ) {
			tokens.push( { type: 'number', value: match[ 3 ] } );
		} else if ( match[ 4 ] ) {
			tokens.push( { type: 'letter', value: match[ 4 ] } );
		} else if ( match[ 5 ] ) {
			tokens.push( { type: 'symbol', value: match[ 5 ] } );
		}
	}
	if ( last !== source.length ) {
		throw new SyntaxError( `Unexpected "${ source[ last ] }"` );
	}
	return tokens;
}

/**
 * Parse tokens into an evaluable closure.
 *
 * @param {Array}    tokens Tokens.
 * @param {string[]} params Allowed parameter names.
 * @return {function(number, Object): number} Evaluator.
 */
function parse( tokens, params ) {
	let index = 0;
	let absDepth = 0;
	const peek = () => tokens[ index ];
	const isSymbol = ( token, value ) =>
		token && token.type === 'symbol' && token.value === value;
	const isCommand = ( token, ...names ) =>
		token && token.type === 'command' && names.includes( token.value );
	const expect = ( value ) => {
		if ( ! isSymbol( peek(), value ) ) {
			throw new SyntaxError( `Expected "${ value }"` );
		}
		index++;
	};
	const skipSizing = () => {
		while ( isCommand( peek(), 'left', 'right', 'big', 'Big' ) ) {
			index++;
		}
	};

	/** @return {function(number, Object): number} Braced group. */
	function group() {
		expect( '{' );
		const inner = expression();
		expect( '}' );
		return inner;
	}

	/** @return {boolean} Whether the next token can start a factor. */
	function startsFactor() {
		skipSizing();
		const token = peek();
		if ( ! token ) {
			return false;
		}
		if ( token.type === 'number' || token.type === 'letter' ) {
			return true;
		}
		if ( token.type === 'command' ) {
			return ! isCommand( token, 'cdot', 'times', 'div' );
		}
		return (
			isSymbol( token, '(' ) ||
			isSymbol( token, '{' ) ||
			( isSymbol( token, '|' ) && absDepth === 0 )
		);
	}

	/** @return {function(number, Object): number} Primary value. */
	function primary() {
		skipSizing();
		const token = peek();
		if ( ! token ) {
			throw new SyntaxError( 'Unexpected end' );
		}
		index++;
		if ( token.type === 'number' ) {
			const value = Number( token.value );
			return () => value;
		}
		if ( token.type === 'letter' ) {
			if ( token.value === 'x' ) {
				return ( x ) => x;
			}
			if ( token.value === 'e' && ! params.includes( 'e' ) ) {
				return () => Math.E;
			}
			if ( params.includes( token.value ) ) {
				const name = token.value;
				return ( x, values ) => values[ name ];
			}
			throw new SyntaxError( `Unknown symbol "${ token.value }"` );
		}
		if ( isSymbol( token, '(' ) ) {
			const saved = absDepth;
			absDepth = 0;
			const inner = expression();
			absDepth = saved;
			skipSizing();
			expect( ')' );
			return inner;
		}
		if ( isSymbol( token, '{' ) ) {
			index--;
			return group();
		}
		if ( isSymbol( token, '|' ) ) {
			absDepth++;
			const inner = expression();
			absDepth--;
			skipSizing();
			expect( '|' );
			return ( x, values ) => Math.abs( inner( x, values ) );
		}
		if ( token.type === 'command' ) {
			return command( token.value );
		}
		throw new SyntaxError( `Unexpected "${ token.value }"` );
	}

	/**
	 * @param {string} name Command name without the backslash.
	 * @return {function(number, Object): number} Evaluator for the command.
	 */
	function command( name ) {
		if ( name in CONSTANTS ) {
			const value = CONSTANTS[ name ];
			return () => value;
		}
		if ( name === 'frac' || name === 'dfrac' || name === 'tfrac' ) {
			const top = group();
			const bottom = group();
			return ( x, values ) => top( x, values ) / bottom( x, values );
		}
		if ( name === 'sqrt' ) {
			let degree = null;
			if ( isSymbol( peek(), '[' ) ) {
				index++;
				degree = expression();
				expect( ']' );
			}
			const radicand = group();
			return ( x, values ) => {
				const base = radicand( x, values );
				if ( ! degree ) {
					return Math.sqrt( base );
				}
				const n = degree( x, values );
				// Real odd roots of negative numbers are defined.
				return base < 0 && Math.abs( n % 2 ) === 1
					? -Math.pow( -base, 1 / n )
					: Math.pow( base, 1 / n );
			};
		}
		if ( name === 'operatorname' || name === 'mathrm' ) {
			expect( '{' );
			let word = '';
			while ( peek() && peek().type === 'letter' ) {
				word += tokens[ index++ ].value;
			}
			expect( '}' );
			return applyFunction( word );
		}
		if ( name in FUNCTIONS ) {
			return applyFunction( name );
		}
		throw new SyntaxError( `Unsupported "\\${ name }"` );
	}

	/**
	 * @param {string} name Function name.
	 * @return {function(number, Object): number} Function applied to its argument.
	 */
	function applyFunction( name ) {
		const fn = FUNCTIONS[ name ];
		if ( ! fn ) {
			throw new SyntaxError( `Unsupported function "${ name }"` );
		}
		let raised = null;
		if ( isSymbol( peek(), '^' ) ) {
			index++;
			raised = exponent();
		}
		const argument = isSymbol( peek(), '(' ) ? primary() : implicit( true );
		return ( x, values ) => {
			const result = fn( argument( x, values ) );
			return raised ? Math.pow( result, raised( x, values ) ) : result;
		};
	}

	/** @return {function(number, Object): number} Exponent after the caret. */
	function exponent() {
		if ( isSymbol( peek(), '{' ) ) {
			return group();
		}
		if ( isSymbol( peek(), '-' ) ) {
			index++;
			const inner = exponent();
			return ( x, values ) => -inner( x, values );
		}
		return primary();
	}

	/** @return {function(number, Object): number} Factor with an optional power. */
	function power() {
		const base = primary();
		if ( isSymbol( peek(), '^' ) ) {
			index++;
			const exp = exponent();
			return ( x, values ) =>
				Math.pow( base( x, values ), exp( x, values ) );
		}
		return base;
	}

	/**
	 * Powers multiplied by juxtaposition, as in `2x` or `\sin 2x`.
	 *
	 * @param {boolean} inArgument Whether this is an unbracketed function argument, which ends at the next function.
	 * @return {function(number, Object): number} Product.
	 */
	function implicit( inArgument = false ) {
		let result = unary();
		while (
			startsFactor() &&
			! ( inArgument && isCommand( peek(), ...Object.keys( FUNCTIONS ) ) )
		) {
			const left = result;
			const right = power();
			result = ( x, values ) => left( x, values ) * right( x, values );
		}
		return result;
	}

	/** @return {function(number, Object): number} Optionally signed power. */
	function unary() {
		if ( isSymbol( peek(), '-' ) ) {
			index++;
			const inner = unary();
			return ( x, values ) => -inner( x, values );
		}
		if ( isSymbol( peek(), '+' ) ) {
			index++;
			return unary();
		}
		return power();
	}

	/** @return {function(number, Object): number} Product or quotient. */
	function term() {
		let result = implicit();
		for (;;) {
			const token = peek();
			const left = result;
			if (
				isSymbol( token, '*' ) ||
				isCommand( token, 'cdot', 'times' )
			) {
				index++;
				const right = implicit();
				result = ( x, values ) =>
					left( x, values ) * right( x, values );
			} else if ( isSymbol( token, '/' ) || isCommand( token, 'div' ) ) {
				index++;
				const right = implicit();
				result = ( x, values ) =>
					left( x, values ) / right( x, values );
			} else {
				return result;
			}
		}
	}

	/** @return {function(number, Object): number} Sum or difference. */
	function expression() {
		let result = term();
		for (;;) {
			const token = peek();
			const left = result;
			if ( isSymbol( token, '+' ) ) {
				index++;
				const right = term();
				result = ( x, values ) =>
					left( x, values ) + right( x, values );
			} else if ( isSymbol( token, '-' ) ) {
				index++;
				const right = term();
				result = ( x, values ) =>
					left( x, values ) - right( x, values );
			} else {
				return result;
			}
		}
	}

	const result = expression();
	if ( index < tokens.length ) {
		throw new SyntaxError( `Unexpected "${ tokens[ index ].value }"` );
	}
	return result;
}

/**
 * Compile the right-hand side of `y = …`, `f(x) = …` or a bare expression in x.
 *
 * @param {string}   latex  MathLive LaTeX.
 * @param {string[]} params Parameter names the expression may use.
 * @return {{fn: function(number, Object): number}|{error: string}} Evaluator or a message.
 */
export function compileExpression( latex, params = [] ) {
	const source = String( latex || '' ).trim();
	if ( ! source ) {
		return { error: 'empty' };
	}
	if ( source.length > 500 ) {
		return { error: 'Expression is too long' };
	}
	try {
		let tokens = tokenize( source );
		const equals = tokens.findIndex( ( token ) => isEquals( token ) );
		if ( equals !== -1 ) {
			tokens = tokens.slice( equals + 1 );
		}
		if ( ! tokens.length ) {
			return { error: 'empty' };
		}
		const fn = parse( tokens, params );
		return { fn };
	} catch ( failure ) {
		return { error: failure.message };
	}
}

/**
 * @param {{type: string, value: string}} token Token.
 * @return {boolean} Whether it is the equals sign.
 */
function isEquals( token ) {
	return token.type === 'symbol' && token.value === '=';
}
