<?php
/**
 * Parser and validator for a cmi5 course structure (cmi5.xml).
 *
 * @package OhMyLMS\Cmi5
 */

namespace OhMyLMS\Cmi5;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- The DOMDocument and ZipArchive classes define these property names.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- InvalidPackage messages are plain text; whatever displays them escapes them.

/**
 * Reads cmi5.xml into plain arrays and reports every rule it breaks.
 *
 * The rules follow the cmi5 specification, section 13: a course with an ID, title and description,
 * blocks that group assignable units (AUs), and AUs with a URL, a moveOn rule, an optional mastery
 * score and a launch method. Parsing is namespace-agnostic (it reads local names), refuses any
 * DOCTYPE or entity declaration so no external entity can ever be loaded, and caps size, nesting and
 * the number of AUs.
 */
final class CourseStructure {

	const MAX_BYTES  = 5242880;
	const MAX_AUS    = 5000;
	const MAX_DEPTH  = 20;
	const MAX_ERRORS = 25;
	const MOVE_ON    = array( 'Passed', 'Completed', 'CompletedAndPassed', 'CompletedOrPassed', 'NotApplicable' );
	const METHODS    = array( 'AnyWindow', 'OwnWindow' );

	/**
	 * Problems found while parsing.
	 *
	 * @var string[]
	 */
	private $errors = array();

	/**
	 * Things worth knowing that do not stop an import.
	 *
	 * @var string[]
	 */
	private $warnings = array();

	/**
	 * IDs seen so far (course, blocks and AUs share one namespace).
	 *
	 * @var array<string,bool>
	 */
	private $ids = array();

	/**
	 * AUs counted so far.
	 *
	 * @var int
	 */
	private $au_count = 0;

	/**
	 * Preferred language for titles and descriptions.
	 *
	 * @var string
	 */
	private $locale;

	/**
	 * Constructor.
	 *
	 * @param string $locale Preferred language tag.
	 */
	private function __construct( $locale ) {
		$this->locale = strtolower( str_replace( '_', '-', (string) $locale ) );
	}

