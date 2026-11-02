/**
 * Interactive question controls: sort into groups, build from tiles.
 *
 * The server renders working native controls (menus for sorting) and this script
 * upgrades them to tap-to-place tiles. The native fields stay the source of truth,
 * so autosave, required-answer checks and submission work unchanged.
 *
 * window.OhMyLMSInteractive.render( container, type, settings ) builds the same markup
 * for the practice runner, collect( container ) reads the answer, enhance( root ) upgrades.
 */
( function () {
	'use strict';

	var MARKER =
		/\[\[ohmylms-math:latex:(?:inline|display)\]\][\s\S]{1,2000}?\[\[\/ohmylms-math\]\]|\{([a-z0-9_-]{1,20})\}/gi;

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( name ) {
			if ( name === 'text' ) {
				node.textContent = attrs[ name ];
			} else if (
				attrs[ name ] !== null &&
				attrs[ name ] !== undefined
			) {
				node.setAttribute( name, attrs[ name ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function changed( node ) {
		node.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	/** Text with {markers}: calls field( id ) for each marker, adds the rest as text. */
	function marked( parent, text, field ) {
		var last = 0;
		var match;
		MARKER.lastIndex = 0;
		while ( ( match = MARKER.exec( text ) ) !== null ) {
			parent.appendChild(
				document.createTextNode( text.slice( last, match.index ) )
			);
			var control = match[ 1 ]
				? field( match[ 1 ] )
				: document.createTextNode( match[ 0 ] );
			if ( control ) {
				parent.appendChild( control );
			}
			last = match.index + match[ 0 ].length;
		}
		parent.appendChild( document.createTextNode( text.slice( last ) ) );
	}

	/* ---------- Sort into groups ---------- */

	function enhanceCategorize( root ) {
		var data = root.querySelector( '.ohmylms-cat-buckets' );
		var list = root.querySelector( '.ohmylms-cat-items' );
		if ( ! data || ! list ) {
			return;
		}
		var buckets;
		try {
			buckets = JSON.parse( data.textContent );
		} catch ( error ) {
			return;
		}
		var items = Array.prototype.map.call(
			list.querySelectorAll( '.ohmylms-cat-item' ),
			function ( node ) {
				return {
					id: node.getAttribute( 'data-item' ),
					text: node.querySelector( '.ohmylms-cat-label' )
						.textContent,
					image: node.querySelector( 'img' ),
					select: node.querySelector( 'select' ),
				};
			}
		);
		var selected = null;
		var status = el( 'p', {
			class: 'screen-reader-text',
			'aria-live': 'polite',
		} );
		var bank = el( 'div', {
			class: 'ohmylms-cat-bank',
			role: 'group',
			'aria-label': 'Items to sort',
		} );
		var zones = el( 'div', { class: 'ohmylms-cat-groups' } );
		var drops = {};
		buckets.forEach( function ( bucket ) {
			var drop = el( 'div', { class: 'ohmylms-cat-drop' } );
			drops[ bucket.id ] = drop;
			var place = el( 'button', {
				type: 'button',
				class: 'ohmylms-cat-place',
				text: bucket.label,
			} );
			var zone = el(
				'div',
				{
					class: 'ohmylms-cat-bucket',
					role: 'group',
					'aria-label': bucket.label,
				},
				[ place, drop ]
			);
			function assign( item ) {
				item.select.value = bucket.id;
				changed( item.select );
				selected = null;
				status.textContent = item.text + ' → ' + bucket.label;
				render();
			}
			place.addEventListener( 'click', function () {
				if ( selected ) {
					assign( selected );
				}
			} );
			zone.addEventListener( 'dragover', function ( event ) {
				event.preventDefault();
			} );
			zone.addEventListener( 'drop', function ( event ) {
				event.preventDefault();
				var id = event.dataTransfer.getData( 'text/plain' );
				items.forEach( function ( item ) {
					if ( item.id === id ) {
						assign( item );
					}
				} );
			} );
			zones.appendChild( zone );
		} );

		function tile( item ) {
			var button = el( 'button', {
				type: 'button',
				class: 'ohmylms-tile',
				draggable: 'true',
				'aria-pressed': selected === item ? 'true' : 'false',
			} );
			if ( item.image ) {
				button.appendChild(
					el( 'img', {
						src: item.image.getAttribute( 'src' ),
						alt: '',
					} )
				);
			}
			button.appendChild( document.createTextNode( item.text ) );
			button.addEventListener( 'dragstart', function ( event ) {
				event.dataTransfer.setData( 'text/plain', item.id );
			} );
			return button;
		}

		function unassign( item ) {
			item.select.value = '';
			changed( item.select );
			selected = null;
			status.textContent = item.text;
			render();
		}

		function slot( item ) {
			var node = tile( item );
			node.classList.add( 'ohmylms-tile-slot' );
			node.removeAttribute( 'draggable' );
			node.removeAttribute( 'aria-pressed' );
			node.setAttribute( 'aria-label', item.text );
			node.addEventListener( 'click', function () {
				unassign( item );
			} );
			return node;
		}

		function dropOnBank( event ) {
			event.preventDefault();
			var id = event.dataTransfer.getData( 'text/plain' );
			items.forEach( function ( item ) {
				if ( item.id === id && item.select.value ) {
					unassign( item );
				}
			} );
		}

		bank.addEventListener( 'dragover', function ( event ) {
			event.preventDefault();
		} );
		bank.addEventListener( 'drop', dropOnBank );

		function render() {
			bank.textContent = '';
			Object.keys( drops ).forEach( function ( id ) {
				drops[ id ].textContent = '';
			} );
			items.forEach( function ( item ) {
				var button = tile( item );
				if ( item.select.value && drops[ item.select.value ] ) {
					// A dashed slot keeps the tile's place in the bank.
					bank.appendChild( slot( item ) );
					// A placed tile returns to the bank when pressed.
					button.addEventListener( 'click', function () {
						item.select.value = '';
						changed( item.select );
						status.textContent = item.text;
						render();
					} );
					drops[ item.select.value ].appendChild( button );
				} else {
					button.addEventListener( 'click', function () {
						selected = selected === item ? null : item;
						render();
					} );
					bank.appendChild( button );
				}
			} );
		}

		list.hidden = true;
		root.appendChild( bank );
		root.appendChild( zones );
		root.appendChild( status );
		render();
	}

	/* ---------- Build from tiles ---------- */

	function enhanceBuild( root ) {
		var name = root.getAttribute( 'data-field-name' );
		var answer = root.querySelector( '.ohmylms-build-answer' );
		var bank = root.querySelector( '.ohmylms-build-bank' );
		if ( ! answer || ! bank ) {
			return;
		}
		var tiles = Array.prototype.map.call(
			bank.children,
			function ( button ) {
				return {
					text: button.getAttribute( 'data-tile' ),
					button: button,
				};
			}
		);
		answer.setAttribute( 'role', 'group' );
		bank.setAttribute( 'role', 'group' );
		tiles.forEach( function ( tile ) { tile.button.removeAttribute( 'role' ); } );
		var placed = [];
		var clear = root.querySelector( '.ohmylms-build-clear' );
		if ( clear ) {
			clear.addEventListener( 'click', function () {
				placed = [];
				sync();
			} );
		}

		function sync( silent ) {
			answer.textContent = '';
			Array.prototype.forEach.call(
				root.querySelectorAll( 'input[data-tile-answer]' ),
				function ( input ) {
					input.parentNode.removeChild( input );
				}
			);
			placed.forEach( function ( tile ) {
				var button = el( 'button', {
					type: 'button',
					class: 'ohmylms-tile is-placed',
					text: tile.text === ' ' ? '␣' : tile.text,
				} );
				button.addEventListener( 'click', function () {
					placed.splice( placed.indexOf( tile ), 1 );
					sync();
				} );
				answer.appendChild( button );
				root.appendChild(
					el( 'input', {
						type: 'hidden',
						name: name,
						value: tile.text,
						'data-tile-answer': '1',
						'data-question-id':
							root.getAttribute( 'data-question-id' ),
					} )
				);
			} );
			tiles.forEach( function ( tile ) {
				tile.button.hidden = placed.indexOf( tile ) !== -1;
			} );
			if ( clear ) {
				clear.hidden = ! placed.length;
			}
			if ( ! silent ) {
				changed( root );
			}
		}

		tiles.forEach( function ( tile ) {
			tile.button.addEventListener( 'click', function () {
				placed.push( tile );
				sync();
			} );
		} );
		sync( true );
	}

	/* ---------- Math expression keys ---------- */

	var KEYS = [
		[ '+', '+' ],
		[ '−', '-' ],
		[ '×', '*' ],
		[ '÷', '/' ],
		[ '(', '(' ],
		[ ')', ')' ],
		[ 'x²', '^2' ],
		[ '^', '^' ],
		[ '√', 'sqrt()', 1 ],
		[ 'π', 'pi' ],
	];

	/** A row of symbol keys that types into the field at the caret. */
	function addKeys( input ) {
		if ( input.getAttribute( 'data-keys' ) ) {
			return;
		}
		input.setAttribute( 'data-keys', '1' );
		var row = el( 'div', {
			class: 'ohmylms-expression-keys',
			role: 'group',
			'aria-label': 'Math symbols',
		} );
		KEYS.forEach( function ( key ) {
			var button = el( 'button', {
				type: 'button',
				class: 'ohmylms-tile ohmylms-key',
				'aria-label': key[ 0 ],
				text: key[ 0 ],
			} );
			// Keep focus in the field so the caret survives a tap.
			button.addEventListener( 'mousedown', function ( event ) {
				event.preventDefault();
			} );
			button.addEventListener( 'click', function () {
				var start = input.selectionStart;
				var end = input.selectionEnd;
				var value = input.value;
				if ( start === null ) {
					start = end = value.length;
				}
				input.value =
					value.slice( 0, start ) + key[ 1 ] + value.slice( end );
				var caret = start + key[ 1 ].length - ( key[ 2 ] || 0 );
				input.focus();
				input.setSelectionRange( caret, caret );
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				changed( input );
			} );
			row.appendChild( button );
		} );
		input.parentNode.insertBefore( row, input.nextSibling );
	}

	/* ---------- Widget registry (visual question types register here) ---------- */

	var widgets = {};

	/**
	 * Write one answer value as a hidden field named like the server expects
	 * ( prefix[key] ), or remove it when value is null. Marks the question as touched
	 * so the required-answer check can tell an untouched widget from an answered one.
	 */
	function setAnswer( root, key, value ) {
		var prefix = root.getAttribute( 'data-name-prefix' );
		var found = null;
		Array.prototype.forEach.call(
			root.querySelectorAll( 'input[data-answer-key]' ),
			function ( input ) {
				if ( input.getAttribute( 'data-answer-key' ) === key ) {
					found = input;
				}
			}
		);
		if ( value === null || value === undefined || value === '' ) {
			if ( found ) {
				found.parentNode.removeChild( found );
			}
		} else {
			if ( ! found ) {
				found = el( 'input', {
					type: 'hidden',
					'data-answer-key': key,
					name: prefix ? prefix + '[' + key + ']' : null,
				} );
				root.appendChild( found );
			}
			found.value = String( value );
		}
		root.setAttribute( 'data-touched', '1' );
		changed( root );
	}

	function enhance( scope ) {
		scope = scope || document;
		Array.prototype.forEach.call(
			scope.querySelectorAll( '.ohmylms-expression-input' ),
			addKeys
		);
		if ( scope.matches && scope.matches( '.ohmylms-expression-input' ) ) {
			addKeys( scope );
		}
		var roots = Array.prototype.slice.call(
			scope.querySelectorAll( '[data-ohmylms-interactive]' )
		);
		if ( scope.matches && scope.matches( '[data-ohmylms-interactive]' ) ) {
			roots.unshift( scope );
		}
		roots.forEach( function ( root ) {
			if ( root.getAttribute( 'data-enhanced' ) ) {
				return;
			}
			var type = root.getAttribute( 'data-ohmylms-interactive' );
			if ( type === 'categorize' ) {
				enhanceCategorize( root );
			} else if ( type === 'build-expression' ) {
				enhanceBuild( root );
			} else if ( widgets[ type ] ) {
				var config = {};
				try {
					config = JSON.parse(
						root.getAttribute( 'data-config' ) || '{}'
					);
				} catch ( error ) {
					return;
				}
				widgets[ type ].build( root, config );
			} else {
				return;
			}
			root.setAttribute( 'data-enhanced', '1' );
		} );
	}

	/* ---------- Practice runner support ---------- */

	function selectFor( id, choices, multiple ) {
		var select = el( 'select', {
			class: 'ohmylms-inline-select',
			'data-answer-key': id,
			'aria-label': id,
		} );
		select.multiple = !! multiple;
		if ( ! multiple )
			select.appendChild( el( 'option', { value: '', text: '…' } ) );
		choices.forEach( function ( choice ) {
			select.appendChild(
				el( 'option', { value: choice, text: choice } )
			);
		} );
		return select;
	}

	function inputFor( id, field ) {
		var input = el( 'input', {
			type: 'text',
			class:
				'ohmylms-text-input ohmylms-blank-input' +
				( field.kind === 'expression'
					? ' ohmylms-expression-input'
					: '' ),
			autocomplete: 'off',
			size: '6',
			inputmode: field.kind === 'numerical' ? 'decimal' : 'text',
			'data-answer-key': id,
			'aria-label': id,
		} );
		if ( ! field.unit ) {
			return input;
		}
		var group = el( 'span', {}, [
			input,
			el( 'span', { class: 'ohmylms-numerical-unit', text: field.unit } ),
		] );
		return group;
	}

	/** Build a question's controls from learner-safe settings. */
	function render( container, type, settings ) {
		settings = settings || {};
		var root = el( 'div', {
			class: 'ohmylms-interactive ohmylms-' + type,
			'data-ohmylms-interactive': type,
		} );
		if ( type === 'dropdown-blanks' ) {
			var choices = {};
			( settings.slots || [] ).forEach( function ( slot ) {
				choices[ slot.id ] = slot;
			} );
			var sentence = el( 'p', { class: 'ohmylms-dropdown-sentence' } );
			marked( sentence, settings.text || '', function ( id ) {
				return choices[ id ]
					? selectFor(
							id,
							choices[ id ].choices || [],
							choices[ id ].multiple
						)
					: null;
			} );
			root.appendChild( sentence );
		} else if ( type === 'multi-blank' ) {
			var fields = settings.fields || {};
			var field = function ( id ) {
				return fields[ id ] ? inputFor( id, fields[ id ] ) : null;
			};
			if ( settings.layout === 'table' ) {
				var table = el( 'table', { class: 'ohmylms-blank-table' } );
				if ( ( settings.columns || [] ).length ) {
					table.appendChild(
						el( 'thead', {}, [
							el(
								'tr',
								{},
								settings.columns.map( function ( column ) {
									return el( 'th', {
										scope: 'col',
										text: column,
									} );
								} )
							),
						] )
					);
				}
				var body = el( 'tbody' );
				( settings.rows || [] ).forEach( function ( row ) {
					var tr = el( 'tr' );
					row.forEach( function ( cell ) {
						var td = el( 'td' );
						marked( td, cell, field );
						tr.appendChild( td );
					} );
					body.appendChild( tr );
				} );
				table.appendChild( body );
				root.appendChild( table );
			} else {
				var line = el( 'p', { class: 'ohmylms-blank-sentence' } );
				marked( line, settings.text || '', field );
				root.appendChild( line );
			}
		} else if ( type === 'categorize' ) {
			var list = el( 'ul', { class: 'ohmylms-cat-items' } );
			( settings.items || [] ).forEach( function ( item ) {
				var select = el( 'select', {
					class: 'ohmylms-cat-select',
					'data-answer-key': item.id,
				} );
				select.appendChild( el( 'option', { value: '', text: '…' } ) );
				( settings.buckets || [] ).forEach( function ( bucket ) {
					select.appendChild(
						el( 'option', { value: bucket.id, text: bucket.label } )
					);
				} );
				var label = el( 'label', {}, [
					el( 'span', {
						class: 'ohmylms-cat-label',
						text: item.text,
					} ),
					select,
				] );
				list.appendChild(
					el(
						'li',
						{ class: 'ohmylms-cat-item', 'data-item': item.id },
						[
							item.image_url
								? el( 'img', {
										src: item.image_url,
										alt: item.text,
									} )
								: null,
							label,
						]
					)
				);
			} );
			var data = el( 'script', {
				type: 'application/json',
				class: 'ohmylms-cat-buckets',
			} );
			data.textContent = JSON.stringify( settings.buckets || [] );
			root.appendChild( list );
			root.appendChild( data );
		} else if ( widgets[ type ] ) {
			widgets[ type ].build( root, settings );
			root.setAttribute( 'data-enhanced', '1' );
		} else if ( type === 'crossword' || type === 'wordsearch' ) {
			root.setAttribute( 'data-config', JSON.stringify( settings ) );
		} else if ( type === 'text-entry' ) {
 root.appendChild( el( 'input', { type: 'text', 'data-answer-key': 'text', 'aria-label': 'Your answer', maxlength: '2000', class: 'ohmylms-text-input' } ) );
		} else if ( type === 'expression' ) {
			root.appendChild(
				el( 'input', {
					type: 'text',
					class: 'ohmylms-text-input ohmylms-expression-input',
					autocomplete: 'off',
					autocapitalize: 'off',
					spellcheck: 'false',
					'aria-label': 'Your answer',
					'data-answer-key': 'answer',
					placeholder: 'e.g. 2(x+3)',
				} )
			);
			if ( settings.form && settings.form !== 'any' ) {
				root.appendChild(
					el( 'p', {
						class: 'ohmylms-expression-form',
						text:
							'Write your answer in ' + settings.form + ' form.',
					} )
				);
			}
		} else if ( type === 'build-expression' ) {
			root.setAttribute( 'data-field-name', 'tiles[]' );
			root.appendChild(
				el( 'div', {
					class: 'ohmylms-build-answer',
					role: 'list',
					'aria-label': 'Your answer',
				} )
			);
			root.appendChild(
				el(
					'div',
					{
						class: 'ohmylms-build-bank',
						role: 'list',
						'aria-label': 'Available tiles',
					},
					( settings.tiles || [] ).map( function ( text ) {
						return el( 'button', {
							type: 'button',
							class: 'ohmylms-tile',
							role: 'listitem',
							'data-tile': text,
							text: text === ' ' ? '␣' : text,
						} );
					} )
				)
			);
		}
		container.appendChild( root );
		enhance( container );
		return root;
	}

	/** The answer a question holds: keyed object, or the ordered tile list. */
	function collect( container ) {
		var tiles = container.querySelectorAll( 'input[data-tile-answer]' );
		if ( container.querySelector( '.ohmylms-build-expression' ) ) {
			return Array.prototype.map.call( tiles, function ( input ) {
				return input.value;
			} );
		}
		var answer = {};
		Array.prototype.forEach.call(
			container.querySelectorAll( '[data-answer-key]' ),
			function ( field ) {
				if ( field.tagName === 'SELECT' && field.multiple ) {
					answer[ field.getAttribute( 'data-answer-key' ) ] =
						Array.from( field.selectedOptions, function ( option ) {
							return option.value;
						} );
					return;
				}
				if ( field.value !== '' ) {
					answer[ field.getAttribute( 'data-answer-key' ) ] =
						field.value;
				}
			}
		);
		return answer;
	}

	window.OhMyLMSInteractive = {
		enhance: enhance,
		render: render,
		collect: collect,
		register: function ( type, widget ) {
			widgets[ type ] = widget;
		},
		util: { el: el, changed: changed, setAnswer: setAnswer },
	};

	function start() {
		enhance( document );
		if ( window.MutationObserver ) {
			// The block editor canvas iframe has no body until React mounts one; the root then sees it added.
			var target = document.body || document.documentElement;
			new MutationObserver( function ( records ) {
				records.forEach( function ( record ) {
					Array.prototype.forEach.call(
						record.addedNodes,
						function ( node ) {
							if ( node.nodeType === 1 ) {
								enhance( node );
							}
						}
					);
				} );
			} ).observe( target, { childList: true, subtree: true } );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
