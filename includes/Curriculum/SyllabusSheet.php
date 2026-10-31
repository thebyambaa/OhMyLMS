<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Cell edits from the syllabus spreadsheet, applied together or not at all.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * The spreadsheet view edits names and codes of a syllabus's content items, skill groups and skills.
 * Each change names the value the author saw (`previous`). Under the curriculum lock, every change is
 * compared with the stored value first; any difference refuses the whole batch with 409 and lists the
 * conflicting cells. Otherwise the existing domain writes run inside one transaction, so a failure
 * part-way leaves nothing changed. Skill codes stay unique in the syllabus, and swapping two codes in
 * one batch is allowed.
 */
final class SyllabusSheet {

	/** Most cells in one batch. */
	const MAX_CHANGES = 500;

	/** Code length limits by kind. */
	const CODE_LIMITS = array(
		'item'  => SyllabusRows::CONTENT_CODE_LIMIT,
		'group' => SyllabusRows::GROUP_CODE_LIMIT,
		'skill' => SyllabusRows::SKILL_CODE_LIMIT,
	);

	/**
	 * Apply a batch of cell changes.
	 *
	 * @param int     $syllabus_id Syllabus ID.
	 * @param array[] $changes     kind (item, group, skill), id, field (name, code), previous, value.
	 * @return true|\WP_Error
	 */
	public static function apply( $syllabus_id, array $changes ) {
		global $wpdb;
		$syllabus_id = (int) $syllabus_id;
		$clean       = self::validate( $syllabus_id, $changes );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$lock = Items::LOCK . '-' . md5( $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Non-cacheable domain advisory lock.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_syllabus_busy', __( 'Another author is changing the syllabus. Try again.', 'ohmylms' ), 409 );
		}
		$failure = null;
		try {
			$outline = Syllabus::outline( $syllabus_id );
			if ( is_wp_error( $outline ) ) {
				return $outline;
			}
			$cells     = self::cells( $outline );
			$conflicts = array();
			foreach ( $clean as $change ) {
				$key = $change['kind'] . ':' . $change['id'];
				if ( ! isset( $cells[ $key ] ) ) {
					return Access::error( 'ohmylms_syllabus_sheet_missing', __( 'A changed row is no longer in this syllabus. Reload the spreadsheet.', 'ohmylms' ), 409, array( 'change' => $change ) );
				}
				$current = $cells[ $key ][ $change['field'] ];
				if ( $current !== $change['previous'] ) {
					$conflicts[] = array(
						'kind'    => $change['kind'],
						'id'      => $change['id'],
						'field'   => $change['field'],
						'current' => $current,
					);
				}
			}
			if ( $conflicts ) {
				return Access::error( 'ohmylms_syllabus_sheet_stale', __( 'Someone changed these cells after you opened the spreadsheet. Nothing was saved.', 'ohmylms' ), 409, array( 'conflicts' => $conflicts ) );
			}
			$taken = self::duplicate_code( $cells, $clean );
			if ( '' !== $taken ) {
				/* translators: %s: skill code. */
				return Access::error( 'ohmylms_syllabus_code_taken', sprintf( __( 'Another skill in this syllabus already uses the code “%s”.', 'ohmylms' ), $taken ), 409 );
			}
			$entities = array();
			foreach ( $clean as $change ) {
				$entities[ $change['kind'] . ':' . $change['id'] ][ $change['field'] ] = $change['value'];
			}
			try {
				Transaction::run(
					static function () use ( $syllabus_id, $entities, &$failure ) {
						// Changed skill codes are cleared first so that two skills can swap codes.
						foreach ( $entities as $key => $fields ) {
							if ( 0 === strpos( $key, 'skill:' ) && isset( $fields['code'] ) ) {
								update_term_meta( (int) substr( $key, 6 ), Syllabus::CODE_META, '' );
							}
						}
						foreach ( $entities as $key => $fields ) {
							list( $kind, $id ) = explode( ':', $key );
							if ( 'item' === $kind ) {
								$saved = Items::update( (int) $id, $fields );
							} elseif ( 'group' === $kind ) {
								$saved = Syllabus::update_group( $syllabus_id, (int) $id, $fields );
							} else {
								$saved = Syllabus::update_skill( $syllabus_id, (int) $id, $fields );
							}
							if ( is_wp_error( $saved ) ) {
								$failure = $saved;
								throw new \RuntimeException( 'Spreadsheet change refused.' );
							}
						}
					}
				);
			} catch ( \Throwable $error ) {
				foreach ( array_keys( $entities ) as $key ) {
					if ( 0 === strpos( $key, 'skill:' ) ) {
						clean_term_cache( (int) substr( $key, 6 ), \OhMyLMS\Skills\Taxonomy::NAME );
						wp_cache_delete( (int) substr( $key, 6 ), 'term_meta' );
					}
				}
				return $failure ? $failure : Access::error( 'ohmylms_syllabus_sheet_failed', __( 'The spreadsheet could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
			}
			return true;
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the domain advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Check the shape and limits of every change before reading the syllabus.
	 *
	 * @param int     $syllabus_id Syllabus ID.
	 * @param array[] $changes     Changes.
	 * @return array[]|\WP_Error Normalised changes.
	 */
	private static function validate( $syllabus_id, array $changes ) {
		if ( ! $changes || count( $changes ) > self::MAX_CHANGES ) {
			/* translators: %d: most cells. */
			return Access::error( 'ohmylms_syllabus_invalid', sprintf( __( 'Save between 1 and %d cells at a time.', 'ohmylms' ), self::MAX_CHANGES ) );
		}
		$seen  = array();
		$clean = array();
		foreach ( $changes as $change ) {
			$kind  = (string) ( $change['kind'] ?? '' );
			$id    = (int) ( $change['id'] ?? 0 );
			$field = (string) ( $change['field'] ?? '' );
			if ( ! isset( self::CODE_LIMITS[ $kind ] ) || $id < 1 || ! in_array( $field, array( 'name', 'code' ), true ) || ! is_string( $change['previous'] ?? null ) || ! is_string( $change['value'] ?? null ) ) {
				return Access::error( 'ohmylms_syllabus_invalid', __( 'Each change needs a kind, an ID, a field and its previous and new text.', 'ohmylms' ) );
			}
			if ( 'item' === $kind && $id === (int) $syllabus_id ) {
				return Access::error( 'ohmylms_syllabus_invalid', __( 'Rename the syllabus itself in the curriculum.', 'ohmylms' ) );
			}
			$key = "$kind:$id:$field";
			if ( isset( $seen[ $key ] ) ) {
				return Access::error( 'ohmylms_syllabus_invalid', __( 'Each cell can change once per save.', 'ohmylms' ) );
			}
			$seen[ $key ] = true;
			$value        = SyllabusRows::line( $change['value'] );
			if ( 'name' === $field && ( '' === $value || mb_strlen( $value ) > SyllabusRows::NAME_LIMIT ) ) {
				/* translators: %d: most characters. */
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( __( 'Names need 1 to %d characters.', 'ohmylms' ), SyllabusRows::NAME_LIMIT ), 400, array( 'change' => $key ) );
			}
			if ( 'code' === $field && mb_strlen( $value ) > self::CODE_LIMITS[ $kind ] ) {
				/* translators: %d: most characters. */
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( __( 'This code can have at most %d characters.', 'ohmylms' ), self::CODE_LIMITS[ $kind ] ), 400, array( 'change' => $key ) );
			}
			$clean[] = array(
				'kind'     => $kind,
				'id'       => $id,
				'field'    => $field,
				'previous' => SyllabusRows::line( $change['previous'] ),
				'value'    => $value,
			);
		}
		return $clean;
	}

	/**
	 * Current names and codes of the syllabus's items, groups and skills, keyed `kind:id`.
	 *
	 * @param array $outline Syllabus::outline().
	 * @return array<string,array{name:string,code:string}>
	 */
	private static function cells( array $outline ) {
		$cells = array();
		foreach ( $outline['contents'] as $index => $content ) {
			if ( $index > 0 ) {
				$cells[ 'item:' . $content['id'] ] = array(
					'name' => SyllabusRows::line( $content['name'] ),
					'code' => SyllabusRows::line( $content['code'] ),
				);
			}
			foreach ( $content['groups'] as $group ) {
				$cells[ 'group:' . $group['id'] ] = array(
					'name' => SyllabusRows::line( $group['name'] ),
					'code' => SyllabusRows::line( $group['code'] ),
				);
				foreach ( $group['skills'] as $skill ) {
					$cells[ 'skill:' . $skill['term_id'] ] = array(
						'name' => SyllabusRows::line( $skill['name'] ),
						'code' => SyllabusRows::line( $skill['code'] ),
					);
				}
			}
		}
		return $cells;
	}

	/**
	 * The first skill code that would be used twice after the batch, or ''.
	 *
	 * @param array   $cells   Current cells.
	 * @param array[] $changes Normalised changes.
	 * @return string
	 */
	private static function duplicate_code( array $cells, array $changes ) {
		$codes = array();
		foreach ( $cells as $key => $cell ) {
			if ( 0 === strpos( $key, 'skill:' ) ) {
				$codes[ $key ] = $cell['code'];
			}
		}
		foreach ( $changes as $change ) {
			if ( 'skill' === $change['kind'] && 'code' === $change['field'] ) {
				$codes[ 'skill:' . $change['id'] ] = $change['value'];
			}
		}
		$seen = array();
		foreach ( $codes as $code ) {
			$key = SyllabusPlan::key( $code );
			if ( '' === $code ) {
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				return $code;
			}
			$seen[ $key ] = true;
		}
		return '';
	}
}
