<?php
/**
 * Provides Parish Formation front-end shortcodes.
 *
 * @package ParishFormation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end shortcode registration and rendering.
 */
final class Parish_Formation_Shortcodes {

	/**
	 * Register participant shortcodes.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'parish_formation_my_courses', array( self::class, 'render_my_courses' ) );
	}

	/**
	 * Load UIkit only on pages containing the participant shortcode.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'parish_formation_my_courses' ) ) {
			return;
		}

		wp_enqueue_style(
			'parish-formation-uikit',
			PARISH_FORMATION_PLUGIN_URL . 'assets/vendor/uikit/uikit.min.css',
			array(),
			PARISH_FORMATION_UIKIT_VERSION
		);

		wp_enqueue_style(
			'parish-formation-frontend',
			PARISH_FORMATION_PLUGIN_URL . 'assets/css/parish-formation-frontend.css',
			array( 'parish-formation-uikit' ),
			PARISH_FORMATION_VERSION
		);
	}

	/**
	 * Render the logged-in participant's active courses.
	 *
	 * @return string
	 */
	public static function render_my_courses() {
		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( self::current_url() );

			return sprintf(
				'<div class="uk-alert uk-alert-primary"><p>%1$s <a class="uk-alert-link" href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Please log in to view your formation courses.', 'parish-formation' ),
				esc_url( $login_url ),
				esc_html__( 'Log in', 'parish-formation' )
			);
		}

		$course_id = isset( $_GET['pf_course'] ) ? absint( $_GET['pf_course'] ) : 0;

		if ( $course_id ) {
			return self::render_course( $course_id );
		}

		$enrollments = Parish_Formation_Enrollment_Repository::get_for_user( get_current_user_id() );

		if ( ! $enrollments ) {
			return '<div class="uk-alert uk-alert-primary"><p>' . esc_html__( 'You are not currently enrolled in any formation courses.', 'parish-formation' ) . '</p></div>';
		}

		ob_start();
		?>
		<div class="parish-formation-my-courses uk-container uk-container-small uk-section">
			<h2 class="uk-heading-small"><?php echo esc_html__( 'My Formation', 'parish-formation' ); ?></h2>
			<ul class="parish-formation-course-list uk-list">
				<?php foreach ( $enrollments as $enrollment ) : ?>
					<?php
					$course_lessons = Parish_Formation_Course_Repository::get_published_lessons( $enrollment->course_id );
					$progress       = Parish_Formation_Progress_Repository::get_summary( $enrollment->id, $course_lessons );
					self::reconcile_course_completion( $enrollment, $course_lessons, $progress );
					$current_lesson_id = Parish_Formation_Progress_Repository::get_current_lesson_id( $enrollment->id, $course_lessons );
					$current_lesson    = $current_lesson_id ? get_post( $current_lesson_id ) : null;
					?>
					<li class="parish-formation-course uk-card uk-card-default uk-card-body uk-margin">
						<h3 class="uk-card-title"><?php echo esc_html( $enrollment->course_title ); ?></h3>
						<p>
							<strong><?php echo esc_html__( 'Status:', 'parish-formation' ); ?></strong>
							<?php echo esc_html( self::get_display_status( $enrollment ) ); ?>
						</p>
						<p>
							<strong><?php echo esc_html__( 'Enrolled:', 'parish-formation' ); ?></strong>
							<?php echo esc_html( self::format_utc_date( $enrollment->enrolled_at ) ); ?>
						</p>
						<p>
							<strong><?php echo esc_html__( 'Progress:', 'parish-formation' ); ?></strong>
							<?php echo esc_html( $progress['percentage'] . '%' ); ?>
						</p>
						<progress class="uk-progress" value="<?php echo esc_attr( $progress['percentage'] ); ?>" max="100">
							<?php echo esc_html( $progress['percentage'] . '%' ); ?>
						</progress>
						<?php if ( $enrollment->completed_at ) : ?>
							<p>
								<strong><?php echo esc_html__( 'Completed:', 'parish-formation' ); ?></strong>
								<?php echo esc_html( self::format_utc_date( $enrollment->completed_at ) ); ?>
							</p>
						<?php endif; ?>
						<?php if ( $enrollment->expires_at ) : ?>
							<p>
								<strong><?php echo esc_html__( 'Access expires:', 'parish-formation' ); ?></strong>
								<?php echo esc_html( self::format_utc_date( $enrollment->expires_at ) ); ?>
							</p>
						<?php endif; ?>
						<p>
							<strong><?php echo esc_html__( 'Current lesson:', 'parish-formation' ); ?></strong>
							<?php
							if ( $current_lesson ) {
								echo esc_html( $current_lesson->post_title );
							} elseif ( $progress['is_complete'] ) {
								echo esc_html__( 'Course complete', 'parish-formation' );
							} else {
								echo esc_html__( 'No lessons available', 'parish-formation' );
							}
							?>
						</p>
						<p>
							<strong><?php echo esc_html__( 'Next action:', 'parish-formation' ); ?></strong>
							<?php
							if ( self::is_expired( $enrollment ) ) {
								echo esc_html__( 'Contact the parish about expired access.', 'parish-formation' );
							} elseif ( $current_lesson ) {
								echo Parish_Formation_Course_Repository::is_lesson_required( $current_lesson->ID )
									? esc_html__( 'Complete the current lesson.', 'parish-formation' )
									: esc_html__( 'Complete or skip the optional lesson.', 'parish-formation' );
							} elseif ( $progress['is_complete'] ) {
								echo esc_html__( 'Review the completed course.', 'parish-formation' );
							} else {
								echo esc_html__( 'Wait for the parish to publish course lessons.', 'parish-formation' );
							}
							?>
						</p>
						<?php if ( ! self::is_expired( $enrollment ) ) : ?>
							<p>
								<?php
								$course_link = $current_lesson
									? add_query_arg( array( 'pf_course' => $enrollment->course_id, 'pf_lesson' => $current_lesson->ID ), self::current_url() )
									: add_query_arg( 'pf_course', $enrollment->course_id, self::current_url() );
								?>
								<a class="uk-button uk-button-primary" href="<?php echo esc_url( $course_link ); ?>">
									<?php echo $current_lesson ? esc_html__( 'Continue course', 'parish-formation' ) : esc_html__( 'Review course', 'parish-formation' ); ?>
								</a>
							</p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render an enrolled participant's course introduction and lesson list.
	 *
	 * @param int $course_id Course post ID.
	 * @return string
	 */
	private static function render_course( $course_id ) {
		$enrollment = Parish_Formation_Enrollment_Repository::get_for_user_course(
			get_current_user_id(),
			$course_id
		);

		if ( ! $enrollment ) {
			return '<div class="uk-alert uk-alert-danger"><p>' . esc_html__( 'You do not have access to this course.', 'parish-formation' ) . '</p></div>';
		}

		if ( self::is_expired( $enrollment ) ) {
			return '<div class="uk-alert uk-alert-warning"><p>' . esc_html__( 'Your access to this course has expired.', 'parish-formation' ) . '</p></div>';
		}

		$lessons  = Parish_Formation_Course_Repository::get_published_lessons( $course_id );
		$sequence = self::get_sequence( $enrollment->id, $lessons );
		$progress = Parish_Formation_Progress_Repository::get_summary( $enrollment->id, $lessons );
		self::reconcile_course_completion( $enrollment, $lessons, $progress );
		$lesson_id    = isset( $_GET['pf_lesson'] ) ? absint( $_GET['pf_lesson'] ) : 0;
		$active_lesson = null;

		if ( $lesson_id ) {
			$active_lesson = Parish_Formation_Course_Repository::get_published_lesson( $course_id, $lesson_id );
			$lesson_index  = self::find_lesson_index( $lessons, $lesson_id );

			if ( ! $active_lesson ) {
				return '<div class="uk-alert uk-alert-danger"><p>' . esc_html__( 'This lesson is not available in your course.', 'parish-formation' ) . '</p></div>';
			}

			if ( null === $lesson_index || $lesson_index > $sequence['current_index'] ) {
				return '<div class="uk-alert uk-alert-warning"><p>' . esc_html__( 'Complete the preceding lessons before opening this lesson.', 'parish-formation' ) . '</p></div>';
			}
		}

		return self::render_learning_layout( $enrollment, $lessons, $sequence, $progress, $active_lesson );
	}

	/**
	 * Render the persistent course navigator and its active content panel.
	 *
	 * @param object       $enrollment   Enrollment and course data.
	 * @param WP_Post[]    $lessons     Ordered published lessons.
	 * @param array        $sequence    Calculated sequence state.
	 * @param array        $progress    Calculated progress summary.
	 * @param WP_Post|null $active_lesson Active lesson, or null for the introduction.
	 * @return string
	 */
	private static function render_learning_layout( $enrollment, $lessons, $sequence, $progress, $active_lesson ) {
		$course_url  = add_query_arg( 'pf_course', $enrollment->course_id, self::current_url() );
		$my_url      = remove_query_arg( array( 'pf_course', 'pf_lesson' ), self::current_url() );
		$active_id   = $active_lesson ? $active_lesson->ID : 0;
		$lesson_index = $active_lesson ? self::find_lesson_index( $lessons, $active_id ) : null;

		ob_start();
		?>
		<div class="pf-learning-layout">
			<aside class="pf-course-sidebar">
				<a class="pf-back-link" href="<?php echo esc_url( $my_url ); ?>">&larr; <?php echo esc_html__( 'My Formation', 'parish-formation' ); ?></a>
				<h2><?php echo esc_html( $enrollment->course_title ); ?></h2>
				<progress class="uk-progress" value="<?php echo esc_attr( $progress['percentage'] ); ?>" max="100"></progress>
				<p class="pf-progress-text"><?php echo esc_html( $progress['percentage'] . '% ' . __( 'complete', 'parish-formation' ) ); ?></p>

				<nav class="pf-lesson-navigation" aria-label="<?php echo esc_attr__( 'Course lessons', 'parish-formation' ); ?>">
					<ol>
						<?php foreach ( $lessons as $index => $lesson ) : ?>
							<?php
							$status   = $sequence['statuses'][ $lesson->ID ] ?? '';
							$is_done  = in_array( $status, array( 'completed', 'skipped' ), true );
							$is_locked = $index > $sequence['current_index'];
							$is_active = $active_id === $lesson->ID;
							$item_classes = implode( ' ', array_filter( array( $is_done ? 'is-complete' : '', $is_locked ? 'is-locked' : '', $is_active ? 'is-active' : '' ) ) );
							?>
							<li class="<?php echo esc_attr( $item_classes ); ?>">
								<span class="pf-lesson-marker" aria-hidden="true"><?php echo $is_done ? '&#10003;' : esc_html( $index + 1 ); ?></span>
								<div>
									<?php if ( ! $is_locked ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'pf_course' => $enrollment->course_id, 'pf_lesson' => $lesson->ID ), self::current_url() ) ); ?>"><?php echo esc_html( $lesson->post_title ); ?></a>
									<?php else : ?>
										<span><?php echo esc_html( $lesson->post_title ); ?></span>
									<?php endif; ?>
									<small>
										<?php echo Parish_Formation_Course_Repository::is_lesson_required( $lesson->ID ) ? esc_html__( 'Required', 'parish-formation' ) : esc_html__( 'Optional', 'parish-formation' ); ?>
										<?php if ( $is_locked ) : ?> &middot; <?php echo esc_html__( 'Locked', 'parish-formation' ); ?><?php endif; ?>
										<?php if ( 'skipped' === $status ) : ?> &middot; <?php echo esc_html__( 'Skipped', 'parish-formation' ); ?><?php endif; ?>
									</small>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>
				</nav>
			</aside>

			<main class="pf-course-content">
				<?php if ( $active_lesson ) : ?>
					<header class="pf-content-header"><h1><?php echo esc_html( $active_lesson->post_title ); ?></h1></header>
					<article class="pf-content-body uk-article">
						<?php echo apply_filters( 'the_content', $active_lesson->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
					<footer class="pf-content-footer">
						<?php if ( $lesson_index === $sequence['current_index'] ) : ?>
							<?php
							$next_lesson = $lessons[ $lesson_index + 1 ] ?? null;
							$return_url  = $next_lesson
								? add_query_arg( array( 'pf_course' => $enrollment->course_id, 'pf_lesson' => $next_lesson->ID ), self::current_url() )
								: $course_url;
							?>
							<form class="parish-formation-complete-lesson" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
								<input type="hidden" name="action" value="pf_complete_lesson" />
								<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $enrollment->id ); ?>" />
								<input type="hidden" name="course_id" value="<?php echo esc_attr( $enrollment->course_id ); ?>" />
								<input type="hidden" name="lesson_id" value="<?php echo esc_attr( $active_lesson->ID ); ?>" />
								<input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>" />
								<?php wp_nonce_field( 'pf_complete_lesson_' . $enrollment->id . '_' . $active_lesson->ID ); ?>
								<button class="uk-button uk-button-primary" type="submit" name="progress_action" value="completed"><?php echo esc_html__( 'Complete & Continue', 'parish-formation' ); ?> &rarr;</button>
								<?php if ( ! Parish_Formation_Course_Repository::is_lesson_required( $active_lesson->ID ) ) : ?>
									<button class="uk-button uk-button-default" type="submit" name="progress_action" value="skipped"><?php echo esc_html__( 'Skip Optional Lesson', 'parish-formation' ); ?></button>
								<?php endif; ?>
							</form>
						<?php else : ?>
							<span class="uk-label uk-label-success"><?php echo 'skipped' === ( $sequence['statuses'][ $active_id ] ?? '' ) ? esc_html__( 'Skipped', 'parish-formation' ) : esc_html__( 'Completed', 'parish-formation' ); ?></span>
						<?php endif; ?>
					</footer>
				<?php else : ?>
					<header class="pf-content-header"><h1><?php echo esc_html( $enrollment->course_title ); ?></h1></header>
					<article class="pf-content-body uk-article">
						<?php echo apply_filters( 'the_content', $enrollment->course_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php if ( $progress['is_complete'] ) : ?>
							<div class="uk-alert uk-alert-success">
								<h3><?php echo esc_html__( 'Course Complete', 'parish-formation' ); ?></h3>
								<?php echo wp_kses_post( wpautop( get_post_meta( $enrollment->course_id, '_pf_completion_message', true ) ) ); ?>
							</div>
						<?php endif; ?>
					</article>
					<?php if ( ! $progress['is_complete'] && isset( $lessons[ $sequence['current_index'] ] ) ) : ?>
						<footer class="pf-content-footer">
							<a class="uk-button uk-button-primary" href="<?php echo esc_url( add_query_arg( array( 'pf_course' => $enrollment->course_id, 'pf_lesson' => $lessons[ $sequence['current_index'] ]->ID ), self::current_url() ) ); ?>"><?php echo esc_html__( 'Continue Course', 'parish-formation' ); ?> &rarr;</a>
						</footer>
					<?php endif; ?>
				<?php endif; ?>
			</main>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Calculate the current unlocked position in a course sequence.
	 *
	 * @param int       $enrollment_id Enrollment ID.
	 * @param WP_Post[] $lessons       Ordered course lessons.
	 * @return array{current_index:int,statuses:array<int,string>}
	 */
	private static function get_sequence( $enrollment_id, $lessons ) {
		$statuses      = Parish_Formation_Progress_Repository::get_statuses( $enrollment_id );
		$current_index = count( $lessons );

		foreach ( $lessons as $index => $lesson ) {
			$status = $statuses[ $lesson->ID ] ?? '';

			if ( ! in_array( $status, array( 'completed', 'skipped' ), true ) ) {
				$current_index = $index;
				break;
			}
		}

		return array(
			'current_index' => $current_index,
			'statuses'      => $statuses,
		);
	}

	/**
	 * Find a lesson's zero-based position in an ordered lesson collection.
	 *
	 * @param WP_Post[] $lessons  Ordered course lessons.
	 * @param int       $lesson_id Lesson post ID.
	 * @return int|null
	 */
	private static function find_lesson_index( $lessons, $lesson_id ) {
		foreach ( $lessons as $index => $lesson ) {
			if ( absint( $lesson_id ) === $lesson->ID ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Determine the participant-facing enrollment status.
	 *
	 * @param object $enrollment Enrollment row.
	 * @return string
	 */
	private static function get_display_status( $enrollment ) {
		if ( self::is_expired( $enrollment ) ) {
			return __( 'Expired', 'parish-formation' );
		}

		return ucwords( str_replace( '_', ' ', $enrollment->status ) );
	}

	/**
	 * Determine whether an enrollment is expired.
	 *
	 * @param object $enrollment Enrollment row.
	 * @return bool
	 */
	private static function is_expired( $enrollment ) {
		return $enrollment->expires_at && strtotime( $enrollment->expires_at . ' UTC' ) < time();
	}

	/**
	 * Reconcile older terminal progress with its enrollment status.
	 *
	 * @param object    $enrollment Enrollment row to update in memory.
	 * @param WP_Post[] $lessons   Ordered published lessons.
	 * @param array     $progress  Calculated progress summary.
	 * @return void
	 */
	private static function reconcile_course_completion( $enrollment, $lessons, $progress ) {
		if ( ! $progress['is_complete'] || ( 'completed' === $enrollment->status && ! empty( $enrollment->completed_at ) ) ) {
			return;
		}

		$result = Parish_Formation_Progress_Repository::sync_course_completion( $enrollment, $lessons );

		if ( is_wp_error( $result ) ) {
			return;
		}

		$enrollment->status       = 'completed';
		$enrollment->completed_at = current_time( 'mysql', true );
	}

	/**
	 * Format a UTC datetime using the site's date and time settings.
	 *
	 * @param string $utc_date UTC MySQL datetime.
	 * @return string
	 */
	private static function format_utc_date( $utc_date ) {
		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return get_date_from_gmt( $utc_date, $date_format );
	}

	/**
	 * Get the current front-end URL for the post-login redirect.
	 *
	 * @return string
	 */
	private static function current_url() {
		global $wp;

		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
