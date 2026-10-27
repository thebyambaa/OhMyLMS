<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Registration is explicit; callbacks are supplied by trusted plugins or themes only.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Registration is explicit; callbacks are supplied by trusted plugins or themes only. */
final class Registry {
	/**
	 * Items.
	 *
	 * @var mixed
	 */
	private static $items = array(
		'layout'   => array(),
		'question' => array(),
		'lesson'   => array(),
		'activity' => array(),
	);

	/**
	 * Register for Registry.
	 *
	 * @param mixed $kind Kind.
	 * @param mixed $id Id.
	 * @param array $definition Definition.
	 * @throws \InvalidArgumentException When the operation cannot be completed.
	 */
	public static function register( $kind, $id, array $definition ) {
		if ( ! isset( self::$items[ $kind ] ) || ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9_-]*$/D', $id ) ) {
			throw new \InvalidArgumentException( 'Invalid extension identifier.' );
		}
		if ( isset( self::$items[ $kind ][ $id ] ) ) {
			throw new \InvalidArgumentException( 'Extension already registered: ' . $id );
		}
		if ( empty( $definition['label'] ) ) {
			throw new \InvalidArgumentException( 'An extension label is required.' );
		}
		foreach ( 'question' === $kind ? array( 'render', 'validate', 'grade' ) : array( 'render' ) as $callback ) {
			if ( ! is_callable( $definition[ $callback ] ?? null ) ) {
				throw new \InvalidArgumentException( 'Missing callback: ' . $callback );
			}
		}
		if ( 'layout' === $kind && ( ! isset( $definition['contexts'] ) || array_diff( $definition['contexts'], array( 'course', 'chapter', 'lesson', 'quiz' ) ) ) ) {
			throw new \InvalidArgumentException( 'Invalid layout contexts.' );
		}
		self::$items[ $kind ][ $id ] = $definition;
	}
	/**
	 * Get for Registry.
	 *
	 * @param mixed $kind Kind.
	 * @param mixed $id Id.
	 */
	public static function get( $kind, $id ) {
		return self::$items[ $kind ][ $id ] ?? null; }
	/**
	 * All for Registry.
	 *
	 * @param mixed $kind Kind.
	 */
	public static function all( $kind ) {
		return self::$items[ $kind ] ?? array(); }
	/**
	 * Manifest for Registry.
	 */
	public static function manifest() {
		$result = array();
		foreach ( self::$items as $kind => $items ) {
			foreach ( $items as $id => $definition ) {
				$result[ $kind ][ $id ] = array_intersect_key( $definition, array_flip( array( 'label', 'contexts', 'editor' ) ) );
			}
		}
		return $result;
	}
}
