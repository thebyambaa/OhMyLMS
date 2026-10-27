/**
 * WPBakery Frontend Editor Support
 * Handles carousel initialization when elements are updated in the frontend editor
 */
( function ( $ ) {
	'use strict';

	/**
	 * Initialize OhMyLMS carousels
	 */
	function initCarousels( $container ) {
		// Wait a bit to ensure DOM is ready
		setTimeout( function () {
			// Find carousel wrappers
			var $carousels = $container
				? $container.find( '.ohmylms-carousel-wrapper' )
				: $( '.ohmylms-carousel-wrapper' );

			if ( $carousels.length ) {
				$carousels.each( function () {
					var $carousel = $( this );

					// Skip if already initialized
					if ( $carousel.hasClass( 'slick-initialized' ) ) {
						return;
					}

					// Check if slick is available
					if ( typeof $.fn.slick === 'undefined' ) {
						// Try again after a delay
						setTimeout( function () {
							initCarousels( $container );
						}, 500 );
						return;
					}

					// Initialize slick
					try {
						$carousel.slick( {
							dots: true,
							infinite: true,
							speed: 300,
							slidesToShow: 3,
							slidesToScroll: 1,
							arrows: true,
							responsive: [
								{
									breakpoint: 1024,
									settings: {
										slidesToShow: 2,
										slidesToScroll: 1,
									},
								},
								{
									breakpoint: 600,
									settings: {
										slidesToShow: 1,
										slidesToScroll: 1,
									},
								},
							],
						} );
					} catch ( e ) {
						console.error(
							'OhMyLMS: Error initializing carousel',
							e
						);
					}
				} );
			}
		}, 200 );
	}

	// On document ready
	$( document ).ready( function () {
		// Check if we're in WPBakery frontend editor
		if ( typeof window.vc !== 'undefined' ) {
			// Listen for shortcode updates
			window.vc.events.on( 'shortcodes:add', function ( model ) {
				if ( model.get( 'shortcode' ) === 'ohmylms_course_list' ) {
					initCarousels();
				}
			} );

			window.vc.events.on( 'shortcodeView:updated', function ( view ) {
				if ( view.model.get( 'shortcode' ) === 'ohmylms_course_list' ) {
					// Destroy existing slick instances in this view
					view.$el.find( '.slick-initialized' ).each( function () {
						$( this ).slick( 'unslick' );
					} );
					// Re-initialize
					initCarousels( view.$el );
				}
			} );

			window.vc.events.on( 'shortcodeView:ready', function ( view ) {
				if ( view.model.get( 'shortcode' ) === 'ohmylms_course_list' ) {
					initCarousels( view.$el );
				}
			} );
		}
		// Also initialize on page load
		initCarousels();
	} );
} )( jQuery );
