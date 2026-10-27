/**
 * OhMyLMS Elementor Widgets JavaScript
 */

( function ( $ ) {
	'use strict';

	/**
	 * Initialize Elementor widgets
	 */
	var OhMyLMSElementorWidgets = {
		init: function () {
			// Initialize checkout widget
			this.initCheckoutWidget();
		},

		/**
		 * Initialize checkout widget functionality
		 */
		initCheckoutWidget: function () {
			// Add any specific JavaScript functionality for the checkout widget
			$( document ).on( 'elementor/popup/show', function () {
				// Re-initialize checkout form if it's in a popup
				OhMyLMSElementorWidgets.refreshCheckoutWidget();
			} );

			// Handle empty cart button clicks
			$( document ).on(
				'click',
				'.ohmylms-browse-courses-btn',
				function ( e ) {
					// Add any custom tracking or behavior here if needed
				}
			);
		},

		/**
		 * Refresh checkout widget
		 */
		refreshCheckoutWidget: function () {
			$( '.ohmylms-elementor-checkout-widget' ).each( function () {
				// Re-initialize any checkout-specific functionality
				var $widget = $( this );

				// Check if widget has empty cart message
				var $emptyMessage = $widget.find(
					'.ohmylms-empty-cart-message'
				);
				if ( $emptyMessage.length > 0 ) {
					// Apply any animations or effects to empty cart message
					OhMyLMSElementorWidgets.animateEmptyCartMessage(
						$emptyMessage
					);
				}

				// Trigger custom event for checkout refresh
				$widget.trigger( 'ohmylms:checkout:refresh' );
			} );
		},

		/**
		 * Animate empty cart message
		 */
		animateEmptyCartMessage: function ( $element ) {
			// Add a subtle fade-in animation
			$element
				.css( {
					opacity: '0',
					transform: 'translateY(20px)',
				} )
				.animate(
					{
						opacity: '1',
					},
					500
				)
				.animate(
					{
						transform: 'translateY(0)',
					},
					300
				);
		},

		/**
		 * Handle responsive behavior
		 */
		handleResponsive: function () {
			$( window ).on( 'resize', function () {
				// Adjust checkout form layout on mobile if needed
				var windowWidth = $( window ).width();

				$( '.ohmylms-checkout-form-wrapper' ).each( function () {
					var $wrapper = $( this );

					if ( windowWidth <= 768 ) {
						$wrapper.addClass( 'ohmylms-mobile-layout' );
					} else {
						$wrapper.removeClass( 'ohmylms-mobile-layout' );
					}
				} );
			} );
		},
	};

	// Initialize when document is ready
	$( document ).ready( function () {
		OhMyLMSElementorWidgets.init();
		OhMyLMSElementorWidgets.handleResponsive();
	} );

	// Initialize when Elementor frontend is ready
	$( window ).on( 'elementor/frontend/init', function () {
		// Register widget handlers if needed
		if ( typeof elementorFrontend !== 'undefined' ) {
			elementorFrontend.hooks.addAction(
				'frontend/element_ready/ohmylms-checkout.default',
				function ( $scope ) {
					OhMyLMSElementorWidgets.initCheckoutWidget();
				}
			);
		}
		if ( typeof elementorFrontend !== 'undefined' ) {
			elementorFrontend.hooks.addAction(
				'frontend/element_ready/ohmylms-dashboard.default',
				function ( $scope ) {
					var $dashboard = $scope.find( '.ohmylms-dashboard' );
					if ( $dashboard.length ) {
						$dashboard.trigger( 'ohmylms:dashboard:ready' );
					}
				}
			);
		}
		if ( typeof elementorFrontend !== 'undefined' ) {
			elementorFrontend.hooks.addAction(
				'frontend/element_ready/ohmylms-profile.default',
				function ( $scope ) {
					var $profile = $scope.find( '.ohmylms-dashboard' );
					if ( $profile.length ) {
						$profile.trigger( 'ohmylms:profile:ready' );
					}
				}
			);
		}
	} );
} )( jQuery );
