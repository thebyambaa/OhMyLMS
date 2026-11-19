/* global wp, ohmylmsMathReferences */
( function () {
	const { RichTextToolbarButton } = wp.blockEditor;
	const { Button, ComboboxControl, Modal } = wp.components;
	const { createElement: el, Fragment, useState } = wp.element;
	const { __ } = wp.i18n;
	const { applyFormat, getActiveFormat, registerFormatType, removeFormat } =
		wp.richText;
	const name = 'ohmylms/math-reference';

	function Edit( { isActive, value, onChange } ) {
		const [ open, setOpen ] = useState( false );
		const [ selected, setSelected ] = useState( '' );
		const { options } = ohmylmsMathReferences;

		function selection() {
			let { start, end } = value;
			const hasReference = ( index ) =>
				value.formats[ index ]?.some(
					( format ) => format.type === name
				);
			if ( start === end && isActive ) {
				while ( start > 0 && hasReference( start - 1 ) ) {
					start--;
				}
				while ( end < value.text.length && hasReference( end ) ) {
					end++;
				}
			}
			return { start, end };
		}

		function link() {
			const reference = options.find(
				( item ) => item.value === selected
			);
			const { start, end } = selection();
			if ( reference && start < end ) {
				onChange(
					applyFormat(
						removeFormat( value, 'core/link', start, end ),
						{
							type: name,
							attributes: {
								href: reference.url,
								referenceId: reference.value,
							},
						},
						start,
						end
					)
				);
			}
			setOpen( false );
		}

		return el(
			Fragment,
			null,
			el( RichTextToolbarButton, {
				icon: 'book',
				title: __( 'Math Reference', 'ohmylms' ),
				isActive,
				onClick() {
					setSelected(
						getActiveFormat( value, name )?.attributes
							?.referenceId || ''
					);
					setOpen( true );
				},
			} ),
			open &&
				el(
					Modal,
					{
						title: __( 'Link a math reference', 'ohmylms' ),
						onRequestClose: () => setOpen( false ),
					},
					value.start === value.end && ! isActive
						? el(
								'p',
								null,
								__(
									'Select the words you want to link first.',
									'ohmylms'
								)
							)
						: el(
								Fragment,
								null,
								options.length
									? el( ComboboxControl, {
											label: __(
												'Search published references',
												'ohmylms'
											),
											options,
											value: selected || null,
											onChange: ( next ) =>
												setSelected( next || '' ),
										} )
									: el(
											'p',
											null,
											__(
												'Publish a Math Reference in OhMyLMS to link it here.',
												'ohmylms'
											)
										),
								el(
									Button,
									{
										variant: 'primary',
										disabled: ! options.some(
											( option ) =>
												option.value === selected
										),
										onClick: link,
									},
									__( 'Apply link', 'ohmylms' )
								),
								isActive &&
									el(
										Button,
										{
											variant: 'tertiary',
											onClick() {
												const { start, end } =
													selection();
												onChange(
													removeFormat(
														value,
														name,
														start,
														end
													)
												);
												setOpen( false );
											},
										},
										__( 'Remove reference link', 'ohmylms' )
									)
							)
				)
		);
	}

	registerFormatType( name, {
		title: __( 'Math Reference', 'ohmylms' ),
		tagName: 'a',
		className: 'ohmylms-reference-link',
		attributes: {
			href: 'href',
			referenceId: 'data-ohmylms-reference',
		},
		edit: Edit,
	} );
} )();
