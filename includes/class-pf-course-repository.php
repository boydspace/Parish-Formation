<?php
/**
 * Provides front-end course data access.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Course and lesson retrieval operations.
 */
final class Parish_Formation_Course_Repository {

	/**
	 * Retrieve one published lesson assigned to a course.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $lesson_id Lesson post ID.
	 * @return WP_Post|null
	 */
	public static function get_published_lesson( $course_id, $lesson_id ) {
		$lesson = get_post( absint( $lesson_id ) );

		if ( ! $lesson || Parish_Formation_Lesson_Post_Type::POST_TYPE !== $lesson->post_type || 'publish' !== $lesson->post_status ) {
			return null;
		}

		$assigned_course_id = absint( get_post_meta( $lesson->ID, '_pf_course_id', true ) );

		if ( absint( $course_id ) !== $assigned_course_id ) {
			return null;
		}

		return $lesson;
	}

	/**
	 * Retrieve published lessons for a course in lesson-number order.
	 *
	 * @param int $course_id Course post ID.
	 * @return WP_Post[]
	 */
	public static function get_published_lessons( $course_id ) {
		$lessons = get_posts(
			array(
				'post_type'      => Parish_Formation_Lesson_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => '_pf_course_id',
						'value'   => absint( $course_id ),
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		usort(
			$lessons,
			static function ( $first_lesson, $second_lesson ) {
				$first_order  = self::get_lesson_number( $first_lesson->ID );
				$second_order = self::get_lesson_number( $second_lesson->ID );

				if ( $first_order === $second_order ) {
					return strcasecmp( $first_lesson->post_title, $second_lesson->post_title );
				}

				return $first_order <=> $second_order;
			}
		);

		return $lessons;
	}

	/**
	 * Get a lesson's number.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int
	 */
	public static function get_lesson_number( $lesson_id ) {
		return absint( get_post_meta( $lesson_id, '_pf_lesson_order', true ) );
	}

	/**
	 * Determine whether a lesson is required.
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return bool
	 */
	public static function is_lesson_required( $lesson_id ) {
		if ( ! metadata_exists( 'post', $lesson_id, '_pf_is_required' ) ) {
			return true;
		}

		return (bool) get_post_meta( $lesson_id, '_pf_is_required', true );
	}
}
