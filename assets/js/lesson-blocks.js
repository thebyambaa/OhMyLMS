/**
 * Editor UI for the lesson allowlist. Saved attributes are references and display fields only.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;

	function field( label, value, onChange, type ) {
		return el(
			'label',
			{ style: { display: 'block', marginBottom: '8px' } },
			label,
			el( 'input', {
				type: type || 'text',
				value: value || '',
				onChange: function ( event ) {
					onChange( event.target.value );
				},
				style: { display: 'block', width: '100%' },
			} )
		);
	}

	function register( name, title, attributes, edit ) {
		if ( wp.blocks.getBlockType( name ) ) {
			wp.blocks.unregisterBlockType( name );
		}
		wp.blocks.registerBlockType( name, {
			title: title,
			icon: 'welcome-learn-more',
			category: 'text',
			attributes: attributes,
			edit: edit,
			save: function () {
				return null;
			},
		} );
	}

	register(
		'ohmylms/lesson-video',
		__( 'Lesson video', 'ohmylms' ),
		{
			url: { type: 'string', default: '' },
			title: { type: 'string', default: '' },
		},
		function ( props ) {
			return el(
				'div',
				useBlockProps(),
				field( __( 'Video URL', 'ohmylms' ), props.attributes.url, function ( value ) {
					props.setAttributes( { url: value } );
				} ),
				field( __( 'Caption', 'ohmylms' ), props.attributes.title, function ( value ) {
					props.setAttributes( { title: value } );
				} )
			);
		}
	);

	register(
		'ohmylms/lesson-callout',
		__( 'Callout', 'ohmylms' ),
		{
			tone: { type: 'string', default: 'info' },
			text: { type: 'string', default: '' },
		},
		function ( props ) {
			return el(
				'div',
				useBlockProps(),
				el(
					'label',
					{ style: { display: 'block' } },
					__( 'Tone', 'ohmylms' ),
					el(
						'select',
						{
							value: props.attributes.tone,
							onChange: function ( event ) {
								props.setAttributes( { tone: event.target.value } );
							},
						},
						el( 'option', { value: 'info' }, __( 'Info', 'ohmylms' ) ),
						el( 'option', { value: 'warning' }, __( 'Warning', 'ohmylms' ) ),
						el( 'option', { value: 'success' }, __( 'Success', 'ohmylms' ) )
					)
				),
				el( 'textarea', {
					value: props.attributes.text,
					rows: 3,
					onChange: function ( event ) {
						props.setAttributes( { text: event.target.value } );
					},
					style: { width: '100%' },
				} )
			);
		}
	);

	register(
		'ohmylms/lesson-math',
		__( 'Math', 'ohmylms' ),
		{ tex: { type: 'string', default: '' } },
		function ( props ) {
			return el(
				'div',
				useBlockProps(),
				field( __( 'TeX', 'ohmylms' ), props.attributes.tex, function ( value ) {
					props.setAttributes( { tex: value } );
				} )
			);
		}
	);

	register(
		'ohmylms/lesson-exercise',
		__( 'Exercise', 'ohmylms' ),
		{
			quizId: { type: 'number', default: 0 },
			questionId: { type: 'number', default: 0 },
		},
		function ( props ) {
			return el(
				'div',
				useBlockProps(),
				el(
					'p',
					{},
					__(
						'References a quiz or question by ID. The question body stays on that post.',
						'ohmylms'
					)
				),
				field(
					__( 'Quiz ID', 'ohmylms' ),
					String( props.attributes.quizId || '' ),
					function ( value ) {
						props.setAttributes( { quizId: parseInt( value, 10 ) || 0 } );
					},
					'number'
				),
				field(
					__( 'Question ID', 'ohmylms' ),
					String( props.attributes.questionId || '' ),
					function ( value ) {
						props.setAttributes( { questionId: parseInt( value, 10 ) || 0 } );
					},
					'number'
				)
			);
		}
	);
} )( window.wp );
