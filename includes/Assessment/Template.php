<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Randomised question templates: one question, endless numbers.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\Assessment\Cas\Expression;

defined( 'ABSPATH' ) || exit;

/**
 * Randomised question templates: one question, endless numbers.
 *
 * A question whose settings carry a "template" is stored once, as an immutable version.
 * Each time it is issued (a quiz attempt item, a practice item, an inline check) an
 * integer instance seed is stored with that issue, and QuestionSnapshot::instantiate()
 * turns the version into a concrete question: the same seed always gives the same numbers,
 * so grading, review and reports see exactly what the learner saw.
 *
 * settings.template = {
 *   variables: [ { name: 'a', type: 'int',     min: 2, max: 9, step: 1, exclude: [5] },
 *                { name: 'x', type: 'decimal', min: 1, max: 5, places: 1 },
 *                { name: 'w', type: 'choice',  values: ['apples', 'pears'] },
 *                { name: 'c', type: 'expr',    expr: 'a*b', places: 0 } ],      // derived, in order
 *   constraints: [ 'a>b', 'gcd(a,b)=1' ],                                      // all must hold
 *   set: [ { path: 'answer', expr: 'a*b' } ]                                   // computed settings
 * }
 *
 * {{expr}} in the title, body, option text and any text setting is replaced by its value;
 * {{expr:2}} fixes two decimals and {{expr:+}} always shows the sign (so 3x{{b:+}} reads
 * 3x-4 or 3x+4). A text that is exactly one {{expr}} becomes a number, which lets typed
 * settings (an expected value, a target) be computed too. Variable names are single
 * letters (not "e"), so they work inside the expression engine, which they share.
 */
final class Template {
	const TRIES   = 200;
	const SAMPLES = 8;
	const TYPES   = array( 'int', 'decimal', 'choice', 'expr' );
	const NAME    = '/^[a-df-z]$/';

	/**
	 * Has for Template.
	 *
	 * @param array $settings Configuration settings.
	 */
	public static function has( array $settings ) {
		return ! empty( $settings['template']['variables'] ) && is_array( $settings['template']['variables'] );
	}

	/** A positive 28-bit integer from any text, for derived seeds.
	 *
	 * @param mixed $text Text.
	 */
	public static function seed_from( $text ) {
		return (int) ( hexdec( substr( hash( 'sha256', (string) $text ), 0, 7 ) ) ?: 1 );
	}

	/** A fresh random instance seed. */
	public static function new_seed() {
		return random_int( 1, 0xFFFFFFF );
	}

	/** Same seed, same stream: draw $n of the stream as an integer up to 2^48.
	 *
	 * @param mixed $seed Seed.
	 * @param mixed $n N.
	 */
	private static function draw( $seed, &$n ) {
		return (int) hexdec( substr( hash( 'sha256', $seed . ':' . ( $n++ ) ), 0, 12 ) );
	}

	/** Only real numbers can appear in arithmetic.
	 *
	 * @param array $params Request parameters.
	 */
	private static function numeric( array $params ) {
		return array_filter(
			$params,
			static function ( $value ) {
				return is_int( $value ) || is_float( $value );
			}
		);
	}

	/**
	 * Eval expression for Template.
	 *
	 * @param mixed $text Text.
	 * @param array $params Request parameters.
	 */
	private static function eval_expression( $text, array $params ) {
		$tree = Expression::parse( $text );
		return null === $tree || '=' === $tree[0] ? null : Expression::evaluate( $tree, array_map( 'floatval', self::numeric( $params ) ) );
	}

	/**
	 * Draw the variables for a seed, retrying until every constraint holds.
	 *
	 * @return array{ok:bool,params:array}
	 * @param array $template Template.
	 * @param mixed $seed Seed.
	 */
	public static function generate( array $template, $seed ) {
		$n      = 0;
		$params = array();
		for ( $try = 0; $try < self::TRIES; $try++ ) {
			$params = array();
			$ok     = true;
			foreach ( (array) $template['variables'] as $def ) {
				$value = self::value( (array) $def, $params, $seed, $n );
				if ( null === $value ) {
					$ok = false;
					break; }
				$params[ (string) $def['name'] ] = $value;
			}
			foreach ( $ok ? (array) ( $template['constraints'] ?? array() ) : array() as $constraint ) {
				if ( ! self::holds( (string) $constraint, $params ) ) {
					$ok = false;
					break; }
			}
			if ( $ok ) {
				return array(
					'ok'     => true,
					'params' => $params,
				); }
		}
		return array(
			'ok'     => false,
			'params' => $params,
		);
	}

