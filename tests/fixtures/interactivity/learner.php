<?php
/**
 * Learner page views for the browser fixture: the plugin's real templates (catalogue, carousel,
 * account, login/sign-up, reviews, checkout) rendered with fixture data and their decorators.
 *
 * @package OhMyLMS\Tests
 */

namespace OhMyLMS\Commerce {
	/** Stands in for the commerce service: one offline gateway, an empty session. */
	function ecommerce() {
		static $service = null;
		if ( null === $service ) {
			$service = new class() {
				public $cart;
				public $session;
				public function __construct() {
					$this->cart    = new class() {
						public function get_cart() {
							return array();
						}
						public function needs_payment() {
							return true;
						}
					};
					$this->session = new class() {
						public function get( $key ) {
							return null;
						}
					};
				}
				public function checkout() {
					return fixture_checkout();
				}
				public function gateways() {
					return new class() {
						public function get_available_payment_gateways() {
							return fixture_gateways();
						}
					};
				}
			};
		}
		return $service;
	}
}

namespace {
	define( 'OHMYLMS_URL', '/wp-content/plugins/' . basename( OHMYLMS_DIR ) );
	define( 'OHMYLMS_COURSE_CPT', 'ohmylms-course' );

	/** Minimal main query: three courses, two per page, searchable by title. */
	class WP_Query {
		public $posts         = array();
		public $post_count    = 0;
		public $found_posts   = 0;
		public $max_num_pages = 1;
		private $vars         = array();
		private $index        = -1;

		public function __construct( array $courses = array(), $per_page = 2, $pages = 1 ) {
			$this->vars        = array(
				'posts_per_page'           => $per_page * $pages,
				'ohmylms_catalog_per_page' => $per_page,
				'ohmylms_catalog_pages'    => $pages,
				'paged'                    => 1,
			);
			$this->found_posts = count( $courses );
			$this->posts       = array_slice( $courses, 0, $per_page * $pages );
			$this->post_count  = count( $this->posts );
			$this->max_num_pages = (int) ceil( $this->found_posts / max( 1, $per_page ) );
		}
		public function get( $name, $default = '' ) {
			return $this->vars[ $name ] ?? $default;
		}
		public function have_posts() {
			if ( $this->index + 1 < $this->post_count ) {
				return true;
			}
			$this->index = -1;
			return false;
		}
		public function the_post() {
			++$this->index;
			$GLOBALS['course'] = $this->posts[ $this->index ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixture loop.
		}
	}
	function have_posts() {
		return $GLOBALS['wp_query']->have_posts();
	}
	function the_post() {
		$GLOBALS['wp_query']->the_post();
	}
	function wp_reset_postdata() {
	}

	function fixture_course( $id, $title ) {
		return new class( $id, $title ) {
			private $id;
			private $title;
			public function __construct( $id, $title ) {
				$this->id    = $id;
				$this->title = $title;
			}
			public function get_id() {
				return $this->id;
			}
			public function get_title() {
				return $this->title;
			}
			public function get_type() {
				return 'self-paced';
			}
			public function add_to_cart_url() {
				return '/fixture?add-to-cart=' . $this->id;
			}
			public function add_to_cart_text() {
				return 'Buy now';
			}
			public function get_enable_reviews() {
				return true;
			}
			public function has_access() {
				return true;
			}
		};
	}

	function fixture_checkout() {
		return new class() {
			public function is_allow_purchase_without_login() {
				return true;
			}
			public function is_login_enabled() {
				return true;
			}
			public function get_checkout_fields() {
				return array(
					'contact' => array(
						'email' => array( 'required' => true ),
					),
					'billing' => array(
						'first_name' => array( 'required' => true ),
						'country'    => array(
							'required' => true,
							'default'  => 'MN',
						),
					),
				);
			}
		};
	}

	function fixture_gateways() {
		$gateway = static function ( $id, $title ) {
			return new class( $id, $title ) {
				public $id;
				public $order_button_text    = '';
				public $subscription_support = true;
				private $title;
				public function __construct( $id, $title ) {
					$this->id    = $id;
					$this->title = $title;
				}
				public function get_title() {
					return $this->title;
				}
				public function has_fields() {
					return true;
				}
				public function payment_fields() {
					echo '<p>' . esc_html( $this->title . ' instructions' ) . '</p>';
				}
				public function get_required_checkout_fields() {
					return array( 'first_name' );
				}
			};
		};
		return array( $gateway( 'offline', 'Bank transfer' ), $gateway( 'qpay', 'QPay' ) );
	}

