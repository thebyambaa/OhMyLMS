<?php
/**
 * Template for displaying course content within loop.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/filters/course-search.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
?>

<div class="course-searchbox">
	<svg class="search-icon" width="16" height="16" fill="none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill="#A1A1AA" d="M14.473 13.528l-1.887-1.88a6.347 6.347 0 10-.94.94l1.88 1.887a.665.665 0 00.947 0 .666.666 0 000-.947zM2.666 7.668a5 5 0 1110 0 5 5 0 01-10 0z"/></svg>

	<input type="search" name="course-search" placeholder="Search">
</div>
