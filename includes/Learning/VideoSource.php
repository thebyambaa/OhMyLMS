<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Which provider plays a lesson video URL.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Parses a video URL into its provider and ids. The same rules run in the browser
 * (assets/interactivity/video/sources.js); tests/php/video-player-integration.php and
 * tests/js/video.test.mjs assert the same cases.
 */
final class VideoSource {

	/** Providers whose watching can be tracked; an `embed` is any other iframe. */
	const TRACKABLE = array( 'html5', 'youtube', 'vimeo', 'bunny', 'wistia' );

	/** Providers the custom controls drive; the others keep their own controls. */
	const CUSTOM = array( 'html5', 'youtube', 'vimeo' );

	const FILE = '/\.(mp4|webm|ogg|ogv|mov|m4v|avi|flv|mkv)(\?.*)?$/i';

	const MIME = array(
		'mp4'  => 'video/mp4',
		'm4v'  => 'video/mp4',
		'webm' => 'video/webm',
		'ogg'  => 'video/ogg',
		'ogv'  => 'video/ogg',
		'mov'  => 'video/quicktime',
		'avi'  => 'video/x-msvideo',
		'flv'  => 'video/x-flv',
		'mkv'  => 'video/x-matroska',
	);

	/**
	 * Provider of a URL.
	 *
	 * @param string $url    Video URL.
	 * @param bool   $custom Whether the site's custom player is on: then any other http(s) URL is treated as a
	 *                       video file (as the jQuery player did), else as an iframe embed.
	 * @return array{provider:string,id:string,hash:string,embed:string} Provider '' when the URL is not usable.
	 */
	public static function parse( $url, $custom = true ) {
		$url  = trim( (string) $url );
		$none = array(
			'provider' => '',
			'id'       => '',
			'hash'     => '',
			'embed'    => '',
		);
		if ( '' === $url ) {
			return $none;
		}
		$youtube = self::youtube_id( $url );
		if ( $youtube ) {
			return array(
				'provider' => 'youtube',
				'id'       => $youtube,
				'hash'     => '',
				'embed'    => 'https://www.youtube.com/embed/' . $youtube,
			);
		}
		$vimeo = self::vimeo_id( $url );
		if ( $vimeo['id'] ) {
			return array(
				'provider' => 'vimeo',
				'id'       => $vimeo['id'],
				'hash'     => $vimeo['hash'],
				'embed'    => 'https://player.vimeo.com/video/' . $vimeo['id'],
			);
		}
		$bunny = self::bunny_id( $url );
		if ( $bunny['id'] ) {
			return array(
				'provider' => 'bunny',
				'id'       => $bunny['id'],
				'hash'     => '',
				'embed'    => 'https://iframe.mediadelivery.net/embed/' . $bunny['library'] . '/' . $bunny['id'],
			);
		}
		$wistia = self::wistia_id( $url );
		if ( $wistia ) {
			return array(
				'provider' => 'wistia',
				'id'       => $wistia,
				'hash'     => '',
				'embed'    => 'https://fast.wistia.net/embed/iframe/' . $wistia,
			);
		}
		$parts    = self::parts( $url );
		$relative = 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' );
		if ( ! $parts && ! $relative ) {
			return $none;
		}
		$path = $parts ? (string) ( $parts['path'] ?? '' ) : $url;
		if ( preg_match( self::FILE, $path ) || $custom ) {
			return array(
				'provider' => 'html5',
				'id'       => '',
				'hash'     => '',
				'embed'    => $url,
			);
		}
		return array(
			'provider' => 'embed',
			'id'       => '',
			'hash'     => '',
			'embed'    => $url,
		);
	}

	/**
	 * Whether watching a URL can be tracked.
	 *
	 * @param string $url    Video URL.
	 * @param bool   $custom Custom player on.
	 * @return bool
	 */
	public static function trackable( $url, $custom = true ) {
		return in_array( self::parse( $url, $custom )['provider'], self::TRACKABLE, true );
	}