	// Plugin template helpers (the real ones are in includes/Utility and need the database).
	function is_ohmylms_checkout() {
		return 'checkout' === ( $_GET['view'] ?? '' );
	}
	function ohmylms_get_checkout_url() {
		return '/fixture?view=checkout';
	}
	function ohmylms_get_countries() {
		return array(
			array(
				'code'  => 'MN',
				'title' => 'Mongolia',
			),
			array(
				'code'  => 'DE',
				'title' => 'Germany',
			),
		);
	}
	function ohmylms_login_form( $args ) {
		ohmylms_get_template( 'global/form-login.php', wp_parse_args( $args, array( 'message' => '', 'redirect_to' => '', 'hidden' => false ) ) );
	}
	function ohmylms_signup_form( $args ) {
		ohmylms_get_template( 'global/form-signup.php', wp_parse_args( $args, array( 'message' => '', 'redirect_to' => '', 'hidden' => false ) ) );
	}
	function ohmylms_get_initials( $first, $last ) {
		return strtoupper( substr( (string) $first, 0, 1 ) . substr( (string) $last, 0, 1 ) );
	}
	function ohmylms_price( $amount ) {
		return '₮' . number_format( (float) $amount );
	}
	function ohmylms_format_datetime( $date ) {
		return (string) $date;
	}
	function ohmylms_course_loop_start( $echo = true, $attrs = null ) {
		ohmylms_get_template( 'loop/loop-start.php', array( 'atts' => $attrs ) );
	}
	function ohmylms_course_loop_end( $echo = true, $attrs = null ) {
		ohmylms_get_template( 'loop/loop-end.php', array( 'atts' => $attrs ) );
	}
	function ohmylms_implode_html_attributes( $attributes ) {
		$pairs = array();
		foreach ( $attributes as $name => $value ) {
			$pairs[] = esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $pairs );
	}
	function size_format( $bytes ) {
		return round( $bytes / 1048576 ) . ' MB';
	}
	function is_rtl() {
		return false;
	}
	function have_comments() {
		return false;
	}
	function get_comment_pages_count() {
		return 1;
	}
	function wp_get_current_commenter() {
		return array(
			'comment_author'       => '',
			'comment_author_email' => '',
		);
	}
	class WP_Comment_Query {
		public function query( $args ) {
			return array();
		}
	}
	/** Core's comment form, reduced to the fields and submit button the review template passes. */
	function comment_form( $args ) {
		echo '<div id="respond" class="comment-respond">' . $args['title_reply_before'] . esc_html( $args['title_reply'] ) . $args['title_reply_after']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template-supplied markup.
		echo '<form action="/fixture" method="post" id="commentform" class="comment-form">';
		echo $args['comment_field']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template-supplied markup.
		echo '<p class="form-submit">';
		printf( $args['submit_button'], 'submit', 'submit', 'submit', esc_attr( $args['label_submit'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template-supplied markup.
		echo '<input type="hidden" name="comment_post_ID" value="' . (int) $args['comment_post_ID'] . '"></p></form></div>';
	}

	/** A course card standing in for content-course.php, with the real buy button template. */
	function fixture_course_card() {
		global $course;
		echo '<div class="course-card"><h3 class="ohmylms-course-title">' . esc_html( $course->get_title() ) . '</h3>';
		ohmylms_get_template(
			'loop/add-to-cart.php',
			array(
				'class'      => 'ohmylms-button add_to_cart_button',
				'attributes' => array( 'data-course_id' => $course->get_id() ),
			)
		);
		echo '</div>';
	}

	function fixture_student() {
		return new class() {
			public function get_id() {
				return 1;
			}
			public function get_first_name() {
				return 'Saran';
			}
			public function get_last_name() {
				return 'Bat';
			}
			public function get_display_name() {
				return 'Saran Bat';
			}
			public function get_email() {
				return 'saran@example.test';
			}
			public function get_phone() {
				return '';
			}
			public function get_whatsapp() {
				return '';
			}
			public function get_address() {
				return '';
			}
			public function get_bio() {
				return '';
			}
			public function get_country() {
				return 'MN';
			}
			public function get_interest() {
				return '';
			}
			public function get_skills() {
				return array( 'Algebra' );
			}
			public function get_social_links() {
				return array();
			}
			public function get_cover_image( $context = 'view' ) {
				return '';
			}
			public function has_cover_image() {
				return false;
			}
			public function get_profile_image() {
				return '';
			}
		};
	}

	function fixture_membership() {
		return new class() {
			public $subscription = null;
			public $order;
			public function __construct() {
				$this->order = new class() {
					public function get_date_created() {
						return '2026-10-01';
					}
					public function get_payment_method_title() {
						return 'Bank transfer';
					}
				};
			}
			public function get_products() {
				return array( array( 'label' => 'Algebra basics' ), array( 'label' => 'Geometry' ) );
			}
			public function get_subscription_period() {
				return 'one_time';
			}
			public function get_regular_price() {
				return 50000;
			}
			public function get_name() {
				return 'School plan';
			}
		};
	}

	/**
	 * Render one learner view into the current output buffer.
	 *
	 * @param string $view View name.
	 * @return bool False for an unknown view.
	 */
	function fixture_learner_view( $view ) {
		$courses = array(
			fixture_course( 5, 'Algebra basics' ),
			fixture_course( 6, 'Geometry' ),
			fixture_course( 7, 'Reading club' ),
		);
		$GLOBALS['fixture_parts']['content-course'] = 'fixture_course_card';
		// The learner stylesheet modules each page loads (assets/css/frontend/modules.php).
		$styles = array(
			'catalog'  => array( 'catalog' ),
			'carousel' => array( 'catalog' ),
			'account'  => array( 'account', 'membership' ),
			'auth'     => array( 'account' ),
			'reviews'  => array( 'course' ),
			'checkout' => array( 'checkout' ),
		);
		foreach ( array_merge( array( 'core' ), $styles[ $view ] ?? array() ) as $style ) {
			$GLOBALS['fixture_styles'][] = 'assets/css/frontend/' . $style . '.css';
		}
		switch ( $view ) {
			case 'catalog':
				$GLOBALS['fixture_options']['ohmylms_archive_page_filters'] = array( 'price_type', 'difficulty_level' );
				$filters  = \OhMyLMS\Extensions\Catalog::filters();
				$matching = array_values(
					array_filter(
						$courses,
						static function ( $course ) use ( $filters ) {
							return '' === $filters['search'] || false !== stripos( $course->get_title(), $filters['search'] );
						}
					)
				);
				$GLOBALS['wp_query'] = new WP_Query( $matching, 2, $filters['page'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixture main query.
				add_action(
					'ohmylms_course_filter',
					static function ( $atts ) {
						ohmylms_get_template( 'filters/filter-header.php' );
						ohmylms_get_template( 'filters/filters.php', array( 'atts' => $atts ) );
					}
				);
				add_action(
					'ohmylms_before_course_loop',
					static function ( $atts ) {
						ohmylms_get_template( 'filters/search-sort.php', array( 'atts' => $atts ) );
					}
				);
				ohmylms_get_template(
					'archive-course.php',
					array(
						'atts' => array(
							'layout'         => 'grid',
							'layout_style'   => 'grid-style1',
							'show_filter'    => 'yes',
							'show_search'    => 'yes',
							'show_sort'      => 'yes',
							'posts_per_page' => 2,
						),
					)
				);
				return true;
			case 'carousel':
				$atts = array(
					'layout'       => 'grid',
					'layout_style' => 'grid-style4',
					'columns'      => 2,
				);
				\OhMyLMS\Design\Carousel::label( 'Popular courses' );
				ohmylms_course_loop_start( true, $atts );
				foreach ( array_merge( $courses, array( fixture_course( 8, 'Physics' ), fixture_course( 9, 'History' ) ) ) as $course ) {
					$GLOBALS['course'] = $course; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixture loop.
					fixture_course_card();
				}
				ohmylms_course_loop_end( true, $atts );
				return true;
			case 'account':
				ohmylms_get_template( 'profile/profile-edit.php', array( 'student' => fixture_student() ) );
				add_filter(
					'ohmylms_my_course_tabs',
					static function () {
						$panel = static function ( $key ) {
							echo '<p>' . esc_html( 'Courses: ' . $key ) . '</p>';
						};
						return array(
							'enrolled-courses'  => array(
								'title'    => 'Enrolled',
								'callback' => $panel,
							),
							'active-courses'    => array(
								'title'    => 'Active',
								'callback' => $panel,
							),
							'completed-courses' => array(
								'title'    => 'Completed',
								'callback' => $panel,
							),
						);
					}
				);
				ohmylms_get_template( 'profile/my-courses.php' );
				ohmylms_get_template( 'profile/loop/membership-card.php', array( 'memebership' => fixture_membership() ) );
				return true;
			case 'auth':
				$GLOBALS['fixture_logged_in'] = false;
				ohmylms_login_form( array( 'redirect_to' => '/fixture?view=account' ) );
				ohmylms_signup_form( array( 'hidden' => true ) );
				return true;
			case 'reviews':
				$GLOBALS['course'] = $courses[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Read by the template.
				ohmylms_get_template( 'single-course/tabs/reviews.php' );
				return true;
			case 'checkout':
				$checkout = fixture_checkout();
				add_action(
					'ohmylms_checkout_billing',
					static function () {
						echo '<p class="ohmylms-form-row" id="first_name_field"><label for="first_name" class="ohmylms-input-label">First name</label><input class="ohmylms-input-text" id="first_name" name="first_name" value=""></p>';
						echo '<p class="ohmylms-form-row" id="country_field"><label for="country" class="ohmylms-input-label">Country</label><select class="ohmylms-input-text" id="country" name="country"><option value="MN" selected>Mongolia</option><option value="DE">Germany</option></select></p>';
						echo '<p class="ohmylms-form-row" id="state_field"><label for="state" class="ohmylms-input-label">State</label><input class="ohmylms-input-text" id="state" name="state" value=""></p>';
						echo '<p class="ohmylms-form-row vat-number-field" id="vat_number_field"><label for="vat_number" class="ohmylms-input-label">VAT number</label><input class="ohmylms-input-text" id="vat_number" name="vat_number" value=""></p>';
					}
				);
				// As includes/Hooks/template-hooks.php: the payment methods sit inside the checkout form.
				add_action(
					'ohmylms_checkout_after_billing',
					static function () {
						ohmylms_get_template( 'checkout/payment.php', array( 'available_gateways' => fixture_gateways() ) );
					}
				);
				ohmylms_get_template( 'checkout/form-checkout.php', array( 'checkout' => $checkout ) );
				return true;
		}
		return false;
	}
}
