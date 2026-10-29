import { createRequire } from 'node:module';

const React = createRequire( import.meta.url )( 'react' );

export const { createElement, Fragment } = React;

let current = null;

/**
 * Hooks for a tiny renderer. Outside of it (static markup) the real React hooks
 * are used, so one component can be rendered both ways.
 *
 * @param {*} initial Initial state or initialiser.
 * @return {Array} State and setter.
 */
export function useState( initial ) {
	if ( ! current ) {
		return React.useState( initial );
	}
	const instance = current;
	const index = instance.cursor++;
	if ( ! instance.hooks[ index ] ) {
		const hook = {
			value: 'function' === typeof initial ? initial() : initial,
		};
		hook.set = ( next ) => {
			const value =
				'function' === typeof next ? next( hook.value ) : next;
			if ( ! Object.is( value, hook.value ) ) {
				hook.value = value;
				instance.root.dirty = true;
			}
		};
		instance.hooks[ index ] = hook;
	}
	return [ instance.hooks[ index ].value, instance.hooks[ index ].set ];
}

export function useRef( initial ) {
	if ( ! current ) {
		return React.useRef( initial );
	}
	const index = current.cursor++;
	current.hooks[ index ] ||= { value: { current: initial } };
	return current.hooks[ index ].value;
}

export function useEffect( effect, deps ) {
	if ( ! current ) {
		return React.useEffect( effect, deps );
	}
	const instance = current;
	const index = instance.cursor++;
	const hook = ( instance.hooks[ index ] ||= {
		deps: undefined,
		first: true,
	} );
	const changed =
		hook.first ||
		! deps ||
		deps.length !== hook.deps.length ||
		deps.some( ( dep, i ) => ! Object.is( dep, hook.deps[ i ] ) );
	if ( changed ) {
		instance.root.pending.push( () => {
			hook.cleanup?.();
			hook.cleanup = effect();
		} );
	}
	hook.first = false;
	hook.deps = deps;
}

export const memo = ( component ) => component;
export const forwardRef = ( render ) => ( props ) => render( props, null );

/**
 * Stand-in for a component of `@wordpress/components` or a sibling control: it
 * prints its props, and the renderer keeps it as a node with its raw props.
 *
 * @param {string} name Component name.
 * @return {Function} Probe component.
 */
export function probe( name ) {
	const Probe = ( props ) => {
		const { children, ...rest } = props;
		return createElement(
			`probe-${ name.toLowerCase() }`,
			{
				'data-props': JSON.stringify( rest, ( key, value ) =>
					'function' === typeof value
						? '[fn]'
						: value?.$$typeof
							? '[element]'
							: value
				),
			},
			children
		);
	};
	Probe.probeName = name;
	return Probe;
}

/** Render function components with hooks and keep the element tree. */
export class Renderer {
	constructor( component, props ) {
		this.component = component;
		this.props = props;
		this.instances = new Map();
		this.pending = [];
		this.dirty = false;
		this.tree = null;
		this.flush();
	}

	update( props ) {
		this.props = props;
		this.flush();
	}

	unmount() {
		for ( const instance of this.instances.values() ) {
			for ( const hook of instance.hooks ) hook?.cleanup?.();
		}
	}

	flush() {
		for ( let pass = 0; pass < 50; pass++ ) {
			this.dirty = false;
			this.tree = this.node(
				createElement( this.component, this.props ),
				'0'
			);
			const effects = this.pending.splice( 0 );
			effects.forEach( ( run ) => run() );
			if ( ! this.dirty ) return;
		}
		throw new Error( 'Too many renders' );
	}

	node( element, path ) {
		if ( null == element || 'boolean' === typeof element ) return null;
		if ( 'string' === typeof element || 'number' === typeof element )
			return String( element );
		if ( Array.isArray( element ) )
			return element.map( ( child, i ) =>
				this.node( child, `${ path }.${ i }` )
			);
		const { type, props } = element;
		if ( 'string' === typeof type ) {
			if ( props.ref ) {
				props.ref.current = {
					contains: ( target ) => !! target?.inside,
				};
			}
			return {
				tag: type,
				props,
				children: this.node( props.children, path ),
			};
		}
		if ( type === Fragment ) return this.node( props.children, path );
		if ( type.probeName ) {
			return {
				tag: type.probeName,
				props,
				children: this.node( props.children, path ),
			};
		}
		let instance = this.instances.get( path );
		if ( ! instance ) {
			instance = { hooks: [], cursor: 0, root: this };
			this.instances.set( path, instance );
		}
		instance.cursor = 0;
		current = instance;
		let rendered;
		try {
			rendered = type( props );
		} finally {
			current = null;
		}
		return this.node( rendered, `${ path }/` );
	}
}

/**
 * Plain, comparable form of a tree: functions become markers.
 *
 * @param {*} node Tree node.
 * @return {*} Serialisable tree.
 */
export function plain( node ) {
	if ( Array.isArray( node ) )
		return node.map( plain ).filter( ( n ) => null !== n );
	if ( ! node || 'string' === typeof node ) return node;
	const props = {};
	for ( const [ key, value ] of Object.entries( node.props ) ) {
		if ( 'children' === key || 'ref' === key ) continue;
		// The recovered icon spells SVG attributes with hyphens; React renders both alike.
		const name = key.startsWith( 'stroke-' )
			? key.replace( /-(\w)/g, ( match, letter ) => letter.toUpperCase() )
			: key;
		props[ name ] = JSON.parse(
			JSON.stringify( { value }, ( field, item ) => {
				if ( 'function' === typeof item ) {
					return '[fn]';
				}
				return item?.$$typeof ? '[element]' : item;
			} )
		).value;
	}
	return { tag: node.tag, props, children: plain( node.children ) };
}

/**
 * @param {*}        node      Tree node.
 * @param {Function} predicate Test on a node.
 * @return {Array} Matching nodes, depth first.
 */
export function findAll( node, predicate ) {
	if ( Array.isArray( node ) )
		return node.flatMap( ( child ) => findAll( child, predicate ) );
	if ( ! node || 'string' === typeof node ) return [];
	return [
		...( predicate( node ) ? [ node ] : [] ),
		...findAll( node.children, predicate ),
	];
}
