<?php
/**
 * Utility functions.
 *
 * @package OhMyLMS
 */



if ( ! function_exists( 'ecommerce_form_field' ) ) {

	/**
	 * Generate a form field for the checkout form.
	 *
	 * @param string     $key The key for the field.
	 * @param array      $args The arguments for the field.
	 * @param mixed|null $value The value of the field. Default is null.
	 * @return string The HTML for the form field.
	 *
	 * @since 1.0.0
	 */
	function ecommerce_form_field( $key, $args, $value = null ) {
		$defaults = array(
			'type'            => 'text',
			'label'           => '',
			'description'     => '',
			'placeholder'     => '',
			'required'        => false,
			'id'              => $key,
			'class'           => array(),
			'label_class'     => array( 'ohmylms-input-label' ),
			'input_class'     => array(),
			'options'         => array(),
			'default'         => '',
			'autofocus'       => '',
			'priority'        => '',
			'unchecked_value' => null,
			'checked_value'   => '1',
			'autocomplete'    => '',
		);
		$args     = wp_parse_args( $args, $defaults );
		if ( is_null( $value ) || '' === $value ) {
			$value = $args['default'];
		}

		$custom_attributes = array();
		if ( ! empty( $args['autocomplete'] ) ) {
			$args['custom_attributes']['autocomplete'] = $args['autocomplete'];
		}

		if ( $args['description'] ) {
			$args['custom_attributes']['aria-describedby'] = $args['id'] . '-description';
		}

		if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
			foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		$required = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'ohmylms' ) . ')</span>';

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required        = '&nbsp;<abbr class="required" title="' . esc_attr__( 'required', 'ohmylms' ) . '">*</abbr>';
		}

		$field           = '';
		$label_id        = $args['id'];
		$field_container = '<p class="ohmylms-form-row %1$s" id="%2$s">%3$s</p>';

		switch ( $args['type'] ) {
			case 'text':
			case 'email':
			case 'tel':
			case 'password':
			case 'number':
			case 'url':
				$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="ohmylms-input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder=""  value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';
				break;
			case 'select':
				if ( ! empty( $args['options'] ) && is_array( $args['options'] ) ) {
					$field .= '<select class="ohmylms-input-select ohmylms-input-text' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . '>';
					foreach ( $args['options'] as $key => $option ) {
						$selected = selected( $value, $option['code'], false );
						$field   .= '<option value="' . esc_attr( $option['code'] ) . '" ' . $selected . '>' . esc_html( $option['title'] ) . '</option>';
					}
					$field .= '</select>';
				}
				break;
			case 'hidden':
				$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="input-hidden ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';
				break;
		}

		if ( ! empty( $field ) ) {
			$field_html  = '';
			$field_html .= '<span class="ohmylms-input-wrapper">';

			if ( $args['label'] ) {
				$field_html .= '<label for="' . esc_attr( $label_id ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . wp_kses_post( $args['label'] ) . $required . '</label>';
			}

				$field_html .= $field;

			if ( $args['description'] ) {
				$field_html .= '<span class="description" id="' . esc_attr( $args['id'] ) . '-description" aria-hidden="true">' . wp_kses_post( $args['description'] ) . '</span>';
			}

			$field_html .= '</span>';

			$container_class = esc_attr( implode( ' ', $args['class'] ) );
			$container_id    = esc_attr( $args['id'] ) . '_field';
			$field           = sprintf( $field_container, $container_class, $container_id, $field_html );
		}
		echo $field;
	}
}