	/**
	 * MIME type for the `<source>` of a video file (MP4 when unknown).
	 *
	 * @param string $url File URL.
	 * @return string
	 */
	public static function mime( $url ) {
		$path      = strtolower( (string) strtok( (string) $url, '?#' ) );
		$extension = false !== strpos( $path, '.' ) ? substr( $path, strrpos( $path, '.' ) + 1 ) : '';
		return self::MIME[ $extension ] ?? 'video/mp4';
	}

	/**
	 * Parts of an absolute http(s) URL.
	 *
	 * @param string $url URL.
	 * @return array|null
	 */
	private static function parts( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) {
			return null;
		}
		$parts['host'] = strtolower( $parts['host'] );
		return $parts;
	}

	/**
	 * YouTube id of a watch, short, embed, shorts or live URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function youtube_id( $url ) {
		$parts = self::parts( $url );
		if ( ! $parts ) {
			return '';
		}
		$host = preg_replace( '/^(www\.|m\.)/', '', $parts['host'] );
		$path = (string) ( $parts['path'] ?? '' );
		$id   = '';
		if ( 'youtu.be' === $host ) {
			$id = explode( '/', ltrim( $path, '/' ) )[0];
		} elseif ( in_array( $host, array( 'youtube.com', 'youtube-nocookie.com', 'music.youtube.com' ), true ) ) {
			if ( preg_match( '#^/(?:embed|v|e|shorts|live)/([^/?\#]+)#', $path, $match ) ) {
				$id = $match[1];
			} else {
				parse_str( (string) ( $parts['query'] ?? '' ), $query );
				$id = isset( $query['v'] ) && is_string( $query['v'] ) ? $query['v'] : '';
			}
		}
		return preg_match( '/^[A-Za-z0-9_-]{11}$/D', $id ) ? $id : '';
	}

	/**
	 * Vimeo id and privacy hash.
	 *
	 * @param string $url URL.
	 * @return array{id:string,hash:string}
	 */
	public static function vimeo_id( $url ) {
		$parts = self::parts( $url );
		if ( ! $parts || ! preg_match( '/(^|\.)vimeo\.com$/', $parts['host'] ) || ! preg_match( '#/(\d{3,})(?:/([0-9a-f]{6,}))?/?$#i', (string) ( $parts['path'] ?? '' ), $match ) ) {
			return array(
				'id'   => '',
				'hash' => '',
			);
		}
		parse_str( (string) ( $parts['query'] ?? '' ), $query );
		$hash = ! empty( $match[2] ) ? $match[2] : ( isset( $query['h'] ) && is_string( $query['h'] ) ? $query['h'] : '' );
		return array(
			'id'   => $match[1],
			'hash' => preg_match( '/^[0-9a-f]+$/iD', $hash ) ? $hash : '',
		);
	}

	/**
	 * Bunny Stream library and video ids.
	 *
	 * @param string $url URL.
	 * @return array{library:string,id:string}
	 */
	public static function bunny_id( $url ) {
		$parts = self::parts( $url );
		if ( ! $parts || ! preg_match( '/(^|\.)(mediadelivery\.net|bunnycdn\.com)$/', $parts['host'] ) || ! preg_match( '#^/(?:embed|play)/(\d+)/([0-9a-f-]{36})/?$#i', (string) ( $parts['path'] ?? '' ), $match ) ) {
			return array(
				'library' => '',
				'id'      => '',
			);
		}
		return array(
			'library' => $match[1],
			'id'      => $match[2],
		);
	}

	/**
	 * Wistia media id.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function wistia_id( $url ) {
		$parts = self::parts( $url );
		if ( ! $parts || ! preg_match( '/(^|\.)(wistia\.com|wistia\.net|wi\.st)$/', $parts['host'] ) || ! preg_match( '#/(?:medias|embed/iframe|embed/medias|embed)/([a-z0-9]{10})(?:\.jsonp)?/?$#i', (string) ( $parts['path'] ?? '' ), $match ) ) {
			return '';
		}
		return $match[1];
	}
}
