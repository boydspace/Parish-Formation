<?php
/** Centralized formation email delivery and logging. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Sends consistent, configurable, and auditable formation notifications. */
final class Parish_Formation_Notifications {
	public const SETTINGS_OPTION = 'parish_formation_notification_settings';

	/** Supported notification types grouped by recipient. */
	public static function types() {
		return array(
			'course_invitation' => array( 'account', __( 'Course invitation', 'parish-formation' ) ),
			'passwordless_login' => array( 'account', __( 'Passwordless login', 'parish-formation' ) ),
			'new_user_registration' => array( 'account', __( 'New WordPress user account', 'parish-formation' ) ),
			'enrollment_confirmation' => array( 'participant', __( 'Enrollment confirmation', 'parish-formation' ) ),
			'enrollment_expiration_reminder' => array( 'participant', __( 'Enrollment expiration reminder', 'parish-formation' ) ),
			'course_expired' => array( 'participant', __( 'Course access expired', 'parish-formation' ) ),
			'course_completed' => array( 'participant', __( 'Course completion', 'parish-formation' ) ),
			'assessment_submitted' => array( 'participant', __( 'Assessment submitted', 'parish-formation' ) ),
			'assessment_passed' => array( 'participant', __( 'Assessment passed', 'parish-formation' ) ),
			'assessment_failed' => array( 'participant', __( 'Assessment failed', 'parish-formation' ) ),
			'assessment_pending_review' => array( 'participant', __( 'Assessment awaiting manual review', 'parish-formation' ) ),
			'assessment_reviewed' => array( 'participant', __( 'Manual assessment review completed', 'parish-formation' ) ),
			'acknowledgement_received' => array( 'participant', __( 'Acknowledgement received', 'parish-formation' ) ),
			'acknowledgement_reviewed' => array( 'participant', __( 'Acknowledgement response reviewed', 'parish-formation' ) ),
			'certificate_issued' => array( 'participant', __( 'Certificate issued', 'parish-formation' ) ),
			'certificate_reissued' => array( 'participant', __( 'Certificate reissued', 'parish-formation' ) ),
			'certificate_revoked' => array( 'participant', __( 'Certificate revoked', 'parish-formation' ) ),
			'course_reset' => array( 'participant', __( 'Course reset or new run started', 'parish-formation' ) ),
			'unenrolled' => array( 'participant', __( 'Unenrollment notice', 'parish-formation' ) ),
			'manual_participant_reminder' => array( 'participant', __( 'Manual participant reminder', 'parish-formation' ) ),
			'staff_assessment_pending' => array( 'staff', __( 'Staff: assessment awaiting review', 'parish-formation' ) ),
			'staff_course_completed' => array( 'staff', __( 'Staff: participant completed a course', 'parish-formation' ) ),
		);
	}

