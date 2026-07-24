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

	/** Retrieve published assessments assigned to a course. */
	public static function get_published_assessments( $course_id ) {
		return get_posts(
			array(
				'post_type'      => Parish_Formation_Assessment_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => Parish_Formation_Assessment_Settings::COURSE_META_KEY,
						'value'   => absint( $course_id ),
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/** Retrieve the combined published curriculum in drag-and-drop order. */
	public static function get_published_curriculum( $course_id ) {
		$items   = array();
		$lessons = self::get_published_lessons( $course_id );
		foreach ( $lessons as $index => $lesson ) {
			$order   = absint( get_post_meta( $lesson->ID, Parish_Formation_Course_Settings::CURRICULUM_ORDER_META_KEY, true ) );
			$items[] = array( 'type' => 'lesson', 'post' => $lesson, 'order' => $order ? $order : ( $index + 1 ) * 10 );
		}
		foreach ( self::get_published_assessments( $course_id ) as $index => $assessment ) {
			$order   = absint( get_post_meta( $assessment->ID, Parish_Formation_Course_Settings::CURRICULUM_ORDER_META_KEY, true ) );
			$items[] = array( 'type' => 'assessment', 'post' => $assessment, 'order' => $order ? $order : 100000 + $index );
		}
		usort(
			$items,
			static function ( $first, $second ) {
				if ( $first['order'] === $second['order'] ) {
					return strcasecmp( $first['post']->post_title, $second['post']->post_title );
				}
				return $first['order'] <=> $second['order'];
			}
		);
		return $items;
	}

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