	/** One variable's value, or null when it must be redrawn.
	 *
	 * @param array $def Def.
	 * @param array $params Request parameters.
	 * @param mixed $seed Seed.
	 * @param mixed $n N.
	 */
	private static function value( array $def, array $params, $seed, &$n ) {
		switch ( $def['type'] ?? '' ) {
			case 'int':
				$min   = (int) ceil( (float) ( $def['min'] ?? 0 ) );
				$max   = (int) floor( (float) ( $def['max'] ?? 10 ) );
				$step  = max( 1, (int) ( $def['step'] ?? 1 ) );
				$count = intdiv( $max - $min, $step ) + 1;
				if ( $count < 1 ) {
					return null; }
				$value = $min + ( self::draw( $seed, $n ) % $count ) * $step;
				return in_array( $value, array_map( 'intval', (array) ( $def['exclude'] ?? array() ) ), true ) ? null : $value;
			case 'decimal':
				$places = max( 0, min( 6, (int) ( $def['places'] ?? 1 ) ) );
				$scale  = 10 ** $places;
				$low    = (int) round( (float) ( $def['min'] ?? 0 ) * $scale );
				$high   = (int) round( (float) ( $def['max'] ?? 10 ) * $scale );
				if ( $high < $low ) {
					return null; }
				return ( $low + self::draw( $seed, $n ) % ( $high - $low + 1 ) ) / $scale;
			case 'choice':
				$values = array_values( (array) ( $def['values'] ?? array() ) );
				return $values ? $values[ self::draw( $seed, $n ) % count( $values ) ] : null;
			case 'expr':
				$value = self::eval_expression( (string) ( $def['expr'] ?? '' ), $params );
				if ( null === $value ) {
					return null; }
				return isset( $def['places'] ) ? round( $value, max( 0, min( 9, (int) $def['places'] ) ) ) : round( $value, 9 );
		}
		return null;
	}

	/**
	 * Holds for Template.
	 * "a>b", "gcd(a,b)=1", "mod(a,b)=0"; a bare expression must be non-zero.
	 *
	 * @param mixed $constraint Constraint.
	 * @param array $params Request parameters.
	 */
	private static function holds( $constraint, array $params ) {
		if ( ! preg_match( '/^(.+?)(<=|>=|!=|==|<|>|=)(.+)$/', $constraint, $m ) ) {
			$value = self::eval_expression( $constraint, $params );
			return null !== $value && abs( $value ) > 1e-9;
		}
		$left  = self::eval_expression( $m[1], $params );
		$right = self::eval_expression( $m[3], $params );
		if ( null === $left || null === $right ) {
			return false; }
		$diff = $left - $right;
		switch ( $m[2] ) {
			case '<':
				return $diff < -1e-9;
			case '>':
				return $diff > 1e-9;
			case '<=':
				return $diff <= 1e-9;
			case '>=':
				return $diff >= -1e-9;
			case '!=':
				return abs( $diff ) > 1e-9;
			default:
				return abs( $diff ) <= 1e-9;
		}
	}

	/**
	 * Format for Template.
	 * 3, -2.5, "0.50" with places, "+3" with a sign; never "-0" or float noise.
	 *
	 * @param mixed $value Value to process.
	 * @param mixed $places Places.
	 * @param bool  $sign Sign.
	 */
	public static function format( $value, $places = null, $sign = false ) {
		$value = (float) $value;
		$text  = null === $places ? rtrim( rtrim( number_format( round( $value, 9 ), 9, '.', '' ), '0' ), '.' ) : number_format( $value, (int) $places, '.', '' );
		if ( preg_match( '/^-0(\.0*)?$/', $text ) ) {
			$text = ltrim( $text, '-' ); }
		return ( $sign && '-' !== $text[0] ? '+' : '' ) . $text;
	}

