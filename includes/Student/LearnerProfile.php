<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Learner profile details: province, school name, grade and phone number.
 *
 * First name, last name and email stay in the WordPress user; the phone number stays in the
 * existing student phone field (`billing_phone`). Only the three school details are new user meta.
 * Settings (and the legacy profile form) go through this class so they validate and store identically.
 * Registration is deliberately not affected: these details are optional and added after sign-up.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Data\Student;

defined( 'ABSPATH' ) || exit;

/** Shared options, validation and storage for the learner details collected at sign-up. */
final class LearnerProfile {

	const META_PROVINCE = 'ohmylms_profile_province';
	const META_SCHOOL   = 'ohmylms_profile_school_name';
	const META_GRADE    = 'ohmylms_profile_grade';

	const MAX_SCHOOL = 120;

	/** Stored value for a learner who lives outside Mongolia. */
	const OUTSIDE = 'outside-mongolia';

	/** Stored value for a learner who is not in grades 1–12. */
	const GRADE_OTHER = 'other';

	/**
	 * Ulaanbaatar and the 21 aimags, keyed by a stable slug, plus an option for learners abroad.
	 *
	 * @return array<string,string> Slug => label.
	 */
	public static function provinces() {
		$provinces = array(
			'ulaanbaatar'  => __( 'Ulaanbaatar', 'ohmylms' ),
			'arkhangai'    => __( 'Arkhangai', 'ohmylms' ),
			'bayan-olgii'  => __( 'Bayan-Ölgii', 'ohmylms' ),
			'bayankhongor' => __( 'Bayankhongor', 'ohmylms' ),
			'bulgan'       => __( 'Bulgan', 'ohmylms' ),
			'darkhan-uul'  => __( 'Darkhan-Uul', 'ohmylms' ),
			'dornod'       => __( 'Dornod', 'ohmylms' ),
			'dornogovi'    => __( 'Dornogovi', 'ohmylms' ),
			'dundgovi'     => __( 'Dundgovi', 'ohmylms' ),
			'govi-altai'   => __( 'Govi-Altai', 'ohmylms' ),
			'govisumber'   => __( 'Govisümber', 'ohmylms' ),
			'khentii'      => __( 'Khentii', 'ohmylms' ),
			'khovd'        => __( 'Khovd', 'ohmylms' ),
			'khuvsgul'     => __( 'Khövsgöl', 'ohmylms' ),
			'orkhon'       => __( 'Orkhon', 'ohmylms' ),
			'omnogovi'     => __( 'Ömnögovi', 'ohmylms' ),
			'selenge'      => __( 'Selenge', 'ohmylms' ),
			'sukhbaatar'   => __( 'Sükhbaatar', 'ohmylms' ),
			'tuv'          => __( 'Töv', 'ohmylms' ),
			'uvs'          => __( 'Uvs', 'ohmylms' ),
			'uvurkhangai'  => __( 'Övörkhangai', 'ohmylms' ),
			'zavkhan'      => __( 'Zavkhan', 'ohmylms' ),
			self::OUTSIDE  => __( 'Outside Mongolia', 'ohmylms' ),
		);
		/**
		 * Filters the provinces offered to learners.
		 *
		 * @param array<string,string> $provinces Slug => label.
		 */
		$filtered = apply_filters( 'ohmylms_learner_provinces', $provinces );
		return is_array( $filtered ) && $filtered ? $filtered : $provinces;
	}

	/**
	 * School grades 1–12 plus an option for anyone outside them.
	 *
	 * @return array<string,string> Value => label.
	 */
	public static function grades() {
		$grades = array();
		for ( $grade = 1; $grade <= 12; $grade++ ) {
			/* translators: %d: school grade number from 1 to 12. */
			$grades[ (string) $grade ] = sprintf( __( 'Grade %d', 'ohmylms' ), $grade );
		}
		$grades[ self::GRADE_OTHER ] = __( 'Not in grades 1–12', 'ohmylms' );
		/**
		 * Filters the grades offered to learners.
		 *
		 * @param array<string,string> $grades Value => label.
		 */
		$filtered = apply_filters( 'ohmylms_learner_grades', $grades );
		return is_array( $filtered ) && $filtered ? $filtered : $grades;
	}

