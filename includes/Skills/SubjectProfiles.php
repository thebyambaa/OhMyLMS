<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Subject profiles: the domains, levels and AI context of each subject's skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Skills are not tied to one subject. A shared skill's subject (its `_ohmylms_shared_subject` text)
 * selects a profile by key; a subject without its own profile uses the site's default profile.
 *
 * Two profiles are built in. Generic has no domains and plain levels. Mathematics keeps the domains
 * and the school grade scale (1–12, then 13 for beyond grade 12) the plugin used before profiles
 * existed, so stored placements read the same. Sites edit, add and remove profiles; editing a built-in
 * profile stores a copy, and removing that copy restores the built-in.
 */
final class SubjectProfiles {

	const OPTION      = 'ohmylms_subject_profiles';
	const GENERIC     = 'generic';
	const MATHEMATICS = 'mathematics';
	const MAX_STORED  = 100;

	/**
	 * Default profile key resolved during this request.
	 *
	 * @var string|null
	 */
	private static $default = null;

	/**
	 * Built-in profiles keyed by profile key.
	 *
	 * @return array<string,array>
	 */
	public static function builtin() {
		$grades = array();
		for ( $grade = 1; $grade <= 12; $grade++ ) {
			/* translators: %d: school grade. */
			$grades[] = sprintf( __( 'Grade %d', 'ohmylms' ), $grade );
		}
		$grades[] = __( 'Beyond grade 12', 'ohmylms' );
		$levels   = array();
		for ( $level = 1; $level <= 10; $level++ ) {
			/* translators: %d: level number. */
			$levels[] = sprintf( __( 'Level %d', 'ohmylms' ), $level );
		}
		$domains = array();
		foreach ( array(
			'number'       => __( 'Number', 'ohmylms' ),
			'algebra'      => __( 'Algebra', 'ohmylms' ),
			'functions'    => __( 'Functions and graphs', 'ohmylms' ),
			'geometry'     => __( 'Geometry', 'ohmylms' ),
			'measurement'  => __( 'Measurement', 'ohmylms' ),
			'trigonometry' => __( 'Trigonometry', 'ohmylms' ),
			'vectors'      => __( 'Vectors and matrices', 'ohmylms' ),
			'statistics'   => __( 'Statistics', 'ohmylms' ),
			'probability'  => __( 'Probability', 'ohmylms' ),
			'calculus'     => __( 'Calculus', 'ohmylms' ),
			'mechanics'    => __( 'Mechanics', 'ohmylms' ),
			'discrete'     => __( 'Discrete mathematics', 'ohmylms' ),
		) as $id => $label ) {
			$domains[] = array(
				'id'    => $id,
				'label' => $label,
			);
		}
		return array(
			self::GENERIC     => array(
				'label'    => __( 'Generic', 'ohmylms' ),
				'language' => '',
				'domains'  => array(),
				'levels'   => $levels,
				'ai'       => array(
					'role'        => 'curriculum analyst',
					'context'     => '',
					'level_hints' => '',
				),
			),
			self::MATHEMATICS => array(
				'label'    => __( 'Mathematics', 'ohmylms' ),
				'language' => '',
				'domains'  => $domains,
				'levels'   => $grades,
				'ai'       => array(
					'role'        => 'mathematics curriculum analyst',
					'context'     => 'School and university-entrance mathematics.',
					'level_hints' => 'Levels are school grades 1 to 12, or 13 for beyond grade 12 such as the second year of A level. A "Grade 7" syllabus objective is grade 7; for IGCSE use 9-10, AS level 11-12, A level 12-13, Digital SAT 9-12, unless the content clearly belongs elsewhere.',
				),
			),
		);
	}