	/**
	 * Placeholder for Template.
	 *
	 * @return array{0:mixed,1:bool} value and whether it is a number
	 * @param mixed $body Body.
	 * @param array $params Request parameters.
	 * @param mixed $errors Errors.
	 */
	private static function placeholder( $body, array $params, &$errors ) {
		$places = null;
		$sign   = false;
		if ( preg_match( '/^(.*?):(\+?)(\d{0,2})$/s', $body, $m ) ) {
			$body   = $m[1];
			$sign   = '+' === $m[2];
			$places = '' === $m[3] ? null : (int) $m[3];
		}
		$body = trim( $body );
		if ( preg_match( self::NAME, $body ) && isset( $params[ $body ] ) && is_string( $params[ $body ] ) ) {
			return array( $params[ $body ], false ); }
		$value = self::eval_expression( $body, $params );
		if ( null === $value ) {
			$errors[] = $body;
			return array( null, false ); }
		return array( array( $value, $places, $sign ), true );
	}

	/** Replace {{…}} in one string; a lone placeholder with no sign becomes a number.
	 *
	 * @param mixed $text Text.
	 * @param array $params Request parameters.
	 * @param array $errors Errors.
	 */
	public static function substitute( $text, array $params, array &$errors = array() ) {
		// Older MathLive authoring stored typed double braces as literal LaTeX delimiters.
		// Recognize them only inside explicit equation markers, leaving prose untouched.
		if ( is_string( $text ) && false !== strpos( $text, '[[ohmylms-math:latex:' ) ) {
			$text = preg_replace_callback(
				'/\[\[ohmylms-math:latex:(?:inline|display)\]\]([\s\S]{1,2000}?)\[\[\/ohmylms-math\]\]/',
				static function ( $equation ) {
					$source = preg_replace_callback(
						'/(?:\\\\left\s*)?\\\\(?:lbrace|\{)\s*(?:\\\\left\s*)?\\\\(?:lbrace|\{)\s*([^{}\\\\]{1,240}?)\s*(?:\\\\right\s*)?\\\\(?:rbrace|\})\s*(?:\\\\right\s*)?\\\\(?:rbrace|\})/',
						static function ( $token ) {
							return '{{' . trim( $token[1] ) . '}}';
						},
						$equation[1]
					);
					return str_replace( $equation[1], $source, $equation[0] );
				},
				$text
			);
		}
		if ( ! is_string( $text ) || strpos( $text, '{{' ) === false ) {
			return $text; }
		if ( preg_match( '/^\{\{([^{}]+)\}\}$/', trim( $text ), $whole ) ) {
			list( $got, $is_number ) = self::placeholder( $whole[1], $params, $errors );
			if ( null === $got ) {
				return $text; }
			if ( ! $is_number ) {
				return $got; }
			if ( ! $got[2] ) {
				return null === $got[1] ? round( $got[0], 9 ) : round( $got[0], $got[1] ); }
		}
		return preg_replace_callback(
			'/\{\{([^{}]+)\}\}/',
			static function ( $m ) use ( $params, &$errors ) {
				list( $got, $is_number ) = self::placeholder( $m[1], $params, $errors );
				if ( null === $got ) {
					return $m[0]; }
				return $is_number ? self::format( $got[0], $got[1], $got[2] ) : (string) $got;
			},
			$text
		);
	}

