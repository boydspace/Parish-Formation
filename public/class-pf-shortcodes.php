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

	/** Shortcode page URL supplied during REST fragment rendering. */
	private static $rest_base_url = '';

	/**
	 * Register participant shortcodes.
	 *
	 * @return void
	 */
	public static function register() {
		add_rewrite_endpoint( 'course', EP_PAGES );
		add_shortcode( 'parish_formation_my_courses', array( self::class, 'render_my_courses' ) );
		add_shortcode( 'parish_formation_courses', array( self::class, 'render_course_catalog' ) );
		if ( '1' !== get_option( 'parish_formation_pretty_routes_060', '0' ) ) {
			flush_rewrite_rules( false );
			update_option( 'parish_formation_pretty_routes_060', '1', false );
		}
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

		$has_course_shortcode       = $post instanceof WP_Post && has_shortcode( $post->post_content, 'parish_formation_my_courses' );
		$has_catalog_shortcode      = $post instanceof WP_Post && has_shortcode( $post->post_content, 'parish_formation_courses' );
		$has_verification_shortcode = $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'formation-certificate' ) || has_shortcode( $post->post_content, 'parish_formation_certificate_verification' ) );

		if ( ! $has_course_shortcode && ! $has_catalog_shortcode && ! $has_verification_shortcode ) {
			return;
		}

		wp_enqueue_style(
			'parish-formation-uikit',
			PARISH_FORMATION_PLUGIN_URL . 'assets/vendor/uikit/uikit.min.css',
			array(),
			PARISH_FORMATION_UIKIT_VERSION
		);

		if ( $has_course_shortcode ) {
		wp_enqueue_script(
			'parish-formation-assessment-submission',
			PARISH_FORMATION_PLUGIN_URL . 'assets/js/assessment-submission.js',
			array(),
			(string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/js/assessment-submission.js' ),
			true
		);
		wp_localize_script(
			'parish-formation-assessment-submission',
			'pfAssessmentSubmission',
			array(
				'endpoint' => rest_url( 'parish-formation/v1/assessment-attempts' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'submitting' => __( 'Submitting…', 'parish-formation' ),
				'error'      => __( 'The assessment could not be submitted.', 'parish-formation' ),
			)
		);
		}

		if ( $has_catalog_shortcode ) {
			wp_enqueue_script(
				'parish-formation-enrollment',
				PARISH_FORMATION_PLUGIN_URL . 'assets/js/enrollment.js',
				array(),
				(string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/js/enrollment.js' ),
				true
			);
			wp_localize_script(
				'parish-formation-enrollment',
				'pfEnrollment',
				array(
					'endpoint'      => rest_url( 'parish-formation/v1/access-code-enrollment' ),
					'invitationEndpoint' => rest_url( 'parish-formation/v1/invitation-enrollment' ),
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'submitting'    => __( 'Checking…', 'parish-formation' ),
					'defaultError'  => __( 'The course enrollment could not be completed.', 'parish-formation' ),
					'openFormation' => __( 'Open My Formation', 'parish-formation' ),
				)
			);
		}

		wp_enqueue_style(
			'parish-formation-frontend',
			PARISH_FORMATION_PLUGIN_URL . 'assets/css/parish-formation-frontend.css',
			array( 'parish-formation-uikit' ),
			(string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/css/parish-formation-frontend.css' )
		);

		if ( ! $has_course_shortcode ) {
			return;
		}

		wp_enqueue_script(
			'parish-formation-course-navigation',
			PARISH_FORMATION_PLUGIN_URL . 'assets/js/course-navigation.js',
			array(),
			(string) filemtime( PARISH_FORMATION_PLUGIN_DIR . 'assets/js/course-navigation.js' ),
			true
		);
		wp_localize_script(
			'parish-formation-course-navigation',
			'pfCourseNavigation',
			array(
				'endpoint' => rest_url( 'parish-formation/v1/course-view' ),
				'lessonEndpoint' => rest_url( 'parish-formation/v1/lesson-progress' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'error'    => __( 'This course section could not be loaded.', 'parish-formation' ),
			)
		);
	}

	/** Render published courses that permit open self-enrollment. */
	public static function render_course_catalog() {
		$courses = get_posts(
			array(
				'post_type'      => Parish_Formation_Course_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => Parish_Formation_Course_Settings::OPEN_ENROLLMENT_META_KEY,
						'value' => '1',
					),
					array(
						'relation' => 'AND',
						array(
							'key'   => Parish_Formation_Course_Settings::ACCESS_CODE_ENABLED_META_KEY,
							'value' => '1',
						),
						array(
							'key'     => Parish_Formation_Course_Settings::ACCESS_CODE_HASH_META_KEY,
							'compare' => 'EXISTS',
						),
					),
				),
			)
		);
		$courses = array_values(
			array_filter(
				$courses,
				static function ( $course ) {
					return ! metadata_exists( 'post', $course->ID, Parish_Formation_Course_Settings::CATALOG_VISIBLE_META_KEY ) || (bool) get_post_meta( $course->ID, Parish_Formation_Course_Settings::CATALOG_VISIBLE_META_KEY, true );
				}
			)
		);
		$current_url = remove_query_arg( 'pf_enrollment', self::current_url() );
		$formation_url = self::get_my_formation_url();
		$notice      = isset( $_GET['pf_enrollment'] ) ? sanitize_key( wp_unslash( $_GET['pf_enrollment'] ) ) : '';
		$invitation_token = isset( $_GET['pf_invitation'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_invitation'] ) ) : '';
		if ( $invitation_token ) {
			return self::render_invitation( $invitation_token, $current_url );
		}

		ob_start();
		?>
		<div class="parish-formation-course-catalog uk-container uk-container-large uk-section">
			<?php if ( 'success' === $notice ) : ?>
				<div class="uk-alert uk-alert-success"><p><?php esc_html_e( 'You are now enrolled.', 'parish-formation' ); ?> <a class="uk-alert-link" href="<?php echo esc_url( $formation_url ); ?>"><?php esc_html_e( 'Open My Formation', 'parish-formation' ); ?></a></p></div>
			<?php elseif ( 'already-enrolled' === $notice ) : ?>
				<div class="uk-alert uk-alert-primary"><p><?php esc_html_e( 'You are already enrolled in that course.', 'parish-formation' ); ?></p></div>
			<?php elseif ( 'error' === $notice ) : ?>
				<div class="uk-alert uk-alert-danger"><p><?php esc_html_e( 'The course enrollment could not be completed.', 'parish-formation' ); ?></p></div>
			<?php elseif ( 'invalid-code' === $notice ) : ?>
				<div class="uk-alert uk-alert-danger"><p><?php esc_html_e( 'That access code is not valid.', 'parish-formation' ); ?></p></div>
			<?php elseif ( 'code-expired' === $notice ) : ?>
				<div class="uk-alert uk-alert-warning"><p><?php esc_html_e( 'That access code has expired.', 'parish-formation' ); ?></p></div>
			<?php elseif ( 'code-exhausted' === $notice ) : ?>
				<div class="uk-alert uk-alert-warning"><p><?php esc_html_e( 'That access code has reached its usage limit.', 'parish-formation' ); ?></p></div>
			<?php elseif ( 'code-unavailable' === $notice ) : ?>
				<div class="uk-alert uk-alert-warning"><p><?php esc_html_e( 'Access-code enrollment is no longer available for that course.', 'parish-formation' ); ?></p></div>
			<?php elseif ( 'ambiguous-code' === $notice ) : ?>
				<div class="uk-alert uk-alert-warning"><p><?php esc_html_e( 'That code matches more than one course. Please contact the parish for assistance.', 'parish-formation' ); ?></p></div>
			<?php endif; ?>
			<div class="pf-catalog-code-entry uk-card uk-card-default uk-card-body">
				<h3 class="uk-card-title"><?php esc_html_e( 'Have a course access code?', 'parish-formation' ); ?></h3>
				<?php if ( is_user_logged_in() ) : ?>
					<p><?php esc_html_e( 'Enter it here to enroll, including in courses that are not shown publicly.', 'parish-formation' ); ?></p>
					<form class="pf-course-access-code-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="pf_access_code_enroll"><input type="hidden" name="course_id" value="0"><input type="hidden" name="return_url" value="<?php echo esc_attr( $current_url ); ?>">
						<?php wp_nonce_field( 'pf_access_code_enroll_0', 'pf_access_code_nonce' ); ?>
						<label for="pf-catalog-access-code"><?php esc_html_e( 'Course access code', 'parish-formation' ); ?></label>
						<div><input id="pf-catalog-access-code" class="uk-input" name="access_code" type="text" required autocomplete="off"><button type="submit" class="uk-button uk-button-primary" data-label="<?php esc_attr_e( 'Use Code', 'parish-formation' ); ?>"><?php esc_html_e( 'Use Code', 'parish-formation' ); ?></button></div>
						<p class="pf-course-access-code-message" aria-live="polite"></p>
					</form>
				<?php else : ?>
					<p><?php esc_html_e( 'Log in or create an account before using your course access code.', 'parish-formation' ); ?></p>
					<div class="pf-course-catalog-actions"><a class="uk-button uk-button-primary" href="<?php echo esc_url( wp_login_url( $current_url ) ); ?>"><?php esc_html_e( 'Log In', 'parish-formation' ); ?></a><?php if ( get_option( 'users_can_register' ) ) : ?><a class="uk-button uk-button-default" href="<?php echo esc_url( add_query_arg( 'redirect_to', $current_url, wp_registration_url() ) ); ?>"><?php esc_html_e( 'Create Account', 'parish-formation' ); ?></a><?php endif; ?></div>
				<?php endif; ?>
			</div>

			<?php if ( ! $courses ) : ?>
				<div class="uk-alert uk-alert-primary"><p><?php esc_html_e( 'No courses are currently open for self-enrollment.', 'parish-formation' ); ?></p></div>
			<?php else : ?>
				<div class="pf-course-catalog-grid">
					<?php foreach ( $courses as $course ) :
						$enrollment = is_user_logged_in() ? Parish_Formation_Enrollment_Repository::get_for_user_course( get_current_user_id(), $course->ID ) : null;
						$excerpt    = has_excerpt( $course ) ? $course->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $course->post_content ) ), 32 );
						$open_enrollment = (bool) get_post_meta( $course->ID, Parish_Formation_Course_Settings::OPEN_ENROLLMENT_META_KEY, true );
						?>
						<article class="pf-course-catalog-card uk-card uk-card-default uk-card-body">
							<?php if ( has_post_thumbnail( $course ) ) : ?><div class="pf-course-catalog-image"><?php echo get_the_post_thumbnail( $course, 'large', array( 'class' => 'uk-width-1-1' ) ); ?></div><?php endif; ?>
							<h3 class="uk-card-title"><?php echo esc_html( $course->post_title ); ?></h3>
							<?php if ( $excerpt ) : ?><p><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
							<?php if ( $enrollment ) : ?>
								<div class="pf-course-catalog-enrollment"><span class="uk-label uk-label-success"><?php echo esc_html( 'completed' === $enrollment->status ? __( 'Completed', 'parish-formation' ) : __( 'Enrolled', 'parish-formation' ) ); ?></span><a class="uk-button uk-button-primary" href="<?php echo esc_url( trailingslashit( $formation_url ) . 'course/' . rawurlencode( $course->post_name ) . '/' ); ?>"><?php esc_html_e( 'Open My Formation', 'parish-formation' ); ?></a></div>
							<?php elseif ( is_user_logged_in() && $open_enrollment ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="pf_self_enroll"><input type="hidden" name="course_id" value="<?php echo esc_attr( $course->ID ); ?>"><input type="hidden" name="return_url" value="<?php echo esc_attr( $current_url ); ?>">
									<?php wp_nonce_field( 'pf_self_enroll_' . $course->ID, 'pf_self_enroll_nonce' ); ?>
									<button type="submit" class="uk-button uk-button-primary"><?php esc_html_e( 'Enroll in Course', 'parish-formation' ); ?></button>
								</form>
							<?php elseif ( is_user_logged_in() ) : ?>
								<form class="pf-course-access-code-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-course-url="<?php echo esc_url( trailingslashit( $formation_url ) . 'course/' . rawurlencode( $course->post_name ) . '/' ); ?>">
									<input type="hidden" name="action" value="pf_access_code_enroll"><input type="hidden" name="course_id" value="<?php echo esc_attr( $course->ID ); ?>"><input type="hidden" name="return_url" value="<?php echo esc_attr( $current_url ); ?>">
									<?php wp_nonce_field( 'pf_access_code_enroll_' . $course->ID, 'pf_access_code_nonce' ); ?>
									<label for="pf-access-code-<?php echo esc_attr( $course->ID ); ?>"><?php esc_html_e( 'Course access code', 'parish-formation' ); ?></label>
									<div><input id="pf-access-code-<?php echo esc_attr( $course->ID ); ?>" class="uk-input" name="access_code" type="text" required autocomplete="off"><button type="submit" class="uk-button uk-button-primary" data-label="<?php esc_attr_e( 'Use Code', 'parish-formation' ); ?>"><?php esc_html_e( 'Use Code', 'parish-formation' ); ?></button></div>
									<p class="pf-course-access-code-message" aria-live="polite"></p>
								</form>
							<?php else : ?>
								<div class="pf-course-catalog-actions"><a class="uk-button uk-button-primary" href="<?php echo esc_url( wp_login_url( $current_url ) ); ?>"><?php esc_html_e( 'Log In to Enroll', 'parish-formation' ); ?></a><?php if ( get_option( 'users_can_register' ) ) : ?><a class="uk-button uk-button-default" href="<?php echo esc_url( add_query_arg( 'redirect_to', $current_url, wp_registration_url() ) ); ?>"><?php esc_html_e( 'Create Account', 'parish-formation' ); ?></a><?php endif; ?></div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Render secure invitation acceptance, login, or registration. */
	private static function render_invitation( $token, $current_url ) {
		$invitation = Parish_Formation_Invitation_Repository::get_by_token( $token );
		$notice     = isset( $_GET['pf_invitation_notice'] ) ? sanitize_key( wp_unslash( $_GET['pf_invitation_notice'] ) ) : '';
		$messages   = array(
			'invitation-unavailable' => __( 'This invitation is no longer available.', 'parish-formation' ),
			'invitation-expired' => __( 'This invitation has expired.', 'parish-formation' ),
			'invitation-used' => __( 'This invitation has already reached its usage limit.', 'parish-formation' ),
			'invitation-email-mismatch' => __( 'Sign in or register using the email address that received this invitation.', 'parish-formation' ),
			'registration-invalid' => __( 'Enter a valid email, username, name, and a password of at least eight characters.', 'parish-formation' ),
			'account-exists' => __( 'An account already uses that email or username. Log in to accept the invitation.', 'parish-formation' ),
		);
		if ( ! $invitation ) {
			return '<div class="uk-alert uk-alert-danger"><p>' . esc_html( $messages['invitation-unavailable'] ) . '</p></div>';
		}
		if ( 'active' !== $invitation->status || ( $invitation->expires_at && strtotime( $invitation->expires_at . ' UTC' ) < time() ) || ( $invitation->max_uses && $invitation->use_count >= $invitation->max_uses ) ) {
			$key = 'active' !== $invitation->status ? 'invitation-unavailable' : ( $invitation->expires_at && strtotime( $invitation->expires_at . ' UTC' ) < time() ? 'invitation-expired' : 'invitation-used' );
			return '<div class="uk-alert uk-alert-warning"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
		$invitation_url = add_query_arg( 'pf_invitation', rawurlencode( $token ), $current_url );
		$known_account  = $invitation->restricted_email ? (bool) get_user_by( 'email', $invitation->restricted_email ) : false;
		$show_login     = ! $invitation->restricted_email || $known_account;
		$show_register  = ! $invitation->restricted_email || ! $known_account;
		ob_start();
		?>
		<div class="pf-invitation-page uk-container uk-container-small uk-section"><div class="uk-card uk-card-default uk-card-body">
			<h2 class="uk-card-title"><?php esc_html_e( 'Course Invitation', 'parish-formation' ); ?></h2>
			<h3><?php echo esc_html( $invitation->course_title ); ?></h3>
			<?php if ( isset( $messages[ $notice ] ) ) : ?><div class="uk-alert uk-alert-danger"><p><?php echo esc_html( $messages[ $notice ] ); ?></p></div><?php endif; ?>
			<?php if ( is_user_logged_in() ) : $valid = Parish_Formation_Invitation_Repository::validate_for_user( $invitation, wp_get_current_user() ); ?>
				<?php if ( is_wp_error( $valid ) ) : ?><div class="uk-alert uk-alert-danger"><p><?php echo esc_html( $valid->get_error_message() ); ?></p></div><?php else : ?>
				<form class="pf-invitation-accept-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pf_accept_invitation"><input type="hidden" name="invitation_token" value="<?php echo esc_attr( $token ); ?>"><?php wp_nonce_field( 'pf_accept_invitation', 'pf_invitation_nonce' ); ?>
					<button class="uk-button uk-button-primary" type="submit" data-label="<?php esc_attr_e( 'Accept Invitation', 'parish-formation' ); ?>"><?php esc_html_e( 'Accept Invitation', 'parish-formation' ); ?></button><p class="pf-course-access-code-message" aria-live="polite"></p>
				</form><?php endif; ?>
			<?php else : ?>
				<?php if ( $show_login ) : ?>
					<?php if ( $known_account ) : ?><p><?php esc_html_e( 'An account already exists for the invited email address. Log in to accept this invitation.', 'parish-formation' ); ?></p><?php endif; ?>
					<p><a class="uk-button uk-button-primary" href="<?php echo esc_url( wp_login_url( $invitation_url ) ); ?>"><?php esc_html_e( 'Log In to Accept', 'parish-formation' ); ?></a></p>
				<?php endif; ?>
				<?php if ( $show_register ) : ?>
				<?php if ( $show_login ) : ?><hr><?php endif; ?><h3><?php esc_html_e( 'Create an Account', 'parish-formation' ); ?></h3>
				<?php if ( $invitation->restricted_email ) : ?><p><?php esc_html_e( 'No account exists for the invited email address. Create one to accept the invitation.', 'parish-formation' ); ?></p><?php endif; ?>
				<form class="pf-invitation-registration" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pf_register_invitation"><input type="hidden" name="invitation_token" value="<?php echo esc_attr( $token ); ?>"><?php wp_nonce_field( 'pf_register_invitation', 'pf_invitation_registration_nonce' ); ?>
					<label><?php esc_html_e( 'Full name', 'parish-formation' ); ?><input class="uk-input" name="display_name" type="text" required></label>
					<label><?php esc_html_e( 'Email address', 'parish-formation' ); ?><input class="uk-input" name="user_email" type="email" required value="<?php echo esc_attr( $invitation->restricted_email ); ?>" <?php echo $invitation->restricted_email ? 'readonly' : ''; ?>></label>
					<label><?php esc_html_e( 'Username', 'parish-formation' ); ?><input class="uk-input" name="user_login" type="text" required autocomplete="username"></label>
					<label><?php esc_html_e( 'Password', 'parish-formation' ); ?><input class="uk-input" name="user_password" type="password" minlength="8" required autocomplete="new-password"></label>
					<button class="uk-button uk-button-primary" type="submit"><?php esc_html_e( 'Create Account and Enroll', 'parish-formation' ); ?></button>
				</form>
				<?php endif; ?>
			<?php endif; ?>
		</div></div>
		<?php return ob_get_clean();
	}

	/** Find the published page containing the participant dashboard shortcode. */
	public static function get_my_formation_url() {
		$page_ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $page_ids as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'parish_formation_my_courses' ) ) {
				return get_permalink( $page_id );
			}
		}

		return home_url( '/my-formation/' );
	}

	/** Find the published page containing the public course catalog. */
	public static function get_course_catalog_url() {
		$page_ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $page_ids as $page_id ) {
			if ( has_shortcode( get_post_field( 'post_content', $page_id ), 'parish_formation_courses' ) ) {
				return get_permalink( $page_id );
			}
		}
		return home_url( '/available-courses/' );
	}

	/** Register the authenticated course-view endpoint used by AJAX navigation. */
	public static function register_rest_route() {
		register_rest_route(
			'parish-formation/v1',
			'/course-view',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'render_course_rest' ),
				'permission_callback' => static function () { return is_user_logged_in(); },
			)
		);
	}

	/** Return one access-controlled course interface fragment. */
	public static function render_course_rest( WP_REST_Request $request ) {
		$course_slug = sanitize_title( $request->get_param( 'course_slug' ) );
		$item_type   = sanitize_key( $request->get_param( 'item_type' ) );
		$item_slug   = sanitize_title( $request->get_param( 'item_slug' ) );
		self::$rest_base_url = esc_url_raw( $request->get_param( 'base_url' ) );
		$course      = get_page_by_path( $course_slug, OBJECT, Parish_Formation_Course_Post_Type::POST_TYPE );
		if ( ! $course || 'publish' !== $course->post_status ) {
			return new WP_Error( 'invalid_course', __( 'This course could not be found.', 'parish-formation' ), array( 'status' => 404 ) );
		}
		if ( $item_type && ! in_array( $item_type, array( 'lesson', 'assessment' ), true ) ) {
			return new WP_Error( 'invalid_section', __( 'This course section could not be found.', 'parish-formation' ), array( 'status' => 404 ) );
		}
		$html = self::render_course( $course->ID, $item_type, $item_slug );
		return rest_ensure_response( array( 'html' => $html, 'title' => $course->post_title ) );
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
		$certificate_uuid = isset( $_GET['pf_certificate'] ) ? sanitize_text_field( wp_unslash( $_GET['pf_certificate'] ) ) : '';
		if ( $certificate_uuid ) {
			return self::render_certificate( $certificate_uuid );
		}

		$route = self::get_pretty_route();
		if ( is_wp_error( $route ) ) {
			return '<div class="uk-alert uk-alert-danger"><p>' . esc_html( $route->get_error_message() ) . '</p></div>';
		}
		$course_id = $route ? $route['course_id'] : ( isset( $_GET['pf_course'] ) ? absint( $_GET['pf_course'] ) : 0 );

		if ( $course_id ) {
			return self::render_course( $course_id, $route ? $route['item_type'] : '', $route ? $route['item_slug'] : '' );
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
					$curriculum     = Parish_Formation_Course_Repository::get_published_curriculum( $enrollment->course_id );
					$progress       = Parish_Formation_Progress_Repository::get_summary( $enrollment->id, $course_lessons, $enrollment->course_id );
					$certificate    = Parish_Formation_Certificate_Repository::get_for_enrollment_run( $enrollment->id, $enrollment->current_run );
					self::reconcile_course_completion( $enrollment, $course_lessons, $progress );
					$current_item = self::get_current_curriculum_item( $curriculum, $enrollment->id );
					$current_lesson = $current_item && 'lesson' === $current_item['type'] ? $current_item['post'] : null;
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
							<strong><?php echo esc_html__( 'Current item:', 'parish-formation' ); ?></strong>
							<?php
							if ( $current_item ) {
								echo esc_html( $current_item['post']->post_title );
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
							} elseif ( $current_item && 'assessment' === $current_item['type'] ) {
								echo esc_html__( 'Complete the current assessment.', 'parish-formation' );
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
								$course_link = $current_item
									? self::get_curriculum_item_url( $enrollment->course_id, $current_item, self::current_url() )
									: self::get_course_url( $enrollment->course_id );
								?>
								<a class="uk-button uk-button-primary" href="<?php echo esc_url( $course_link ); ?>">
									<?php echo $current_item ? esc_html__( 'Continue course', 'parish-formation' ) : esc_html__( 'Review course', 'parish-formation' ); ?>
								</a>
							</p>
						<?php endif; ?>
						<?php if ( $certificate && 'issued' === $certificate->status ) : ?>
							<p><a class="uk-button uk-button-default" href="<?php echo esc_url( add_query_arg( 'pf_certificate', $certificate->certificate_uuid, self::current_url() ) ); ?>"><?php esc_html_e( 'View Certificate', 'parish-formation' ); ?></a></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/** Render an access-controlled printable certificate. */
	private static function render_certificate( $certificate_uuid ) {
		$certificate = Parish_Formation_Certificate_Repository::get_by_uuid( $certificate_uuid );
		if ( ! $certificate || absint( $certificate->user_id ) !== get_current_user_id() ) {
			return '<div class="uk-alert uk-alert-danger"><p>' . esc_html__( 'This certificate could not be found or you do not have access to it.', 'parish-formation' ) . '</p></div>';
		}
		$back_url = remove_query_arg( 'pf_certificate', self::current_url() );
		$pdf_url  = wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'pf_download_certificate',
					'certificate' => $certificate->certificate_uuid,
				),
				admin_url( 'admin-post.php' )
			),
			'pf_download_certificate_' . $certificate->certificate_uuid
		);
		$expired  = $certificate->expires_at && strtotime( $certificate->expires_at . ' UTC' ) < current_time( 'timestamp', true );
		$verification_url = Parish_Formation_Certificate_Verification::get_verification_url( $certificate->verification_code );
		ob_start();
		?>
		<div class="pf-certificate-page uk-container uk-container-small uk-section">
			<div class="pf-certificate-actions uk-margin-bottom">
				<a class="uk-button uk-button-default" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'My Formation', 'parish-formation' ); ?></a>
				<button class="uk-button uk-button-primary" type="button" onclick="window.print();"><?php esc_html_e( 'Print Certificate', 'parish-formation' ); ?></button>
				<a class="uk-button uk-button-secondary" href="<?php echo esc_url( $pdf_url ); ?>"><?php esc_html_e( 'Download PDF', 'parish-formation' ); ?></a>
			</div>
			<?php if ( 'issued' !== $certificate->status ) : ?><div class="uk-alert uk-alert-danger"><p><?php esc_html_e( 'This certificate is no longer valid.', 'parish-formation' ); ?></p></div><?php endif; ?>
			<?php if ( $expired ) : ?><div class="uk-alert uk-alert-warning"><p><?php esc_html_e( 'This certificate has expired.', 'parish-formation' ); ?></p></div><?php endif; ?>
			<article class="pf-certificate" aria-label="<?php esc_attr_e( 'Completion certificate', 'parish-formation' ); ?>">
				<p class="pf-certificate-issuer"><?php echo esc_html( $certificate->issuer_name ); ?></p>
				<h1><?php echo esc_html( $certificate->certificate_title ); ?></h1>
				<p><?php esc_html_e( 'This certifies that', 'parish-formation' ); ?></p>
				<h2><?php echo esc_html( $certificate->participant_name ); ?></h2>
				<p><?php esc_html_e( 'has successfully completed', 'parish-formation' ); ?></p>
				<h3><?php echo esc_html( $certificate->course_title ); ?></h3>
				<p><?php echo esc_html( sprintf( __( 'Completed %s', 'parish-formation' ), self::format_utc_date( $certificate->completed_at ) ) ); ?></p>
				<?php if ( $certificate->signatory_name ) : ?>
					<div class="pf-certificate-signature"><span><?php echo esc_html( $certificate->signatory_name ); ?></span><?php if ( $certificate->signatory_title ) : ?><small><?php echo esc_html( $certificate->signatory_title ); ?></small><?php endif; ?></div>
				<?php endif; ?>
				<footer>
					<span><?php echo esc_html( sprintf( __( 'Verification code: %s', 'parish-formation' ), $certificate->verification_code ) ); ?></span>
					<span><a href="<?php echo esc_url( $verification_url ); ?>"><?php esc_html_e( 'Verify certificate', 'parish-formation' ); ?></a></span>
					<?php if ( $certificate->expires_at ) : ?><span><?php echo esc_html( sprintf( __( 'Valid through: %s', 'parish-formation' ), self::format_utc_date( $certificate->expires_at ) ) ); ?></span><?php endif; ?>
				</footer>
			</article>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Render participant-safe question controls without answer-key attributes. */
	private static function render_assessment_questions( $assessment, $enrollment, $curriculum ) {
		$questions = Parish_Formation_Assessment_Repository::get_questions( $assessment->ID );
		if ( ! $questions ) {
			return '<div class="uk-alert uk-alert-primary"><p>' . esc_html__( 'No questions have been added to this assessment yet.', 'parish-formation' ) . '</p></div>';
		}
		$latest = Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment->id, $assessment->ID );
		$max_attempts = max( 1, absint( get_post_meta( $assessment->ID, Parish_Formation_Assessment_Settings::MAX_ATTEMPTS_META_KEY, true ) ) );
		$closed = $latest && ( 'pending_review' === $latest->status || (bool) $latest->passed || absint( $latest->attempt_number ) >= $max_attempts );
		$return_url = self::get_curriculum_item_url( $enrollment->course_id, array( 'type' => 'assessment', 'post' => $assessment ), self::current_url() );
		$next_item  = self::get_next_curriculum_item( $curriculum, $assessment->ID );
		$next_url   = $next_item
			? self::get_curriculum_item_url( $enrollment->course_id, $next_item, self::current_url() )
			: self::get_course_url( $enrollment->course_id );

		ob_start();
		?>
		<?php if ( isset( $_GET['pf_assessment_error'] ) ) : ?><div class="uk-alert uk-alert-danger"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['pf_assessment_error'] ) ) ); ?></p></div><?php endif; ?>
		<?php if ( $latest ) : ?>
			<div class="uk-alert <?php echo 'passed' === $latest->status ? 'uk-alert-success' : ( 'failed' === $latest->status ? 'uk-alert-danger' : 'uk-alert-primary' ); ?>">
				<p><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $latest->status ) ) ); ?></strong></p>
				<?php if ( 'pending_review' !== $latest->status ) : ?><p><?php echo esc_html( sprintf( __( 'Score: %1$s of %2$s points; %3$d of %4$d correct.', 'parish-formation' ), $latest->score_points, $latest->max_points, $latest->correct_count, $latest->total_graded ) ); ?></p><?php endif; ?>
				<p><?php echo esc_html( sprintf( __( 'Attempt %1$d of %2$d.', 'parish-formation' ), $latest->attempt_number, $max_attempts ) ); ?></p>
			</div>
		<?php endif; ?>
		<div class="pf-assessment-ajax-result" aria-live="polite"></div>
		<form class="pf-assessment-questions" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="pf_submit_assessment" />
			<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $enrollment->id ); ?>" />
			<input type="hidden" name="course_id" value="<?php echo esc_attr( $enrollment->course_id ); ?>" />
			<input type="hidden" name="assessment_id" value="<?php echo esc_attr( $assessment->ID ); ?>" />
			<input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>" />
			<input type="hidden" name="formation_base_url" value="<?php echo esc_url( self::current_url() ); ?>" />
			<?php wp_nonce_field( 'pf_submit_assessment_' . $enrollment->id . '_' . $assessment->ID ); ?>
			<?php foreach ( $questions as $index => $question ) : ?>
				<?php
				$type       = sanitize_key( get_post_meta( $question->ID, '_pf_question_type', true ) );
				$prompt     = wp_kses_post( $question->post_content );
				$options    = get_post_meta( $question->ID, '_pf_question_options', true );
				$options    = is_array( $options ) ? $options : array();
				$field_name = 'pf_answers[' . $question->ID . ']';
				$required   = ! metadata_exists( 'post', $question->ID, '_pf_question_required' ) || (bool) get_post_meta( $question->ID, '_pf_question_required', true );
				?>
				<section class="pf-assessment-question uk-card uk-card-default uk-card-body uk-margin">
					<h3><?php echo esc_html( sprintf( __( 'Question %d', 'parish-formation' ), $index + 1 ) ); ?></h3>
					<div class="pf-assessment-prompt"><?php echo $prompt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php if ( 'multiple_choice' === $type ) : ?>
						<?php foreach ( $options as $option_index => $option ) : ?>
							<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $option_index + 1 ); ?>" <?php disabled( $closed ); ?> <?php echo $required ? 'required' : ''; ?> /> <?php echo esc_html( sanitize_text_field( $option ) ); ?></label>
						<?php endforeach; ?>
					<?php elseif ( 'true_false' === $type ) : ?>
						<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="true" <?php disabled( $closed ); ?> <?php echo $required ? 'required' : ''; ?> /> <?php esc_html_e( 'True', 'parish-formation' ); ?></label>
						<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="false" <?php disabled( $closed ); ?> /> <?php esc_html_e( 'False', 'parish-formation' ); ?></label>
					<?php elseif ( 'acknowledgement' === $type ) : ?>
						<label class="pf-assessment-option"><input class="uk-checkbox" type="checkbox" name="<?php echo esc_attr( $field_name ); ?>" value="acknowledged" <?php disabled( $closed ); ?> <?php echo $required ? 'required' : ''; ?> /> <?php esc_html_e( 'I acknowledge this statement.', 'parish-formation' ); ?></label>
					<?php else : ?>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Your response', 'parish-formation' ); ?></span><textarea class="uk-textarea" name="<?php echo esc_attr( $field_name ); ?>" rows="6" placeholder="<?php esc_attr_e( 'Enter your response…', 'parish-formation' ); ?>" <?php disabled( $closed ); ?> <?php echo $required ? 'required' : ''; ?>></textarea></label>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
			<?php if ( ! $closed ) : ?><button class="uk-button uk-button-primary" type="submit"><?php esc_html_e( 'Submit Assessment', 'parish-formation' ); ?></button><?php endif; ?>
		</form>
		<?php if ( $latest && (bool) $latest->passed ) : ?>
			<div class="pf-assessment-continue uk-margin-top">
				<a class="uk-button uk-button-primary" href="<?php echo esc_url( $next_url ); ?>">
					<?php echo $next_item ? esc_html__( 'Continue to Next Section', 'parish-formation' ) : esc_html__( 'Finish Course', 'parish-formation' ); ?> &rarr;
				</a>
			</div>
		<?php endif; ?>
		<?php
		return (string) ob_get_clean();
	}

	/** Recursively find assessment question blocks. */
	private static function find_question_blocks( $blocks ) {
		$questions = array();
		foreach ( $blocks as $block ) {
			if ( Parish_Formation_Question_Block::BLOCK_NAME === ( $block['blockName'] ?? '' ) ) {
				$questions[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$questions = array_merge( $questions, self::find_question_blocks( $block['innerBlocks'] ) );
			}
		}
		return $questions;
	}

	/** Find a published post of a given type in the curriculum. */
	private static function find_curriculum_post( $curriculum, $type, $post_id ) {
		foreach ( $curriculum as $item ) {
			if ( $type === $item['type'] && absint( $post_id ) === $item['post']->ID ) {
				return $item['post'];
			}
		}
		return null;
	}

	/** Determine whether an item has an unfinished lesson before it. */
	private static function is_curriculum_item_locked( $curriculum, $item_id, $statuses, $enrollment_id ) {
		foreach ( $curriculum as $item ) {
			if ( absint( $item_id ) === $item['post']->ID ) {
				return false;
			}
			if ( 'lesson' === $item['type'] ) {
				$status = $statuses[ $item['post']->ID ] ?? '';
				if ( ! in_array( $status, array( 'completed', 'skipped' ), true ) ) {
					return true;
				}
			} else {
				$progression = get_post_meta( $item['post']->ID, Parish_Formation_Assessment_Settings::PROGRESSION_META_KEY, true );
				if ( 'no_gate' !== $progression ) {
					$attempt = Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment_id, $item['post']->ID );
					if ( ! $attempt || ( 'submit_to_continue' !== $progression && ! (bool) $attempt->passed ) ) {
						return true;
					}
				}
			}
		}
		return true;
	}

	/** Get the item immediately following a curriculum item. */
	private static function get_next_curriculum_item( $curriculum, $item_id ) {
		foreach ( $curriculum as $index => $item ) {
			if ( absint( $item_id ) === $item['post']->ID ) {
				return $curriculum[ $index + 1 ] ?? null;
			}
		}
		return null;
	}

	/** Get the first unfinished required curriculum item. */
	private static function get_current_curriculum_item( $curriculum, $enrollment_id ) {
		$statuses = Parish_Formation_Progress_Repository::get_statuses( $enrollment_id );
		foreach ( $curriculum as $item ) {
			if ( 'lesson' === $item['type'] ) {
				if ( ! in_array( $statuses[ $item['post']->ID ] ?? '', array( 'completed', 'skipped' ), true ) ) {
					return $item;
				}
				continue;
			}
			$progression = get_post_meta( $item['post']->ID, Parish_Formation_Assessment_Settings::PROGRESSION_META_KEY, true );
			if ( 'no_gate' === $progression ) {
				continue;
			}
			$attempt = Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment_id, $item['post']->ID );
			if ( ! $attempt || ( 'submit_to_continue' !== $progression && ! (bool) $attempt->passed ) ) {
				return $item;
			}
		}
		return null;
	}

	/** Build a curriculum item URL while removing conflicting item arguments. */
	private static function get_curriculum_item_url( $course_id, $item, $base_url ) {
		return trailingslashit( $base_url ) . 'course/' . rawurlencode( get_post_field( 'post_name', absint( $course_id ) ) ) . '/' . rawurlencode( $item['type'] ) . '/' . rawurlencode( $item['post']->post_name ) . '/';
	}

	/** Build the clean course overview URL. */
	private static function get_course_url( $course_id ) {
		return trailingslashit( self::current_url() ) . 'course/' . rawurlencode( get_post_field( 'post_name', absint( $course_id ) ) ) . '/';
	}

	/** Parse the course endpoint attached to the shortcode page. */
	private static function get_pretty_route() {
		$route = trim( (string) get_query_var( 'course', '' ), '/' );
		if ( '' === $route ) {
			return null;
		}
		$parts  = array_map( 'sanitize_title', explode( '/', $route ) );
		$course = get_page_by_path( $parts[0], OBJECT, Parish_Formation_Course_Post_Type::POST_TYPE );
		if ( ! $course || 'publish' !== $course->post_status ) {
			return new WP_Error( 'invalid_course_route', __( 'This course could not be found.', 'parish-formation' ) );
		}
		$item_type = $parts[1] ?? '';
		$item_slug = $parts[2] ?? '';
		if ( $item_type && ! in_array( $item_type, array( 'lesson', 'assessment' ), true ) ) {
			return new WP_Error( 'invalid_item_route', __( 'This course section could not be found.', 'parish-formation' ) );
		}
		return array( 'course_id' => $course->ID, 'item_type' => $item_type, 'item_slug' => $item_slug );
	}

	/** Find a curriculum item ID by its course-scoped slug. */
	private static function find_curriculum_id_by_slug( $curriculum, $type, $slug ) {
		foreach ( $curriculum as $item ) {
			if ( $type === $item['type'] && $slug === $item['post']->post_name ) {
				return $item['post']->ID;
			}
		}
		return 0;
	}

	/**
	 * Render an enrolled participant's course introduction and lesson list.
	 *
	 * @param int $course_id Course post ID.
	 * @return string
	 */
	private static function render_course( $course_id, $route_item_type = '', $route_item_slug = '' ) {
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

		$lessons   = Parish_Formation_Course_Repository::get_published_lessons( $course_id );
		$curriculum = Parish_Formation_Course_Repository::get_published_curriculum( $course_id );
		$sequence = self::get_sequence( $enrollment->id, $lessons );
		$progress = Parish_Formation_Progress_Repository::get_summary( $enrollment->id, $lessons, $course_id );
		self::reconcile_course_completion( $enrollment, $lessons, $progress );
		$lesson_id    = 'lesson' === $route_item_type ? self::find_curriculum_id_by_slug( $curriculum, 'lesson', $route_item_slug ) : ( isset( $_GET['pf_lesson'] ) ? absint( $_GET['pf_lesson'] ) : 0 );
		$assessment_id  = 'assessment' === $route_item_type ? self::find_curriculum_id_by_slug( $curriculum, 'assessment', $route_item_slug ) : ( isset( $_GET['pf_assessment'] ) ? absint( $_GET['pf_assessment'] ) : 0 );
		$active_lesson  = null;
		$active_assessment = null;
		if ( ( 'lesson' === $route_item_type && ! $lesson_id ) || ( 'assessment' === $route_item_type && ! $assessment_id ) ) {
			return '<div class="uk-alert uk-alert-danger"><p>' . esc_html__( 'This course section could not be found.', 'parish-formation' ) . '</p></div>';
		}

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

		if ( $assessment_id ) {
			$active_assessment = self::find_curriculum_post( $curriculum, 'assessment', $assessment_id );
			if ( ! $active_assessment ) {
				return '<div class="uk-alert uk-alert-danger"><p>' . esc_html__( 'This assessment is not available in your course.', 'parish-formation' ) . '</p></div>';
			}
			if ( self::is_curriculum_item_locked( $curriculum, $assessment_id, $sequence['statuses'], $enrollment->id ) ) {
				return '<div class="uk-alert uk-alert-warning"><p>' . esc_html__( 'Complete the preceding lessons before opening this assessment.', 'parish-formation' ) . '</p></div>';
			}
		}

		return self::render_learning_layout( $enrollment, $lessons, $curriculum, $sequence, $progress, $active_lesson, $active_assessment );
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
	private static function render_learning_layout( $enrollment, $lessons, $curriculum, $sequence, $progress, $active_lesson, $active_assessment ) {
		$course_url  = self::get_course_url( $enrollment->course_id );
		$my_url      = self::current_url();
		$active_id   = $active_lesson ? $active_lesson->ID : 0;
		$active_assessment_id = $active_assessment ? $active_assessment->ID : 0;
		$lesson_index = $active_lesson ? self::find_lesson_index( $lessons, $active_id ) : null;
		$current_item = self::get_current_curriculum_item( $curriculum, $enrollment->id );

		ob_start();
		?>
		<div class="pf-learning-layout">
			<aside class="pf-course-sidebar">
				<a class="pf-back-link" href="<?php echo esc_url( $my_url ); ?>">&larr; <?php echo esc_html__( 'My Formation', 'parish-formation' ); ?></a>
				<h2><?php echo esc_html( $enrollment->course_title ); ?></h2>
				<progress class="uk-progress" value="<?php echo esc_attr( $progress['percentage'] ); ?>" max="100"></progress>
				<p class="pf-progress-text"><?php echo esc_html( $progress['percentage'] . '% ' . __( 'complete', 'parish-formation' ) ); ?></p>

				<nav class="pf-lesson-navigation" aria-label="<?php echo esc_attr__( 'Course curriculum', 'parish-formation' ); ?>">
					<ol>
						<?php foreach ( $curriculum as $index => $item ) : ?>
							<?php
							$item_post = $item['post'];
							$is_lesson = 'lesson' === $item['type'];
							$status   = $is_lesson ? ( $sequence['statuses'][ $item_post->ID ] ?? '' ) : '';
							$is_done  = $is_lesson && in_array( $status, array( 'completed', 'skipped' ), true );
							if ( ! $is_lesson ) {
								$assessment_progression = get_post_meta( $item_post->ID, Parish_Formation_Assessment_Settings::PROGRESSION_META_KEY, true );
								$assessment_attempt = Parish_Formation_Assessment_Repository::get_latest_attempt( $enrollment->id, $item_post->ID );
								$is_done = 'no_gate' === $assessment_progression || ( $assessment_attempt && ( 'submit_to_continue' === $assessment_progression || (bool) $assessment_attempt->passed ) );
							}
							$is_locked = self::is_curriculum_item_locked( $curriculum, $item_post->ID, $sequence['statuses'], $enrollment->id );
							$is_active = $is_lesson ? $active_id === $item_post->ID : $active_assessment_id === $item_post->ID;
							$item_classes = implode( ' ', array_filter( array( $is_done ? 'is-complete' : '', $is_locked ? 'is-locked' : '', $is_active ? 'is-active' : '' ) ) );
							?>
				<li class="<?php echo esc_attr( $item_classes ); ?>" data-item-id="<?php echo esc_attr( $item_post->ID ); ?>" data-item-type="<?php echo esc_attr( $item['type'] ); ?>">
								<span class="pf-lesson-marker" aria-hidden="true"><?php echo $is_done ? '&#10003;' : esc_html( $index + 1 ); ?></span>
								<div>
									<?php if ( ! $is_locked ) : ?>
										<a href="<?php echo esc_url( self::get_curriculum_item_url( $enrollment->course_id, $item, self::current_url() ) ); ?>"><?php echo esc_html( $item_post->post_title ); ?></a>
									<?php else : ?>
										<span><?php echo esc_html( $item_post->post_title ); ?></span>
									<?php endif; ?>
									<small>
										<?php echo $is_lesson ? ( Parish_Formation_Course_Repository::is_lesson_required( $item_post->ID ) ? esc_html__( 'Required lesson', 'parish-formation' ) : esc_html__( 'Optional lesson', 'parish-formation' ) ) : esc_html__( 'Assessment', 'parish-formation' ); ?>
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
				<?php if ( $active_assessment ) : ?>
					<header class="pf-content-header"><h1><?php echo esc_html( $active_assessment->post_title ); ?></h1></header>
					<article class="pf-content-body uk-article">
						<?php echo self::render_assessment_questions( $active_assessment, $enrollment, $curriculum ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
				<?php elseif ( $active_lesson ) : ?>
					<header class="pf-content-header"><h1><?php echo esc_html( $active_lesson->post_title ); ?></h1></header>
					<article class="pf-content-body uk-article">
						<?php echo apply_filters( 'the_content', $active_lesson->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</article>
					<footer class="pf-content-footer">
						<?php if ( $lesson_index === $sequence['current_index'] ) : ?>
							<?php
							$next_item  = self::get_next_curriculum_item( $curriculum, $active_lesson->ID );
							$return_url = $next_item ? self::get_curriculum_item_url( $enrollment->course_id, $next_item, self::current_url() ) : $course_url;
							?>
							<form class="parish-formation-complete-lesson" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
								<input type="hidden" name="action" value="pf_complete_lesson" />
								<input type="hidden" name="enrollment_id" value="<?php echo esc_attr( $enrollment->id ); ?>" />
								<input type="hidden" name="course_id" value="<?php echo esc_attr( $enrollment->course_id ); ?>" />
								<input type="hidden" name="lesson_id" value="<?php echo esc_attr( $active_lesson->ID ); ?>" />
								<input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>" />
								<input type="hidden" name="formation_base_url" value="<?php echo esc_url( self::current_url() ); ?>" />
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
					<?php if ( ! $progress['is_complete'] && $current_item ) : ?>
						<footer class="pf-content-footer">
							<a class="uk-button uk-button-primary" href="<?php echo esc_url( self::get_curriculum_item_url( $enrollment->course_id, $current_item, self::current_url() ) ); ?>"><?php echo esc_html__( 'Continue Course', 'parish-formation' ); ?> &rarr;</a>
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
		if ( self::$rest_base_url ) {
			return self::$rest_base_url;
		}
		return get_permalink( get_queried_object_id() );
	}
}