	/**
	 * Clean a phone number to a compact form, or return an empty string when it is not valid.
	 *
	 * Accepts an optional leading + (international format), then 7–15 digits. Spaces, dots, dashes
	 * and brackets are allowed as separators and removed, for example "+976 9911-2233".
	 *
	 * @param mixed $value Submitted phone number.
	 * @return string Compact number, or '' when invalid or empty.
	 */
	public static function normalize_phone( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $value || ! preg_match( '/^\+?[\s(]*[0-9][0-9\s().\-]*$/', $value ) ) {
			return '';
		}
		$digits = preg_replace( '/\D/', '', $value );
		if ( strlen( $digits ) < 7 || strlen( $digits ) > 15 ) {
			return '';
		}
		return ( '+' === $value[0] ? '+' : '' ) . $digits;
	}

	/**
	 * Validate and clean the submitted details. Every field is optional: only keys present in
	 * $input are checked, so a form that shows a subset never blanks the others, and an empty
	 * value clears the stored one.
	 *
	 * @param array $input Unslashed submitted values (phone, province, school_name, grade).
	 * @return array{values:array<string,string>,errors:string[]}
	 */
	public static function validate( array $input ) {
		$input  = array_filter( $input, 'is_scalar' );
		$values = array();
		$errors = array();
		if ( array_key_exists( 'phone', $input ) ) {
			$raw = trim( sanitize_text_field( (string) $input['phone'] ) );
			if ( '' === $raw ) {
				$values['phone'] = '';
			} else {
				$values['phone'] = self::normalize_phone( $raw );
				if ( '' === $values['phone'] ) {
					$errors[] = __( 'Enter a valid phone number with 7–15 digits, for example +976 9911 2233.', 'ohmylms' );
				}
			}
		}
		if ( array_key_exists( 'province', $input ) ) {
			$values['province'] = sanitize_key( (string) $input['province'] );
			if ( '' !== $values['province'] && ! isset( self::provinces()[ $values['province'] ] ) ) {
				$errors[] = __( 'Choose a province from the list.', 'ohmylms' );
			}
		}
		if ( array_key_exists( 'school_name', $input ) ) {
			$values['school_name'] = self::text( $input['school_name'], self::MAX_SCHOOL );
		}
		if ( array_key_exists( 'grade', $input ) ) {
			$values['grade'] = sanitize_key( (string) $input['grade'] );
			if ( '' !== $values['grade'] && ! isset( self::grades()[ $values['grade'] ] ) ) {
				$errors[] = __( 'Choose a grade from the list.', 'ohmylms' );
			}
		}
		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Store validated details for a user. Keys that are absent from $values are left untouched.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string,string> $values  Values returned by validate().
	 */
	public static function save( $user_id, array $values ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return;
		}
		$meta = array(
			'province'    => self::META_PROVINCE,
			'school_name' => self::META_SCHOOL,
			'grade'       => self::META_GRADE,
		);
		foreach ( $meta as $key => $meta_key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}
			if ( '' === $values[ $key ] ) {
				delete_user_meta( $user_id, $meta_key );
			} else {
				update_user_meta( $user_id, $meta_key, $values[ $key ] );
			}
		}
		if ( array_key_exists( 'phone', $values ) ) {
			$student = new Student( $user_id );
			$student->set_phone( $values['phone'] );
			$student->save();
		}
	}

	/**
	 * Current details of a user, empty strings when never provided.
	 *
	 * @param int $user_id User ID.
	 * @return array{province:string,school_name:string,grade:string}
	 */
	public static function get( $user_id ) {
		$user_id = (int) $user_id;
		return array(
			'province'    => $user_id ? (string) get_user_meta( $user_id, self::META_PROVINCE, true ) : '',
			'school_name' => $user_id ? (string) get_user_meta( $user_id, self::META_SCHOOL, true ) : '',
			'grade'       => $user_id ? (string) get_user_meta( $user_id, self::META_GRADE, true ) : '',
		);
	}

	/**
	 * Bounded plain text.
	 *
	 * @param mixed $value Submitted value.
	 * @param int   $max   Maximum characters.
	 * @return string
	 */
	private static function text( $value, $max ) {
		return mb_substr( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), 0, $max );
	}

	/**
	 * A `<select>` element for the province or grade, with the stored value selected.
	 *
	 * @param string               $id       Element ID.
	 * @param string               $name     Field name.
	 * @param array<string,string> $options  Value => label.
	 * @param string               $selected Current value.
	 * @param string               $placeholder    Label of the empty first option.
	 */
	public static function select( $id, $name, array $options, $selected, $placeholder ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html( $placeholder ) . '</option>';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $selected, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}
}
