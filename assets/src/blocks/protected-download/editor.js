/* global wp, ohmylmsProtectedDownloads */
( function () {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const { ComboboxControl } = wp.components;
	const { createElement } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType( 'ohmylms/protected-download', {
		title: __( 'Protected Download', 'ohmylms' ),
		category: 'widgets',
		icon: 'download',
		attributes: {
			downloadId: { type: 'integer', default: 0 },
		},
		edit: function Edit( { attributes, setAttributes } ) {
			const props = useBlockProps();
			const { options, catalogUrl } = ohmylmsProtectedDownloads;
			const selected = String( attributes.downloadId || '' );
			return createElement(
				'div',
				props,
				options.length
					? createElement( ComboboxControl, {
							label: __( 'Protected download', 'ohmylms' ),
							value: selected || null,
							options,
							onChange: ( value ) =>
								setAttributes( {
									downloadId: Number( value ) || 0,
								} ),
						} )
					: catalogUrl &&
							createElement(
								'a',
								{ href: catalogUrl },
								__(
									'Browse or create protected downloads',
									'ohmylms'
								)
							),
				selected &&
					! options.some( ( option ) => option.value === selected )
					? createElement(
							'p',
							null,
							__(
								'The selected download is unavailable.',
								'ohmylms'
							)
						)
					: null
			);
		},
		save() {
			return null;
		},
	} );
} )();