	/**
	 * Parse a course structure.
	 *
	 * @param string $xml    Contents of cmi5.xml.
	 * @param string $locale Preferred language tag for titles, for example en-US.
	 * @return array course (id, title, description), nodes (blocks and AUs in document order), warnings.
	 * @throws InvalidPackage When the structure breaks any rule.
	 */
	public static function parse( $xml, $locale = 'en-US' ) {
		$xml = (string) $xml;
		if ( '' === trim( $xml ) ) {
			throw new InvalidPackage( array( 'cmi5.xml is empty.' ) );
		}
		if ( strlen( $xml ) > self::MAX_BYTES ) {
			throw new InvalidPackage( array( 'cmi5.xml is larger than 5 MB.' ) );
		}
		// A course structure never needs a DTD. Refusing them outright means no entity can be expanded or fetched.
		if ( preg_match( '/<!\s*(DOCTYPE|ENTITY)/i', $xml ) ) {
			throw new InvalidPackage( array( 'cmi5.xml must not contain a DOCTYPE or entity declarations.' ) );
		}
		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();
		$loaded   = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_NOBLANKS );
		$problems = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement ) {
			$messages = array();
			foreach ( array_slice( $problems, 0, 3 ) as $problem ) {
				$messages[] = sprintf( 'cmi5.xml is not well-formed XML (line %d): %s', (int) $problem->line, trim( $problem->message ) );
			}
			throw new InvalidPackage( $messages ? $messages : array( 'cmi5.xml is not well-formed XML.' ) );
		}

		$parser = new self( $locale );

		return $parser->read( $dom->documentElement );
	}

	/**
	 * Read the root element.
	 *
	 * @param \DOMElement $root Root element.
	 * @return array See parse().
	 * @throws InvalidPackage When the structure breaks any rule.
	 */
	private function read( \DOMElement $root ) {
		if ( 'courseStructure' !== $root->localName ) {
			throw new InvalidPackage( array( 'The root element must be <courseStructure>, found <' . $root->localName . '>.' ) );
		}
		$course = null;
		$nodes  = array();
		foreach ( $this->elements( $root ) as $element ) {
			switch ( $element->localName ) {
				case 'course':
					if ( null !== $course ) {
						$this->error( 'There is more than one <course> element.' );
						break;
					}
					$course = $this->read_course( $element );
					break;
				case 'block':
					$nodes[] = $this->read_block( $element, 1 );
					break;
				case 'au':
					$nodes[] = $this->read_au( $element );
					break;
				case 'objectives':
					break;
				default:
					$this->warnings[] = 'Unknown element <' . $element->localName . '> was ignored.';
			}
		}
		if ( null === $course ) {
			$this->error( 'The <course> element is missing.' );
		}
		if ( 0 === $this->au_count ) {
			$this->error( 'The course has no assignable units (<au> elements).' );
		}
		if ( $this->errors ) {
			throw new InvalidPackage( $this->errors );
		}

		return array(
			'course'   => $course,
			'nodes'    => $nodes,
			'warnings' => $this->warnings,
		);
	}

	/**
	 * The course itself.
	 *
	 * @param \DOMElement $element The <course> element.
	 * @return array id, title, description.
	 */
	private function read_course( \DOMElement $element ) {
		$id = $this->take_id( $element, 'The course' );

		return array(
			'id'          => $id,
			'title'       => $this->required_text( $element, 'title', 'The course' ),
			'description' => $this->lang_text( $element, 'description' ),
		);
	}

	/**
	 * A block and everything inside it.
	 *
	 * @param \DOMElement $element The <block> element.
	 * @param int         $depth   Nesting depth, starting at 1.
	 * @return array
	 */
	private function read_block( \DOMElement $element, $depth ) {
		$id    = $this->take_id( $element, 'A block' );
		$label = '' !== $id ? 'Block ' . $id : 'A block';
		$node  = array(
			'type'        => 'block',
			'id'          => $id,
			'title'       => $this->required_text( $element, 'title', $label ),
			'description' => $this->lang_text( $element, 'description' ),
			'children'    => array(),
		);
		if ( $depth > self::MAX_DEPTH ) {
			$this->error( $label . ' is nested more than ' . self::MAX_DEPTH . ' levels deep.' );

			return $node;
		}
		foreach ( $this->elements( $element ) as $child ) {
			if ( 'block' === $child->localName ) {
				$node['children'][] = $this->read_block( $child, $depth + 1 );
			} elseif ( 'au' === $child->localName ) {
				$node['children'][] = $this->read_au( $child );
			}
		}
		if ( ! $node['children'] ) {
			$this->warnings[] = $label . ' contains nothing.';
		}

		return $node;
	}

	/**
	 * An assignable unit.
	 *
	 * @param \DOMElement $element The <au> element.
	 * @return array
	 */
	private function read_au( \DOMElement $element ) {
		$id    = $this->take_id( $element, 'An AU' );
		$label = '' !== $id ? 'AU ' . $id : 'An AU';
		++$this->au_count;
		if ( self::MAX_AUS + 1 === $this->au_count ) {
			$this->error( 'The course has more than ' . self::MAX_AUS . ' assignable units.' );
		}

		$move_on = $this->option( $element, 'moveOn' );
		if ( '' === $move_on ) {
			$move_on = 'NotApplicable';
		} elseif ( ! in_array( $move_on, self::MOVE_ON, true ) ) {
			$this->error( $label . ' has moveOn "' . $move_on . '"; it must be one of ' . implode( ', ', self::MOVE_ON ) . '.' );
		}
		$method = $this->option( $element, 'launchMethod' );
		if ( '' === $method ) {
			$method = 'AnyWindow';
		} elseif ( ! in_array( $method, self::METHODS, true ) ) {
			$this->error( $label . ' has launchMethod "' . $method . '"; it must be AnyWindow or OwnWindow.' );
		}
		$mastery = $this->option( $element, 'masteryScore' );
		if ( '' !== $mastery ) {
			if ( 1 === preg_match( '/^(?:0(?:\.\d{1,4})?|1(?:\.0{1,4})?)$/', $mastery ) ) {
				$mastery = (float) $mastery;
			} else {
				$this->error( $label . ' has masteryScore "' . $mastery . '"; it must be a decimal from 0 to 1 with at most 4 decimal places.' );
				$mastery = '';
			}
		}
		$type = $this->option( $element, 'activityType' );
		if ( '' !== $type && ! $this->is_iri( $type ) ) {
			$this->error( $label . ' has an activityType that is not an IRI.' );
			$type = '';
		}
		$parameters = $this->child_text( $element, 'launchParameters' );
		if ( strlen( $parameters ) > 4096 ) {
			$this->error( $label . ' has launchParameters longer than 4096 characters.' );
			$parameters = '';
		}
		if ( $this->child( $element, 'entitlementKey' ) ) {
			$this->warnings[] = $label . ' has an entitlementKey, which is not supported and was ignored.';
		}

		return array(
			'type'             => 'au',
			'id'               => $id,
			'title'            => $this->required_text( $element, 'title', $label ),
			'description'      => $this->lang_text( $element, 'description' ),
			'url'              => $this->check_url( $this->child_text( $element, 'url' ), $label ),
			'moveOn'           => $move_on,
			'masteryScore'     => '' === $mastery ? null : $mastery,
			'launchMethod'     => $method,
			'activityType'     => $type,
			'launchParameters' => $parameters,
		);
	}

	/**
	 * Validate an AU URL: relative to the package, or a fully qualified http(s) address.
	 *
	 * @param string $url   URL from the course structure.
	 * @param string $label What it belongs to, for messages.
	 * @return string The URL, or an empty string when it was rejected.
	 */
	private function check_url( $url, $label ) {
		if ( '' === $url ) {
			$this->error( $label . ' has no <url>.' );

			return '';
		}
		if ( preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
			$this->error( $label . ' has a URL with spaces, control characters or backslashes.' );

			return '';
		}
		if ( preg_match( '~^([A-Za-z][A-Za-z0-9+.\-]*):~', $url, $match ) ) {
			$scheme = strtolower( $match[1] );
			$host   = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				$this->error( $label . ' has a URL with the scheme "' . $scheme . '"; only relative paths and http(s) addresses are supported.' );

				return '';
			}
			if ( '' === $host ) {
				$this->error( $label . ' has a URL without a host.' );

				return '';
			}

			return $url;
		}
		if ( 0 === strpos( $url, '/' ) ) {
			$this->error( $label . ' has a URL that starts with a slash; use a path relative to the package or a fully qualified URL.' );

			return '';
		}
		$path = substr( $url, 0, strcspn( $url, '?#' ) );
		if ( '' === $path || in_array( '..', explode( '/', $path ), true ) ) {
			$this->error( $label . ' has a URL that is empty or leaves the package folder.' );

			return '';
		}

		return 0 === strpos( $url, './' ) ? substr( $url, 2 ) : $url;
	}

	/**
	 * Read an ID and make sure it is a unique IRI.
	 *
	 * @param \DOMElement $element Element.
	 * @param string      $label   What it is, for messages.
	 * @return string The ID, or an empty string when it is unusable.
	 */
	private function take_id( \DOMElement $element, $label ) {
		$id = $this->option( $element, 'id' );
		if ( '' === $id ) {
			$this->error( $label . ' has no id.' );

			return '';
		}
		if ( ! $this->is_iri( $id ) ) {
			$this->error( $label . ' has an id that is not an IRI: "' . substr( $id, 0, 80 ) . '".' );

			return '';
		}
		if ( isset( $this->ids[ $id ] ) ) {
			$this->error( 'The id "' . substr( $id, 0, 80 ) . '" is used more than once; IDs must be unique across the course, blocks and AUs.' );

			return '';
		}
		$this->ids[ $id ] = true;

		return $id;
	}

	/**
	 * Whether a value looks like an absolute IRI (a scheme, a colon and something after it).
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private function is_iri( $value ) {
		return strlen( $value ) <= 2000 && 1 === preg_match( '~^[A-Za-z][A-Za-z0-9+.\-]*:\S+$~', $value );
	}

	/**
	 * A title that must be present.
	 *
	 * @param \DOMElement $element Element.
	 * @param string      $name    Child element name.
	 * @param string      $label   What it belongs to, for messages.
	 * @return string
	 */
	private function required_text( \DOMElement $element, $name, $label ) {
		$text = $this->lang_text( $element, $name );
		if ( '' === $text ) {
			$this->error( $label . ' has no ' . $name . '.' );
		}

		return $text;
	}

	/**
	 * Text from a langstring container, in the best available language.
	 *
	 * @param \DOMElement $element Element.
	 * @param string      $name    Child element name, for example title.
	 * @return string
	 */
	private function lang_text( \DOMElement $element, $name ) {
		$container = $this->child( $element, $name );
		if ( ! $container ) {
			return '';
		}
		$by_lang = array();
		foreach ( $this->elements( $container ) as $string ) {
			if ( 'langstring' !== $string->localName ) {
				continue;
			}
			$lang = $string->hasAttribute( 'lang' ) ? $string->getAttribute( 'lang' ) : $string->getAttributeNS( 'http://www.w3.org/XML/1998/namespace', 'lang' );
			$text = trim( preg_replace( '/\s+/u', ' ', (string) $string->textContent ) );
			if ( '' !== $text ) {
				$by_lang[ strtolower( str_replace( '_', '-', $lang ) ) ] = $text;
			}
		}
		if ( ! $by_lang ) {
			return trim( preg_replace( '/\s+/u', ' ', (string) $container->textContent ) );
		}
		// Best match first: the exact language, then the same language in another region, then English, then anything.
		if ( isset( $by_lang[ $this->locale ] ) ) {
			return $by_lang[ $this->locale ];
		}
		$primary = explode( '-', $this->locale )[0];
		foreach ( $by_lang as $lang => $text ) {
			if ( explode( '-', (string) $lang )[0] === $primary ) {
				return $text;
			}
		}
		foreach ( array( 'en-us', 'en' ) as $wanted ) {
			if ( isset( $by_lang[ $wanted ] ) ) {
				return $by_lang[ $wanted ];
			}
		}

		return (string) reset( $by_lang );
	}

	/**
	 * A setting given as an attribute, or as a child element of the same name.
	 *
	 * @param \DOMElement $element Element.
	 * @param string      $name    Setting name.
	 * @return string
	 */
	private function option( \DOMElement $element, $name ) {
		if ( $element->hasAttribute( $name ) ) {
			return trim( $element->getAttribute( $name ) );
		}

		return $this->child_text( $element, $name );
	}

	/**
	 * Trimmed text of a direct child element.
	 *
	 * @param \DOMElement $element Element.
	 * @param string      $name    Child element name.
	 * @return string
	 */
	private function child_text( \DOMElement $element, $name ) {
		$child = $this->child( $element, $name );

		return $child ? trim( (string) $child->textContent ) : '';
	}

	/**
	 * First direct child element with a local name.
	 *
	 * @param \DOMElement $element Element.
	 * @param string      $name    Local name.
	 * @return \DOMElement|null
	 */
	private function child( \DOMElement $element, $name ) {
		foreach ( $this->elements( $element ) as $child ) {
			if ( $child->localName === $name ) {
				return $child;
			}
		}

		return null;
	}

	/**
	 * Direct child elements.
	 *
	 * @param \DOMElement $element Element.
	 * @return \DOMElement[]
	 */
	private function elements( \DOMElement $element ) {
		$out = array();
		foreach ( $element->childNodes as $node ) {
			if ( $node instanceof \DOMElement ) {
				$out[] = $node;
			}
		}

		return $out;
	}

	/**
	 * Record a problem, up to a limit.
	 *
	 * @param string $message Problem.
	 */
	private function error( $message ) {
		if ( count( $this->errors ) < self::MAX_ERRORS ) {
			$this->errors[] = $message;
		} elseif ( count( $this->errors ) === self::MAX_ERRORS ) {
			$this->errors[] = 'More problems were found; fix these and upload again.';
		}
	}

	/**
	 * Every AU in document order, each with the IDs of the blocks around it.
	 *
	 * @param array    $nodes   Nodes from parse().
	 * @param string[] $parents Block IDs above these nodes.
	 * @return array[]
	 */
	public static function flatten_aus( array $nodes, array $parents = array() ) {
		$aus = array();
		foreach ( $nodes as $node ) {
			if ( 'block' === $node['type'] ) {
				$aus = array_merge( $aus, self::flatten_aus( $node['children'], array_merge( $parents, array( $node['id'] ) ) ) );
			} else {
				$node['blocks'] = $parents;
				$aus[]          = $node;
			}
		}

		return $aus;
	}

	/**
	 * Relative AU URLs that point at files the package does not contain.
	 *
	 * @param array    $nodes Nodes from parse().
	 * @param string[] $files Relative paths of the files in the package.
	 * @return string[] Problems, one per missing file.
	 */
	public static function missing_files( array $nodes, array $files ) {
		$have    = array_flip( $files );
		$missing = array();
		foreach ( self::flatten_aus( $nodes ) as $au ) {
			if ( preg_match( '~^[A-Za-z][A-Za-z0-9+.\-]*:~', $au['url'] ) ) {
				continue;
			}
			$path = rawurldecode( substr( $au['url'], 0, strcspn( $au['url'], '?#' ) ) );
			if ( ! isset( $have[ $path ] ) ) {
				$missing[] = 'AU ' . $au['id'] . ' points to "' . $path . '", which is not in the package.';
			}
		}

		return $missing;
	}
}
