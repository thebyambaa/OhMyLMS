<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Factory class to retrieve and validate Chapter objects.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Factory;

use OhMyLMS\Data\Chapter;

/**
 * Factory class to retrieve and validate Chapter objects.
 *
 * This class provides functionality to create or retrieve `Chapter` objects
 * based on different inputs, such as a chapter ID or an existing chapter instance.
 * It also checks if a chapter exists in the database.
 *
 * @package OhMyLMS\Factory
 * @since 1.0.0
 */
class ChapterFactory {

	/**
	 * Retrieves a Chapter object by its ID or other input.
	 *
	 * If a valid chapter ID is provided, this method returns a Chapter object.
	 * If the input is false or invalid, it returns false.
	 *
	 * @param bool|int|Chapter $chapter_id The chapter ID or an instance of Chapter, or false.
	 * @return bool|Chapter Returns a Chapter object if valid, otherwise false.
	 * @throws \Exception If chapter creation fails.
	 * @since 1.0.0
	 */
	public function get_chapter( $chapter_id = false ) {
		$chapter_id = $this->get_chapter_id( $chapter_id );
		if ( ! $chapter_id ) {
			return false;
		}
		return new Chapter( $chapter_id );
	}

	/**
	 * Determines and returns a valid chapter ID.
	 *
	 * This method checks various inputs to determine a valid chapter ID.
	 * It can validate if a post is of type `OHMYLMS_CHAPTER_CPT`.
	 *
	 * @param mixed $chapter Input which can be a chapter ID, an instance of Chapter, or other data.
	 * @return bool|int Returns the chapter ID if valid, otherwise false.
	 * @since 1.0.0
	 */
	private function get_chapter_id( $chapter ) {
		global $post;

		// Check if input is false and post is set.
		if ( false === $chapter && isset( $post, $post->ID ) && OHMYLMS_CHAPTER_CPT === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		}

		// If input is numeric, check if chapter exists.
		elseif ( is_numeric( $chapter ) ) {
			return $this->is_chapter_exist( $chapter ) ? $chapter : false;
		}

		// If input is an instance of Chapter.
		elseif ( $chapter instanceof Chapter ) {
			$id = $chapter->get_id();
			return $this->is_chapter_exist( $id ) ? $id : false;
		}

		// If input contains a valid ID property.
		elseif ( ! empty( $chapter->ID ) ) {
			return $this->is_chapter_exist( $chapter->ID ) ? $chapter->ID : false;
		}

		// Otherwise, return false.
		else {
			return false;
		}
	}

	/**
	 * Checks whether a chapter with the given ID exists.
	 *
	 * This method verifies that the chapter exists in the database and is of
	 * the correct post type (`OHMYLMS_CHAPTER_CPT`).
	 *
	 * @param int $chapter_id The ID of the chapter to check.
	 * @return bool Returns true if the chapter exists, otherwise false.
	 * @since 1.0.0
	 */
	public function is_chapter_exist( $chapter_id ) {
		if ( ! $chapter_id ) {
			return false;
		}

		$chapter = get_post( $chapter_id );

		// Check if the post exists and the post type matches.
		if ( $chapter && OHMYLMS_CHAPTER_CPT === get_post_type( $chapter_id ) ) {
			return true;
		} else {
			return false;
		}
	}
}