	/**
	 * Substitute nested content while preserving textual option contracts.
	 *
	 * @param mixed $value Content to resolve.
	 * @param array $params Generated values.
	 * @param array $errors Unresolved formulas.
	 * @param bool  $preserve_text Keep strings as strings, including lone variables.
	 * @return mixed
	 */
	private static function substitute_deep( $value, array $params, array &$errors, $preserve_text = false ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::substitute_deep( $item, $params, $errors, $preserve_text ); }
			return $value;
		}
		$substituted = self::substitute( $value, $params, $errors );
		return $preserve_text && is_string( $value ) ? (string) $substituted : $substituted;
	}

	/** Assign $value at a dotted path such as "blanks.a.answer" or "parts.0.answer".
	 *
	 * @param array $settings Configuration settings.
	 * @param mixed $path Path.
	 * @param mixed $value Value to process.
	 */
	private static function set_path( array $settings, $path, $value ) {
		$keys = explode( '.', $path );
		$ref  = &$settings;
		foreach ( $keys as $key ) {
			if ( ! is_array( $ref ) ) {
				$ref = array(); }
			if ( ! array_key_exists( $key, $ref ) ) {
				$ref[ $key ] = array(); }
			$ref = &$ref[ $key ];
		}
		$ref = $value;
		return $settings;
	}

	/**
	 * Concrete title, body, settings and options for one seed. The template itself is removed,
	 * so nothing about how the numbers were made reaches a learner.
	 *
	 * @return array{ok:bool,params:array,errors:string[],title:string,body:string,settings:array,options:array}
	 * @param mixed $title Title.
	 * @param mixed $body Body.
	 * @param array $settings Configuration settings.
	 * @param array $options Operation options.
	 * @param mixed $seed Seed.
	 */
	public static function apply( $title, $body, array $settings, array $options, $seed ) {
		$template = (array) ( $settings['template'] ?? array() );
		$errors   = array();
		$gen      = self::has( $settings ) ? self::generate( $template, $seed ) : array(
			'ok'     => true,
			'params' => array(),
		);
		$params   = $gen['params'];
		unset( $settings['template'] );
		$settings = self::substitute_deep( $settings, $params, $errors );
		foreach ( (array) ( $template['set'] ?? array() ) as $rule ) {
			$value = is_array( $rule ) ? self::eval_expression( (string) ( $rule['expr'] ?? '' ), $params ) : null;
			if ( null === $value || ! preg_match( '/^[A-Za-z0-9_.-]{1,80}$/', (string) ( $rule['path'] ?? '' ) ) ) {
				$errors[] = (string) ( $rule['expr'] ?? '' );
				continue; }
			$settings = self::set_path( $settings, $rule['path'], round( $value, 9 ) );
		}
		$options = self::substitute_deep( $options, $params, $errors, true );
		foreach ( $options as $index => $option ) {
			$options[ $index ]['answer'] = (string) ( $option['answer'] ?? '' );
			if ( isset( $option['matching_data']['label'] ) ) {
				$options[ $index ]['matching_data']['label'] = (string) $option['matching_data']['label'];
			}
		}
		$title = (string) self::substitute( (string) $title, $params, $errors );
		$body  = (string) self::substitute( (string) $body, $params, $errors );
		return array(
			'ok'       => $gen['ok'],
			'params'   => $params,
			'errors'   => array_values( array_unique( $errors ) ),
			'title'    => $title,
			'body'     => $body,
			'settings' => $settings,
			'options'  => $options,
		);
	}

	/** Identifies the numbers an instance got, to avoid giving a learner the same ones twice.
	 *
	 * @param array $params Request parameters.
	 */
	public static function signature( array $params ) {
		return md5( wp_json_encode( $params ) );
	}

	/**
	 * A seed whose numbers differ from every signature in $seen (best effort).
	 *
	 * @param array    $settings Configuration settings.
	 * @param string[] $seen Seen.
	 */
	public static function fresh_seed( array $settings, array $seen ) {
		$seed = self::new_seed();
		for ( $i = 0; $i < 12 && self::has( $settings ); $i++ ) {
			$gen = self::generate( $settings['template'], $seed );
			if ( ! in_array( self::signature( $gen['params'] ), $seen, true ) ) {
				break; }
			$seed = self::new_seed();
		}
		return $seed;
	}

	/* ---------- Authoring ---------- */

	/**
	 * Check a template and prove it works: several seeds must generate, resolve every
	 * placeholder, give different numbers, and yield a valid question of the question's type.
	 *
	 * @param array         $settings Configuration settings.
	 * @param mixed         $title Title.
	 * @param mixed         $body Body.
	 * @param array         $options Operation options.
	 * @param callable|null $check Receives instantiated settings; returns true or a message.
	 * @return true|string
	 */
	public static function validate( array $settings, $title, $body, array $options, $check = null ) {
		$template  = (array) ( $settings['template'] ?? array() );
		$names     = array();
		$variables = (array) ( $template['variables'] ?? array() );
		if ( ! $variables || count( $variables ) > 12 ) {
			return __( 'A template needs between 1 and 12 variables.', 'ohmylms' ); }
		foreach ( $variables as $def ) {
			$name = is_array( $def ) ? (string) ( $def['name'] ?? '' ) : '';
			if ( ! preg_match( self::NAME, $name ) || isset( $names[ $name ] ) ) {
				return __( 'Variable names are single letters (a to z, except e) and must be unique.', 'ohmylms' ); }
			$names[ $name ] = true;
			$type           = $def['type'] ?? '';
			if ( ! in_array( $type, self::TYPES, true ) ) {
				return sprintf( /* translators: %s: variable name */ __( 'Variable %s needs a type: int, decimal, choice or expr.', 'ohmylms' ), $name ); }
			if ( in_array( $type, array( 'int', 'decimal' ), true ) && ( ! is_numeric( $def['min'] ?? null ) || ! is_numeric( $def['max'] ?? null ) || (float) $def['max'] < (float) $def['min'] ) ) {
				return sprintf( /* translators: %s: text value. */
					__( 'Variable %s needs a minimum and a maximum that are numbers, with the maximum not smaller.', 'ohmylms' ),
					$name
				); }
			if ( 'choice' === $type && ( ! is_array( $def['values'] ?? null ) || ! $def['values'] || count( $def['values'] ) > 50 ) ) {
				return sprintf( /* translators: %s: text value. */
					__( 'Variable %s needs between 1 and 50 values to choose from.', 'ohmylms' ),
					$name
				); }
			if ( 'expr' === $type && Expression::parse( $def['expr'] ?? '' ) === null ) {
				return sprintf( /* translators: %s: text value. */
					__( 'Variable %s has a formula that cannot be read.', 'ohmylms' ),
					$name
				); }
		}
		$constraints = (array) ( $template['constraints'] ?? array() );
		if ( count( $constraints ) > 10 ) {
			return __( 'A template can have at most 10 conditions.', 'ohmylms' ); }
		foreach ( (array) ( $template['set'] ?? array() ) as $rule ) {
			if ( ! is_array( $rule ) || ! preg_match( '/^[A-Za-z0-9_.-]{1,80}$/', (string) ( $rule['path'] ?? '' ) ) || Expression::parse( $rule['expr'] ?? '' ) === null ) {
				return __( 'Each computed setting needs a setting name and a formula that can be read.', 'ohmylms' ); }
		}
		$signatures = array();
		for ( $i = 1; $i <= self::SAMPLES; $i++ ) {
			$r = self::apply( $title, $body, $settings, $options, self::seed_from( 'sample' . $i ) );
			if ( ! $r['ok'] ) {
				return __( 'The conditions are too strict: no numbers satisfy them. Widen a range or remove a condition.', 'ohmylms' ); }
			if ( $r['errors'] ) {
				return sprintf( /* translators: %s: formula text */ __( 'This formula cannot be worked out: {{%s}}. Check the variable names and the arithmetic.', 'ohmylms' ), $r['errors'][0] ); }
			$signatures[ self::signature( $r['params'] ) ] = true;
			if ( is_callable( $check ) ) {
				$result = call_user_func( $check, $r['settings'] );
				if ( true !== $result ) {
					return sprintf( /* translators: 1: sample number 2: reason */ __( 'Example %1$d is not a valid question: %2$s', 'ohmylms' ), $i, is_string( $result ) ? $result : __( 'invalid settings.', 'ohmylms' ) ); }
			}
		}
		if ( count( $signatures ) < 2 ) {
			return __( 'The numbers never change. Give a variable a range of values.', 'ohmylms' ); }
		return true;
	}

	/** Correct answer in words, for previews and feedback.
	 *
	 * @param mixed $type Type.
	 * @param array $settings Configuration settings.
	 * @param array $options Operation options.
	 */
	public static function expected_text( $type, array $settings, array $options ) {
		if ( Interactive::is_interactive( $type ) ) {
			return implode( ', ', Interactive::expected( $type, $settings ) ); }
		if ( 'numerical' === $type ) {
			return (string) ( $settings['answer'] ?? '' ); }
		$correct = array_filter(
			$options,
			static function ( $option ) {
				return ! empty( $option['is_correct'] );
			}
		);
		return implode( ', ', array_map( 'strval', array_column( $correct, 'answer' ) ) );
	}

	/**
	 * A few concrete examples of a draft template, for the author to read before saving.
	 *
	 * @return array[] each: seed, params, title, body, expected, errors
	 * @param mixed $type Type.
	 * @param mixed $title Title.
	 * @param mixed $body Body.
	 * @param array $settings Configuration settings.
	 * @param array $options Operation options.
	 * @param int   $count Count.
	 */
	public static function samples( $type, $title, $body, array $settings, array $options, $count = 5 ) {
		$out = array();
		for ( $i = 1; $i <= max( 1, min( 10, (int) $count ) ); $i++ ) {
			$seed  = self::seed_from( 'preview' . $i . ':' . wp_rand() );
			$r     = self::apply( $title, $body, $settings, $options, $seed );
			$out[] = array(
				'seed'     => $seed,
				'ok'       => $r['ok'],
				'params'   => $r['params'],
				'title'    => $r['title'],
				'body'     => $r['body'],
				'expected' => self::expected_text( $type, $r['settings'], $r['options'] ),
				'errors'   => $r['errors'],
			);
		}
		return $out;
	}
}