	/** Default subject and body for every supported event. */
	public static function default_templates() {
		return array(
			'course_invitation' => array( __( 'You are invited to {course_name}', 'parish-formation' ), __( 'Hello,\n\nYou have been invited to <strong>{course_name}</strong> at {site_name}.\n\n<a href="{invitation_url}">Accept Course Invitation</a>\n\nIf you do not already have an account, the invitation page will help you create one.', 'parish-formation' ) ),
			'passwordless_login' => array( __( 'Your secure login for {site_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nUse the button below to securely log in. This link and code expire in 15 minutes and can each be used only once.\n\n<a href="{magic_login_url}">Log In Securely</a>\n\nOr enter this one-time code on the login page: <strong>{login_code}</strong>\n\nIf you did not request this email, you can safely ignore it.', 'parish-formation' ) ),
			'new_user_registration' => array( __( 'Set up your account at {site_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nAn account has been created for you at <strong>{site_name}</strong>. Your username is <strong>{username}</strong>.\n\n<a href="{password_setup_url}">Set Your Password</a>\n\nAfter setting your password, you can sign in at <a href="{login_url}">{login_url}</a>.', 'parish-formation' ) ),
			'enrollment_confirmation' => array( __( 'You are enrolled in {course_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nYou have been enrolled in <strong>{course_name}</strong>.\n\n<a href="{formation_url}">Open My Formation</a>', 'parish-formation' ) ),
			'enrollment_expiration_reminder' => array( __( 'Your access to {course_name} expires soon', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour access to <strong>{course_name}</strong> expires on {expiration_date}. Please complete the remaining course work before that date.\n\n<a href="{formation_url}">Continue My Formation</a>', 'parish-formation' ) ),
			'course_expired' => array( __( 'Your access to {course_name} has expired', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour access to <strong>{course_name}</strong> expired on {expiration_date}. Please contact the parish if you need assistance.', 'parish-formation' ) ),
			'course_completed' => array( __( 'You completed {course_name}', 'parish-formation' ), __( 'Congratulations, {participant_name}!\n\nYou completed <strong>{course_name}</strong> on {completion_date}.', 'parish-formation' ) ),
			'assessment_submitted' => array( __( 'Your {assessment_name} assessment was submitted', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour assessment <strong>{assessment_name}</strong> for {course_name} was submitted successfully.', 'parish-formation' ) ),
			'assessment_passed' => array( __( 'You passed {assessment_name}', 'parish-formation' ), __( 'Congratulations, {participant_name}!\n\nYou passed <strong>{assessment_name}</strong> with a score of {score}.\n\n<a href="{formation_url}">Continue My Formation</a>', 'parish-formation' ) ),
			'assessment_failed' => array( __( 'Assessment result for {assessment_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour score for <strong>{assessment_name}</strong> was {score}, which did not meet the passing requirement of {passing_score}. {attempts_message}', 'parish-formation' ) ),
			'assessment_pending_review' => array( __( '{assessment_name} is awaiting review', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour submission for <strong>{assessment_name}</strong> was received and is awaiting review by parish staff.', 'parish-formation' ) ),
			'assessment_reviewed' => array( __( 'Your {assessment_name} review is complete', 'parish-formation' ), __( 'Hello {participant_name},\n\nParish staff completed the review of <strong>{assessment_name}</strong>. Your result is {assessment_result} with a score of {score}. {review_note}\n\n<a href="{formation_url}">Return to My Formation</a>', 'parish-formation' ) ),
			'acknowledgement_received' => array( __( 'Your {assessment_name} submission was received', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour acknowledgement or response for <strong>{assessment_name}</strong> in {course_name} was received successfully.\n\n<a href="{formation_url}">Continue My Formation</a>', 'parish-formation' ) ),
			'acknowledgement_reviewed' => array( __( 'Your {assessment_name} response was reviewed', 'parish-formation' ), __( 'Hello {participant_name},\n\nParish staff reviewed your response for <strong>{assessment_name}</strong>. {review_note}\n\n<a href="{formation_url}">Return to My Formation</a>', 'parish-formation' ) ),
			'certificate_issued' => array( __( 'Your certificate for {course_name}', 'parish-formation' ), __( 'Congratulations, {participant_name}!\n\nYour certificate for <strong>{course_name}</strong> has been issued. Your verification code is <strong>{certificate_code}</strong>.\n\n<a href="{certificate_url}">View Certificate</a> &nbsp; <a href="{certificate_pdf_url}">Download PDF</a>', 'parish-formation' ) ),
			'certificate_reissued' => array( __( 'Replacement certificate for {course_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nA replacement certificate for <strong>{course_name}</strong> has been issued. Your new verification code is <strong>{certificate_code}</strong>.\n\n<a href="{certificate_url}">View Certificate</a> &nbsp; <a href="{certificate_pdf_url}">Download PDF</a>', 'parish-formation' ) ),
			'certificate_revoked' => array( __( 'Certificate revoked for {course_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour certificate for <strong>{course_name}</strong> has been revoked for the following reason:\n\n{revocation_reason}\n\nPlease contact the parish if you have questions.', 'parish-formation' ) ),
			'course_reset' => array( __( 'A new course run has started for {course_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nYour progress in <strong>{course_name}</strong> has been reset and course run {course_run} is ready.\n\n<a href="{formation_url}">Begin the Course</a>', 'parish-formation' ) ),
			'unenrolled' => array( __( 'You were unenrolled from {course_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nYou have been unenrolled from <strong>{course_name}</strong>. Please contact the parish if you believe this was unexpected.', 'parish-formation' ) ),
			'manual_participant_reminder' => array( __( 'Reminder about {course_name}', 'parish-formation' ), __( 'Hello {participant_name},\n\nThis is a reminder about <strong>{course_name}</strong>.\n\n{reminder_message}\n\n<a href="{formation_url}">Continue My Formation</a>', 'parish-formation' ) ),
			'staff_assessment_pending' => array( __( 'Assessment awaiting review: {participant_name}', 'parish-formation' ), __( '<strong>{participant_name}</strong> submitted {assessment_name} for {course_name}.\n\n<a href="{review_url}">Review Assessment</a>', 'parish-formation' ) ),
			'staff_course_completed' => array( __( '{participant_name} completed {course_name}', 'parish-formation' ), __( '<strong>{participant_name}</strong> completed {course_name} on {completion_date}.\n\n<a href="{report_url}">View Course Report</a>', 'parish-formation' ) ),
		);
	}

	/** Placeholders allowed in one template, including common values. */
	public static function placeholders( $type ) {
		$common = array( 'participant_name', 'course_name', 'formation_url', 'site_name' );
		$specific = array(
			'course_invitation' => array( 'invitation_url', 'expiration_date' ),
			'passwordless_login' => array( 'magic_login_url', 'login_code' ),
			'new_user_registration' => array( 'username', 'login_url', 'password_setup_url' ),
			'enrollment_expiration_reminder' => array( 'expiration_date' ), 'course_expired' => array( 'expiration_date' ),
			'course_completed' => array( 'completion_date' ), 'assessment_submitted' => array( 'assessment_name' ),
			'assessment_passed' => array( 'assessment_name', 'score' ), 'assessment_failed' => array( 'assessment_name', 'score', 'passing_score', 'attempts_message' ),
			'assessment_pending_review' => array( 'assessment_name' ), 'assessment_reviewed' => array( 'assessment_name', 'assessment_result', 'score', 'review_note' ),
			'acknowledgement_received' => array( 'assessment_name' ), 'acknowledgement_reviewed' => array( 'assessment_name', 'review_note' ),
			'certificate_issued' => array( 'certificate_code', 'certificate_url', 'certificate_pdf_url' ), 'certificate_reissued' => array( 'certificate_code', 'certificate_url', 'certificate_pdf_url' ),
			'certificate_revoked' => array( 'revocation_reason' ), 'course_reset' => array( 'course_run' ),
			'manual_participant_reminder' => array( 'reminder_message' ),
			'staff_assessment_pending' => array( 'assessment_name', 'review_url' ), 'staff_course_completed' => array( 'completion_date', 'report_url' ),
		);
		return array_unique( array_merge( $common, isset( $specific[ $type ] ) ? $specific[ $type ] : array() ) );
	}

	/** Return the saved template or its default. */
	public static function template( $type ) {
		$defaults = self::default_templates();
		$settings = get_option( self::SETTINGS_OPTION, array() );
		if ( ! isset( $defaults[ $type ] ) ) {
			return array( '', '' );
		}
		$template    = isset( $settings['templates'][ $type ] ) ? array( $settings['templates'][ $type ]['subject'], $settings['templates'][ $type ]['body'] ) : $defaults[ $type ];
		$template[1] = str_replace( '\\n', "\n", $template[1] );
		return $template;
	}

	/** Replace template placeholders and return resolved subject and body. */
	public static function resolve_template( $type, $values ) {
		$template = self::template( $type );
		$values   = array_merge( array( 'site_name' => get_bloginfo( 'name' ) ), $values );
		$replace  = array();
		foreach ( $values as $key => $value ) {
			$replace[ '{' . sanitize_key( $key ) . '}' ] = (string) $value;
		}
		return array( strtr( $template[0], $replace ), strtr( $template[1], $replace ) );
	}

	/** Return representative values for previews and test messages. */
	public static function sample_values() {
		return array(
			'participant_name' => __( 'Jordan Smith', 'parish-formation' ), 'course_name' => __( 'Baptism Preparation', 'parish-formation' ), 'formation_url' => home_url( '/my-formation/' ),
			'username' => 'jordan.smith', 'login_url' => wp_login_url(), 'password_setup_url' => Parish_Formation_Account_Shortcodes::get_password_reset_url( 'sample-key', 'jordan.smith' ),
			'expiration_date' => wp_date( get_option( 'date_format' ), strtotime( '+14 days' ) ), 'completion_date' => wp_date( get_option( 'date_format' ) ), 'assessment_name' => __( 'Course Assessment', 'parish-formation' ),
			'score' => '9/10 (90%)', 'passing_score' => '9/10', 'attempts_message' => __( 'You may try again.', 'parish-formation' ), 'assessment_result' => __( 'Passed', 'parish-formation' ),
			'review_note' => __( 'Thank you for your thoughtful response.', 'parish-formation' ), 'certificate_code' => 'SAMPLE123456789ABCDE', 'certificate_url' => home_url( '/formation-certificate/SAMPLE123456789ABCDE/' ),
			'certificate_pdf_url' => home_url( '/?sample-certificate-pdf=1' ), 'revocation_reason' => __( 'Sample administrative reason.', 'parish-formation' ), 'course_run' => '2',
			'review_url' => admin_url( 'admin.php?page=parish-formation-assessment-reviews' ), 'report_url' => admin_url( 'admin.php?page=parish-formation-course-reports' ),
			'invitation_url' => home_url( '/available-courses/?pf_invitation=sample-token' ),
			'magic_login_url' => home_url( '/?sample-magic-login=1' ), 'login_code' => '123456',
			'reminder_message' => __( 'Please continue your course when you are able. Contact the parish office if you need assistance.', 'parish-formation' ),
		);
	}

	/** Send a newly created email-restricted course invitation. */
	public static function send_course_invitation( $invitation, $raw_token, $context_suffix = '' ) {
		if ( ! $invitation || ! $invitation->restricted_email || ! self::is_enabled_for_course( 'course_invitation', $invitation->course_id ) ) {
			return false;
		}
		$url = add_query_arg( 'pf_invitation', rawurlencode( $raw_token ), Parish_Formation_Shortcodes::get_course_catalog_url() );
		$content = self::resolve_template(
			'course_invitation',
			array(
				'course_name'     => get_the_title( $invitation->course_id ),
				'invitation_url'  => $url,
				'expiration_date' => $invitation->expires_at ? get_date_from_gmt( $invitation->expires_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : __( 'No expiration', 'parish-formation' ),
			)
		);
		$context = 'invitation_' . absint( $invitation->id ) . ( $context_suffix ? '_' . sanitize_key( $context_suffix ) : '' );
		return self::send( 'course_invitation', $invitation->restricted_email, $content[0], self::types()['course_invitation'][1], $content[1], $context );
	}

	/** Render one resolved message inside the shared branded wrapper. */
	public static function preview_html( $heading, $message ) {
		return self::render_template( $heading, $message );
	}

	/** Return normalized settings with safe defaults. */
	public static function settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		$enabled = array();
		foreach ( self::types() as $type => $definition ) {
			$enabled[ $type ] = ! isset( $saved['enabled'] ) || ! array_key_exists( $type, $saved['enabled'] ) ? true : (bool) $saved['enabled'][ $type ];
		}
		return array(
			'from_name' => isset( $saved['from_name'] ) && $saved['from_name'] ? sanitize_text_field( $saved['from_name'] ) : sanitize_text_field( get_bloginfo( 'name' ) ),
			'from_email' => isset( $saved['from_email'] ) && is_email( $saved['from_email'] ) ? sanitize_email( $saved['from_email'] ) : sanitize_email( get_option( 'admin_email' ) ),
			'reply_to' => isset( $saved['reply_to'] ) && is_email( $saved['reply_to'] ) ? sanitize_email( $saved['reply_to'] ) : sanitize_email( get_option( 'admin_email' ) ),
			'staff_emails' => isset( $saved['staff_emails'] ) ? self::sanitize_email_list( $saved['staff_emails'] ) : sanitize_email( get_option( 'admin_email' ) ),
			'reminder_days' => self::sanitize_reminder_days( $saved['reminder_days'] ?? '30, 14, 7, 1' ),
			'enabled' => $enabled,
		);
	}

	/** Return the global email-brand design with safe defaults. */
	public static function design() {
		$saved  = get_option( self::SETTINGS_OPTION, array() );
		$design = isset( $saved['design'] ) && is_array( $saved['design'] ) ? $saved['design'] : array();
		$color  = static function ( $value, $default ) {
			$value = sanitize_hex_color( $value );
			return $value ?: $default;
		};
		return array(
			'header_name'       => isset( $design['header_name'] ) && $design['header_name'] ? sanitize_text_field( $design['header_name'] ) : sanitize_text_field( get_bloginfo( 'name' ) ),
			'logo_url'          => isset( $design['logo_url'] ) ? esc_url_raw( $design['logo_url'] ) : '',
			'page_color'        => $color( $design['page_color'] ?? '', '#f3f4f6' ),
			'header_color'      => $color( $design['header_color'] ?? '', '#1d3557' ),
			'header_text_color' => $color( $design['header_text_color'] ?? '', '#ffffff' ),
			'content_color'     => $color( $design['content_color'] ?? '', '#ffffff' ),
			'text_color'        => $color( $design['text_color'] ?? '', '#1d2327' ),
			'link_color'        => $color( $design['link_color'] ?? '', '#2271b1' ),
			'footer_color'      => $color( $design['footer_color'] ?? '', '#f6f7f7' ),
			'footer_text'       => isset( $design['footer_text'] ) && $design['footer_text'] ? sanitize_text_field( $design['footer_text'] ) : __( 'This message was sent by Parish Formation.', 'parish-formation' ),
			'contact_text'      => isset( $design['contact_text'] ) ? sanitize_textarea_field( $design['contact_text'] ) : '',
			'container_width'   => isset( $design['container_width'] ) ? min( 760, max( 520, absint( $design['container_width'] ) ) ) : 640,
		);
	}

	/** Send one HTML notification and write its delivery outcome. */
	public static function send( $type, $recipient, $subject, $heading, $message, $context_key, $force = false, $initiated_by = 0, $participant_user_id = 0 ) {
		global $wpdb;
		$types     = self::types();
		$settings  = self::settings();
		$recipient = sanitize_email( $recipient );
		if ( ! isset( $types[ $type ] ) || ! $recipient || ( ! $force && empty( $settings['enabled'][ $type ] ) ) ) {
			return false;
		}
		$context_key = sanitize_key( $context_key );
		if ( strlen( $context_key ) > 64 ) {
			$context_key = hash( 'sha256', $context_key );
		}
		$table       = $wpdb->prefix . 'pf_notification_log';
		$existing    = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE notification_type = %s AND recipient = %s AND context_key = %s LIMIT 1", $type, $recipient, $context_key ) );
		if ( $existing ) {
			return true;
		}
		$subject = sanitize_text_field( wp_strip_all_tags( $subject ) );
		$body    = self::render_template( $heading, $message );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>',
			'Reply-To: ' . $settings['reply_to'],
		);
		$sent = wp_mail( $recipient, $subject, $body, $headers );
		$wpdb->insert(
			$table,
			array( 'notification_type' => $type, 'recipient' => $recipient, 'subject' => $subject, 'message_body' => $sent ? null : $body, 'status' => $sent ? 'sent' : 'failed', 'context_key' => $context_key, 'error_message' => $sent ? null : __( 'WordPress could not hand the message to the configured mail service.', 'parish-formation' ), 'sent_at' => $sent ? current_time( 'mysql', true ) : null, 'created_at' => current_time( 'mysql', true ), 'initiated_by' => absint( $initiated_by ), 'participant_user_id' => absint( $participant_user_id ) ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
		return $sent;
	}

	/** Send a one-off, course-aware reminder initiated by a staff member. */
	public static function send_manual_participant_reminder( $enrollment_id, $message, $actor_id ) {
		$enrollment = Parish_Formation_Enrollment_Repository::get_details( absint( $enrollment_id ) );
		$user       = $enrollment ? get_userdata( $enrollment->user_id ) : null;
		if ( ! $enrollment || ! $user || ! in_array( $enrollment->status, array( 'enrolled', 'in_progress' ), true ) || ! self::is_enabled_for_course( 'manual_participant_reminder', $enrollment->course_id ) ) {
			return false;
		}
		$content = self::resolve_template( 'manual_participant_reminder', array( 'participant_name' => $user->display_name, 'course_name' => get_the_title( $enrollment->course_id ), 'formation_url' => home_url( '/my-formation/' ), 'reminder_message' => $message ) );
		$context = 'manual_' . absint( $enrollment->id ) . '_' . absint( $actor_id ) . '_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 6, false, false );
		return self::send( 'manual_participant_reminder', $user->user_email, $content[0], self::types()['manual_participant_reminder'][1], $content[1], $context, false, $actor_id, $user->ID );
	}

	/** Send one participant event using enrollment context and the saved template. */
	public static function send_participant_event( $type, $enrollment_id, $extra_values = array(), $context_suffix = '' ) {
		$enrollment = Parish_Formation_Enrollment_Repository::get_details( absint( $enrollment_id ) );
		$user       = $enrollment ? get_userdata( $enrollment->user_id ) : null;
		if ( ! $enrollment || ! $user || ! self::is_enabled_for_course( $type, $enrollment->course_id ) ) {
			return false;
		}
		$values = array_merge(
			array(
				'participant_name' => $user->display_name,
				'course_name'      => get_the_title( $enrollment->course_id ),
				'formation_url'    => home_url( '/my-formation/' ),
				'expiration_date'  => $enrollment->expires_at ? get_date_from_gmt( $enrollment->expires_at, get_option( 'date_format' ) ) : '',
				'course_run'       => max( 1, absint( $enrollment->current_run ) ),
			),
			$extra_values
		);
		$content = self::resolve_template( $type, $values );
		$context = 'enrollment_' . absint( $enrollment->id ) . '_run_' . max( 1, absint( $enrollment->current_run ) ) . '_cycle_' . sanitize_key( $enrollment->enrolled_at ) . ( $context_suffix ? '_' . sanitize_key( $context_suffix ) : '' );
		return self::send( $type, $user->user_email, $content[0], self::types()[ $type ][1], $content[1], $context );
	}

	/** Send one course-aware event to every configured staff recipient. */
	public static function send_staff_event( $type, $enrollment_id, $extra_values, $context_suffix ) {
		$enrollment = Parish_Formation_Enrollment_Repository::get_details( absint( $enrollment_id ) );
		$user       = $enrollment ? get_userdata( $enrollment->user_id ) : null;
		if ( ! $enrollment || ! $user || ! self::is_enabled_for_course( $type, $enrollment->course_id ) ) {
			return false;
		}
		$values = array_merge( array( 'participant_name' => $user->display_name, 'course_name' => get_the_title( $enrollment->course_id ), 'formation_url' => home_url( '/my-formation/' ) ), $extra_values );
		$content = self::resolve_template( $type, $values );
		$sent_any = false;
		foreach ( self::staff_recipients( $enrollment->course_id ) as $recipient ) {
			$sent_any = self::send( $type, $recipient, $content[0], self::types()[ $type ][1], $content[1], 'enrollment_' . absint( $enrollment_id ) . '_' . sanitize_key( $context_suffix ) ) || $sent_any;
		}
		return $sent_any;
	}

	/** Dispatch all appropriate notifications for a new assessment attempt. */
	public static function send_assessment_submission( $enrollment_id, $assessment_id, $attempt ) {
		if ( ! $attempt ) {
			return;
		}
		$maximum   = (float) $attempt->max_points;
		$score      = (float) $attempt->score_points;
		$percentage = $maximum > 0 ? round( ( $score / $maximum ) * 100, 1 ) : 100;
		$score_text = rtrim( rtrim( number_format( $score, 2, '.', '' ), '0' ), '.' ) . '/' . rtrim( rtrim( number_format( $maximum, 2, '.', '' ), '0' ), '.' ) . ' (' . $percentage . '%)';
		$passing    = 'percentage' === $attempt->passing_rule ? $attempt->passing_value . '%' : rtrim( rtrim( number_format( (float) $attempt->passing_value, 2, '.', '' ), '0' ), '.' );
		$max_attempts = max( 1, absint( get_post_meta( $assessment_id, Parish_Formation_Assessment_Settings::MAX_ATTEMPTS_META_KEY, true ) ) );
		$remaining  = max( 0, $max_attempts - absint( $attempt->attempt_number ) );
		$values     = array( 'assessment_name' => get_the_title( $assessment_id ), 'score' => $score_text, 'passing_score' => $passing, 'attempts_message' => $remaining ? sprintf( _n( 'You have %d attempt remaining.', 'You have %d attempts remaining.', $remaining, 'parish-formation' ), $remaining ) : __( 'No attempts remain.', 'parish-formation' ) );
		$suffix     = 'attempt_' . absint( $attempt->id );
		if ( Parish_Formation_Assessment_Settings::is_acknowledgement_mode( $assessment_id ) ) {
			self::send_participant_event( 'acknowledgement_received', $enrollment_id, $values, $suffix . '_acknowledgement_received' );
			if ( 'pending_review' === $attempt->status ) {
				self::send_staff_event( 'staff_assessment_pending', $enrollment_id, array_merge( $values, array( 'review_url' => admin_url( 'admin.php?page=parish-formation-assessment-reviews' ) ) ), $suffix . '_staff_pending' );
			}
			return;
		}
		self::send_participant_event( 'assessment_submitted', $enrollment_id, $values, $suffix . '_submitted' );
		if ( 'passed' === $attempt->status ) {
			self::send_participant_event( 'assessment_passed', $enrollment_id, $values, $suffix . '_passed' );
		} elseif ( 'failed' === $attempt->status ) {
			self::send_participant_event( 'assessment_failed', $enrollment_id, $values, $suffix . '_failed' );
		} elseif ( 'pending_review' === $attempt->status ) {
			self::send_participant_event( 'assessment_pending_review', $enrollment_id, $values, $suffix . '_pending' );
			self::send_staff_event( 'staff_assessment_pending', $enrollment_id, array_merge( $values, array( 'review_url' => admin_url( 'admin.php?page=parish-formation-assessment-reviews' ) ) ), $suffix . '_staff_pending' );
		}
	}

	/** Notify a participant after staff completes a manual assessment review. */
	public static function send_assessment_reviewed( $enrollment_id, $attempt, $review_note ) {
		if ( ! $attempt ) {
			return;
		}
		if ( Parish_Formation_Assessment_Settings::is_acknowledgement_mode( $attempt->assessment_id ) || 'submission' === $attempt->passing_rule ) {
			self::send_participant_event( 'acknowledgement_reviewed', $enrollment_id, array( 'assessment_name' => get_the_title( $attempt->assessment_id ), 'review_note' => $review_note ), 'attempt_' . absint( $attempt->id ) . '_acknowledgement_reviewed' );
			return;
		}
		$maximum   = (float) $attempt->max_points;
		$score      = (float) $attempt->score_points;
		$percentage = $maximum > 0 ? round( ( $score / $maximum ) * 100, 1 ) : 100;
		$values = array( 'assessment_name' => get_the_title( $attempt->assessment_id ), 'assessment_result' => 'passed' === $attempt->status ? __( 'Passed', 'parish-formation' ) : __( 'Not passed', 'parish-formation' ), 'score' => $score . '/' . $maximum . ' (' . $percentage . '%)', 'review_note' => $review_note );
		self::send_participant_event( 'assessment_reviewed', $enrollment_id, $values, 'attempt_' . absint( $attempt->id ) . '_reviewed' );
	}

	/** Notify the participant and configured staff when a course becomes complete. */
	public static function send_course_completed( $enrollment_id ) {
		$enrollment = Parish_Formation_Enrollment_Repository::get_details( absint( $enrollment_id ) );
		if ( ! $enrollment || ! $enrollment->completed_at ) {
			return;
		}
		$values = array( 'completion_date' => get_date_from_gmt( $enrollment->completed_at, get_option( 'date_format' ) ), 'report_url' => add_query_arg( array( 'page' => 'parish-formation-enrollments', 'enrollment_id' => $enrollment->id ), admin_url( 'admin.php' ) ) );
		$suffix = 'completed_' . sanitize_key( $enrollment->completed_at );
		self::send_participant_event( 'course_completed', $enrollment_id, $values, $suffix );
		self::send_staff_event( 'staff_course_completed', $enrollment_id, $values, $suffix . '_staff' );
	}

	/** Send issuance, replacement, or revocation information for a certificate. */
	public static function send_certificate_event( $type, $certificate, $revocation_reason = '' ) {
		if ( ! $certificate ) {
			return;
		}
		$certificate_url = home_url( '/formation-certificate/' . rawurlencode( $certificate->verification_code ) . '/' );
		$pdf_url = add_query_arg( array( 'action' => 'pf_public_certificate_pdf', 'certificate_code' => $certificate->verification_code ), admin_url( 'admin-post.php' ) );
		$values = array( 'certificate_code' => $certificate->verification_code, 'certificate_url' => $certificate_url, 'certificate_pdf_url' => $pdf_url, 'revocation_reason' => $revocation_reason );
		self::send_participant_event( $type, $certificate->enrollment_id, $values, 'certificate_' . absint( $certificate->id ) );
	}

	/** Ensure the daily reminder worker is scheduled. */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'pf_daily_notification_events' ) ) {
			wp_schedule_event( time() + 300, 'daily', 'pf_daily_notification_events' );
		}
	}

	/** Remove scheduled notification work when the plugin is deactivated. */
	public static function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'pf_daily_notification_events' );
	}

	/** Send due expiration reminders and one-time expiration notices. */
	public static function process_expiration_notifications() {
		global $wpdb;
		$now         = current_time( 'mysql', true );
		$enrollments = $wpdb->get_results( $wpdb->prepare( "SELECT id, expires_at FROM {$wpdb->prefix}pf_enrollments WHERE status IN ('enrolled','in_progress') AND expires_at IS NOT NULL AND expires_at <= %s", gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC +365 days' ) ) ) );
		$reminders   = array_map( 'absint', preg_split( '/[\s,;]+/', self::settings()['reminder_days'] ) );
		$today       = strtotime( gmdate( 'Y-m-d', strtotime( $now . ' UTC' ) ) );
		foreach ( $enrollments as $enrollment ) {
			$expiration_day = strtotime( gmdate( 'Y-m-d', strtotime( $enrollment->expires_at . ' UTC' ) ) );
			$days_remaining = (int) round( ( $expiration_day - $today ) / DAY_IN_SECONDS );
			if ( strtotime( $enrollment->expires_at . ' UTC' ) <= strtotime( $now . ' UTC' ) ) {
				self::send_participant_event( 'course_expired', $enrollment->id, array(), 'expired_' . gmdate( 'Ymd', strtotime( $enrollment->expires_at . ' UTC' ) ) );
			} elseif ( in_array( $days_remaining, $reminders, true ) ) {
				self::send_participant_event( 'enrollment_expiration_reminder', $enrollment->id, array(), 'reminder_' . $days_remaining . '_' . gmdate( 'Ymd', strtotime( $enrollment->expires_at . ' UTC' ) ) );
			}
		}
	}

	/** Retry a previously failed delivery using its immutable rendered content. */
	public static function retry( $log_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pf_notification_log';
		$log   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $log_id ) ) );
		if ( ! $log || 'failed' !== $log->status || ! $log->message_body ) {
			return new WP_Error( 'notification_not_retryable', __( 'This email cannot be retried.', 'parish-formation' ) );
		}
		$settings = self::settings();
		$headers  = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>', 'Reply-To: ' . $settings['reply_to'] );
		$sent     = wp_mail( $log->recipient, $log->subject, $log->message_body, $headers );
		$wpdb->update( $table, array( 'status' => $sent ? 'sent' : 'failed', 'message_body' => $sent ? null : $log->message_body, 'error_message' => $sent ? null : __( 'WordPress could not hand the retried message to the configured mail service.', 'parish-formation' ), 'sent_at' => $sent ? current_time( 'mysql', true ) : null ), array( 'id' => absint( $log_id ) ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
		return $sent;
	}

	/** Replace WordPress's user-facing new-account email for every role. */
	public static function filter_new_user_email( $email, $user, $blogname ) {
		$settings = self::settings();
		if ( empty( $settings['enabled']['new_user_registration'] ) ) {
			return $email;
		}
		$setup_url = '';
		foreach ( wp_extract_urls( wp_strip_all_tags( $email['message'] ) ) as $url ) {
			$url = rtrim( $url, "<>\r\n\t ." );
			if ( false !== strpos( $url, 'action=rp' ) || false !== strpos( $url, 'action=resetpass' ) ) {
				$setup_url = $url;
				break;
			}
		}
		if ( ! $setup_url ) {
			return $email;
		}
		$query = array();
		parse_str( (string) wp_parse_url( html_entity_decode( $setup_url ), PHP_URL_QUERY ), $query );
		if ( ! empty( $query['key'] ) && ! empty( $query['login'] ) ) { $setup_url = Parish_Formation_Account_Shortcodes::get_password_reset_url( sanitize_text_field( $query['key'] ), sanitize_user( $query['login'], true ) ); }
		$content = self::resolve_template( 'new_user_registration', array( 'participant_name' => $user->display_name ?: $user->user_login, 'username' => $user->user_login, 'site_name' => $blogname, 'login_url' => wp_login_url(), 'password_setup_url' => $setup_url ) );
		$context = 'new_user_' . absint( $user->ID ) . '_' . sanitize_key( $user->user_registered );
		$email['subject'] = sanitize_text_field( wp_strip_all_tags( $content[0] ) );
		$email['message'] = self::preview_html( self::types()['new_user_registration'][1], $content[1] );
		$email['headers'] = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>', 'Reply-To: ' . $settings['reply_to'], 'X-Parish-Formation-Notification: new_user_registration', 'X-Parish-Formation-Context: ' . $context );
		return $email;
	}

	/** Log successful branded core-email delivery. */
	public static function log_wp_mail_succeeded( $mail_data ) {
		self::log_core_mail_result( $mail_data, true, '' );
	}

	/** Log failed branded core-email delivery. */
	public static function log_wp_mail_failed( $error ) {
		$data = $error instanceof WP_Error ? $error->get_error_data() : array();
		self::log_core_mail_result( is_array( $data ) ? $data : array(), false, $error instanceof WP_Error ? $error->get_error_message() : __( 'WordPress could not send the email.', 'parish-formation' ) );
	}

	/** Return validated staff notification recipients. */
	public static function staff_recipients( $course_id = 0 ) {
		$emails = $course_id ? get_post_meta( absint( $course_id ), Parish_Formation_Course_Settings::NOTIFICATION_STAFF_EMAILS_META_KEY, true ) : '';
		if ( ! $emails ) {
			$emails = self::settings()['staff_emails'];
		}
		return array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', $emails ) ) );
	}

	/** Determine whether a globally enabled email is also enabled for a course. */
	public static function is_enabled_for_course( $type, $course_id ) {
		$settings = self::settings();
		if ( empty( $settings['enabled'][ $type ] ) ) {
			return false;
		}
		$disabled = (array) get_post_meta( absint( $course_id ), Parish_Formation_Course_Settings::NOTIFICATION_DISABLED_META_KEY, true );
		return ! in_array( $type, $disabled, true );
	}

	/** Sanitize a comma/newline separated email list. */
	public static function sanitize_email_list( $value ) {
		$emails = array_unique( array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) $value ) ) ) );
		return implode( ', ', $emails );
	}

	/** Normalize reminder offsets to unique descending day values. */
	public static function sanitize_reminder_days( $value ) {
		$days = array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) $value ) ), static function ( $day ) { return $day >= 1 && $day <= 365; } ) );
		rsort( $days, SORT_NUMERIC );
		return implode( ', ', $days );
	}

	/** Persist the result of a branded email sent by WordPress core. */
	private static function log_core_mail_result( $mail_data, $sent, $error_message ) {
		global $wpdb;
		$headers = isset( $mail_data['headers'] ) ? $mail_data['headers'] : array();
		$headers = is_array( $headers ) ? $headers : preg_split( '/\r?\n/', (string) $headers );
		$type    = '';
		$context = '';
		foreach ( $headers as $header ) {
			if ( 0 === stripos( $header, 'X-Parish-Formation-Notification:' ) ) {
				$type = sanitize_key( trim( substr( $header, strlen( 'X-Parish-Formation-Notification:' ) ) ) );
			} elseif ( 0 === stripos( $header, 'X-Parish-Formation-Context:' ) ) {
				$context = sanitize_key( trim( substr( $header, strlen( 'X-Parish-Formation-Context:' ) ) ) );
			}
		}
		if ( ! isset( self::types()[ $type ] ) || ! $context ) {
			return;
		}
		$recipients = isset( $mail_data['to'] ) ? (array) $mail_data['to'] : array();
		foreach ( $recipients as $recipient ) {
			$recipient = sanitize_email( $recipient );
			if ( ! $recipient ) {
				continue;
			}
			$wpdb->replace(
				$wpdb->prefix . 'pf_notification_log',
				array( 'notification_type' => $type, 'recipient' => $recipient, 'subject' => sanitize_text_field( $mail_data['subject'] ?? '' ), 'message_body' => $sent ? null : wp_kses_post( $mail_data['message'] ?? '' ), 'status' => $sent ? 'sent' : 'failed', 'context_key' => $context, 'error_message' => $sent ? null : sanitize_text_field( $error_message ), 'sent_at' => $sent ? current_time( 'mysql', true ) : null, 'created_at' => current_time( 'mysql', true ) ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/** Wrap a notification in a restrained parish-branded HTML layout. */
	private static function render_template( $heading, $message ) {
		$design = self::design();
		$logo   = $design['logo_url'] ? '<img src="' . esc_url( $design['logo_url'] ) . '" alt="" style="display:block;max-height:54px;max-width:220px;margin:0 0 12px">' : '';
		$contact = $design['contact_text'] ? '<br>' . nl2br( esc_html( $design['contact_text'] ) ) : '';
		$message = preg_replace( '/<a\s/i', '<a style="color:' . esc_attr( $design['link_color'] ) . '" ', wp_kses_post( wpautop( $message ) ) );
		return '<!doctype html><html><body style="margin:0;background:' . esc_attr( $design['page_color'] ) . ';font-family:Arial,sans-serif;color:' . esc_attr( $design['text_color'] ) . '"><div style="max-width:' . absint( $design['container_width'] ) . 'px;margin:24px auto;background:' . esc_attr( $design['content_color'] ) . ';border:1px solid #dcdcde"><div style="padding:22px 28px;background:' . esc_attr( $design['header_color'] ) . ';color:' . esc_attr( $design['header_text_color'] ) . ';font-size:20px;font-weight:700">' . $logo . esc_html( $design['header_name'] ) . '</div><div style="padding:30px 28px;background:' . esc_attr( $design['content_color'] ) . '"><h1 style="margin:0 0 20px;font-size:26px;color:' . esc_attr( $design['text_color'] ) . '">' . esc_html( $heading ) . '</h1><div style="font-size:16px;line-height:1.6;color:' . esc_attr( $design['text_color'] ) . '">' . $message . '</div></div><div style="padding:18px 28px;background:' . esc_attr( $design['footer_color'] ) . ';color:#646970;font-size:13px">' . esc_html( $design['footer_text'] ) . $contact . '</div></div></body></html>';
	}
}