	/**
	 * Stored profiles and the chosen default.
	 *
	 * @return array profiles (key => profile), default.
	 */
	private static function stored() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'profiles'   => is_array( $stored['profiles'] ?? null ) ? $stored['profiles'] : array(),
			'default'    => is_string( $stored['default'] ?? null ) ? $stored['default'] : '',
			'curriculum' => is_array( $stored['curriculum'] ?? null ) ? $stored['curriculum'] : array(),
		);
	}

	/**
	 * Every profile keyed by key, stored profiles over built-in ones.
	 *
	 * @return array<string,array> label, language, domains, levels, ai, key, builtin, customized.
	 */
	public static function all() {
		$builtin = self::builtin();
		$result  = array();
		foreach ( $builtin as $key => $profile ) {
			$result[ $key ] = $profile + array(
				'key'        => $key,
				'builtin'    => true,
				'customized' => false,
			);
		}
		foreach ( self::stored()['profiles'] as $key => $profile ) {
			$profile = is_array( $profile ) ? SubjectProfileModel::normalize( $profile ) : 'label';
			if ( is_array( $profile ) && SubjectProfileModel::key( $key ) === $key ) {
				$result[ $key ] = $profile + array(
					'key'        => $key,
					'builtin'    => isset( $builtin[ $key ] ),
					'customized' => true,
				);
			}
		}
		foreach ( $result as $key => &$profile ) {
			$profile['curriculum_id'] = (int) ( self::stored()['curriculum'][ $key ] ?? 0 );
		}
		unset( $profile );
		return $result;
	}

	/**
	 * Connect existing profiles to subjects when an administrator opens the plugin.
	 * Generic remains a fallback skill scheme rather than a browseable subject.
	 */
	public static function upgrade_subjects() {
		if ( ! Schema::ready() || ! Access::can_manage() ) {
			return;
		}
		$stored   = self::stored();
		$profiles = self::all();
		$items    = array_column( Items::all(), null, 'id' );
		foreach ( $profiles as $key => $profile ) {
			if ( self::GENERIC === $key ) {
				continue;
			}
			$item = $items[ $stored['curriculum'][ $key ] ?? 0 ] ?? null;
			if ( ! $item || 'subject' !== $item['item_type'] || sanitize_text_field( $profile['label'] ) !== $item['name'] ) {
				Items::exclusive(
					static function () {
						return self::write(
							self::revision(),
							static function ( array $current ) {
								foreach ( self::all() as $profile_key => $subject_profile ) {
									$id = self::subject_id( $profile_key, $subject_profile, $current );
									if ( is_wp_error( $id ) ) {
										throw new \RuntimeException( 'Subject migration failed.' );
									}
									$current['curriculum'][ $profile_key ] = $id;
								}
								return $current;
							}
						);
					}
				);
				return;
			}
		}
	}

	/**
	 * Reuse or create a subject while the caller holds the curriculum write lock.
	 *
	 * @param string $key Profile key.
	 * @param array  $profile Normalized profile.
	 * @param array  $stored Stored profiles and subject identities.
	 * @return int|\WP_Error Curriculum subject ID; zero for the Generic fallback.
	 * @throws \RuntimeException When a subject name cannot be updated.
	 */
	private static function subject_id( $key, array $profile, array $stored ) {
		global $wpdb;
		if ( self::GENERIC === $key ) {
			return 0;
		}
		$item = Items::get( (int) ( $stored['curriculum'][ $key ] ?? 0 ) );
		if ( ! $item || 'subject' !== $item['item_type'] ) {
			$item = null;
			foreach ( Items::all() as $candidate ) {
				if ( 'subject' === $candidate['item_type'] && in_array( SubjectProfileModel::key( $candidate['name'] ), array( $key, SubjectProfileModel::key( $profile['label'] ) ), true ) ) {
					$item = $candidate;
					break;
				}
			}
		}
		$name = sanitize_text_field( $profile['label'] );
		if ( $item ) {
			if ( $name !== $item['name'] && false === $wpdb->update(
				Items::table(),
				array(
					'name'       => $name,
					'updated_at' => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $item['id'] )
			) ) {
				throw new \RuntimeException( 'Subject name update failed.' );
			}
			return (int) $item['id'];
		}
		$fields = Items::clean(
			array(
				'name'      => $name,
				'item_type' => 'subject',
			)
		);
		return is_wp_error( $fields ) ? $fields : Items::insert_locked( $fields, 0 );
	}

	/**
	 * Key of the profile used for subjects without their own: the saved choice, otherwise Mathematics on
	 * sites whose shared skills are mathematics (their behaviour before profiles), else Generic.
	 *
	 * @return string
	 */
	public static function default_key() {
		if ( null !== self::$default ) {
			return self::$default;
		}
		$chosen = self::stored()['default'];
		if ( '' !== $chosen && isset( self::all()[ $chosen ] ) ) {
			self::$default = $chosen;
			return $chosen;
		}
		self::$default = self::GENERIC;
		foreach ( self::subjects_in_use() as $subject => $count ) {
			if ( self::MATHEMATICS === SubjectProfileModel::key( $subject ) && $count > 0 ) {
				self::$default = self::MATHEMATICS;
			}
		}
		return self::$default;
	}

	/**
	 * Subjects of shared skills with their skill counts.
	 *
	 * @return array<string,int>
	 */
	public static function subjects_in_use() {
		global $wpdb;
		$result = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One grouped read of the shared library; results change with every skill save.
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT meta_value AS subject, COUNT(*) AS skills FROM %i WHERE meta_key=%s GROUP BY meta_value', $wpdb->termmeta, Comparison::SUBJECT_META ), ARRAY_A ) as $row ) {
			if ( '' !== (string) $row['subject'] ) {
				$result[ (string) $row['subject'] ] = (int) $row['skills'];
			}
		}
		return $result;
	}

	/**
	 * The profile of a subject: its own when one exists, otherwise the default profile.
	 *
	 * @param string $subject Subject name or key, or empty for the default.
	 * @return array Profile with key.
	 */
	public static function get( $subject = '' ) {
		$all = self::all();
		$key = SubjectProfileModel::key( $subject );
		return $all[ '' !== $key && isset( $all[ $key ] ) ? $key : self::default_key() ] ?? $all[ self::GENERIC ];
	}

	/**
	 * Revision of the stored profiles, for guarded saves.
	 *
	 * @return string
	 */
	public static function revision() {
		return substr( hash( 'sha256', (string) wp_json_encode( self::stored() ) ), 0, 16 );
	}

	/**
	 * Profiles, the default, the subjects in use and the revision, for the settings screen and tools.
	 *
	 * @return array
	 */
	public static function payload() {
		$profiles = array();
		foreach ( self::all() as $profile ) {
			$profiles[] = $profile;
		}
		$subjects = array();
		foreach ( self::subjects_in_use() as $subject => $count ) {
			$subjects[] = array(
				'subject' => $subject,
				'skills'  => $count,
				'profile' => self::get( $subject )['key'],
			);
		}
		return array(
			'profiles' => $profiles,
			'default'  => self::default_key(),
			'subjects' => $subjects,
			'limits'   => array(
				'domains' => SubjectProfileModel::MAX_DOMAINS,
				'levels'  => SubjectProfileModel::MAX_LEVELS,
				'text'    => SubjectProfileModel::MAX_TEXT,
			),
			'revision' => self::revision(),
		);
	}

	/**
	 * Save one profile. A new profile's key comes from its label; an existing key keeps its key.
	 *
	 * @param array  $input    key (optional), label, language, domains, levels, ai.
	 * @param string $expected Revision the change was made against.
	 * @return array|\WP_Error The new payload.
	 */
	public static function save( array $input, $expected ) {
		$input['label'] = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
		$profile        = SubjectProfileModel::normalize( $input );
		if ( is_string( $profile ) ) {
			return self::invalid( $profile );
		}
		$key = SubjectProfileModel::key( '' !== (string) ( $input['key'] ?? '' ) ? $input['key'] : $profile['label'] );
		return Items::exclusive(
			static function () use ( $expected, $key, $profile ) {
				return self::write(
					$expected,
					static function ( array $stored ) use ( $key, $profile ) {
						if ( ! isset( $stored['profiles'][ $key ] ) && count( $stored['profiles'] ) >= self::MAX_STORED ) {
							return self::invalid( 'count' );
						}
						$id = self::subject_id( $key, $profile, $stored );
						if ( is_wp_error( $id ) ) {
							return $id;
						}
						$stored['curriculum'][ $key ] = $id;
						$stored['profiles'][ $key ]   = $profile;
						return $stored;
					}
				);
			}
		);
	}

	/**
	 * Remove a stored profile. A customised built-in profile returns to its built-in values.
	 *
	 * @param string $key      Profile key.
	 * @param string $expected Revision the change was made against.
	 * @return array|\WP_Error The new payload.
	 */
	public static function remove( $key, $expected ) {
		$key = SubjectProfileModel::key( $key );
		return self::write(
			$expected,
			static function ( array $stored ) use ( $key ) {
				if ( ! isset( $stored['profiles'][ $key ] ) ) {
					return Access::error( 'ohmylms_subject_profile_missing', __( 'This profile has no saved changes to remove.', 'ohmylms' ), 404 );
				}
				unset( $stored['profiles'][ $key ] );
				if ( $stored['default'] === $key && ! isset( self::builtin()[ $key ] ) ) {
					$stored['default'] = '';
				}
				return $stored;
			}
		);
	}

	/**
	 * Choose the profile for subjects without their own.
	 *
	 * @param string $key      Profile key.
	 * @param string $expected Revision the change was made against.
	 * @return array|\WP_Error The new payload.
	 */
	public static function set_default( $key, $expected ) {
		$key = SubjectProfileModel::key( $key );
		if ( ! isset( self::all()[ $key ] ) ) {
			return Access::error( 'ohmylms_subject_profile_missing', __( 'Choose an existing profile.', 'ohmylms' ), 404 );
		}
		return self::write(
			$expected,
			static function ( array $stored ) use ( $key ) {
				$stored['default'] = $key;
				return $stored;
			}
		);
	}

	/**
	 * Apply a change to the stored profiles when the revision still matches.
	 *
	 * @param string   $expected Revision the change was made against.
	 * @param callable $change   Receives and returns the stored array, or a WP_Error.
	 * @return array|\WP_Error The new payload.
	 * @throws \RuntimeException When the profile option cannot be saved.
	 */
	private static function write( $expected, callable $change ) {
		if ( ! hash_equals( self::revision(), (string) $expected ) ) {
			return Access::error( 'ohmylms_subject_profile_conflict', __( 'Subject profiles changed since you opened them. Reload and try again.', 'ohmylms' ), 409 );
		}
		$stored = $change( self::stored() );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		if ( ! update_option( self::OPTION, $stored, false ) && get_option( self::OPTION ) !== $stored ) {
			throw new \RuntimeException( 'Subject profile save failed.' );
		}
		self::$default = null;
		return self::payload();
	}

	/**
	 * Error for a rejected profile field.
	 *
	 * @param string $code Field code from the model.
	 * @return \WP_Error
	 */
	private static function invalid( $code ) {
		$messages = array(
			'label'    => __( 'Enter a profile name of up to 100 characters.', 'ohmylms' ),
			'language' => __( 'Enter a language code such as en or mn, or leave it empty.', 'ohmylms' ),
			'domains'  => sprintf(
				/* translators: %d: most domains. */
				__( 'A profile can have up to %d domains.', 'ohmylms' ),
				SubjectProfileModel::MAX_DOMAINS
			),
			'domain'   => __( 'Each domain needs a unique id (lower-case letters, digits and hyphens, starting with a letter) and a name of up to 100 characters.', 'ohmylms' ),
			'levels'   => sprintf(
				/* translators: %d: most levels. */
				__( 'A profile needs between 1 and %d levels.', 'ohmylms' ),
				SubjectProfileModel::MAX_LEVELS
			),
			'level'    => __( 'Each level needs a name of up to 60 characters.', 'ohmylms' ),
			'ai'       => __( 'Keep each AI context field within 2000 characters.', 'ohmylms' ),
			'count'    => __( 'This site already has the most subject profiles it can store.', 'ohmylms' ),
		);
		return Access::error( 'ohmylms_subject_profile_invalid', $messages[ $code ] ?? $messages['label'] );
	}
}
