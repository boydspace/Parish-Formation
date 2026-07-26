<?php
/** Accessible learner rendering for registered question types. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Parish_Formation_Question_Renderer {
	/** Render one question response control without exposing its grading key. */
	public static function render( $question, $field_name, $disabled = false ) {
		$config         = Parish_Formation_Question_Config::get( $question->ID );
		$type           = $config['type'];
		$required_attr  = $config['required'] ? ' required' : '';
		$disabled_attr  = $disabled ? ' disabled' : '';
		$control_attrs  = $disabled_attr . $required_attr;

		ob_start();
		if ( $config['instructions'] ) {
			?><div class="pf-question-instructions"><?php echo wp_kses_post( $config['instructions'] ); ?></div><?php
		}

		if ( 'multiple_choice' === $type ) {
			$choices = self::display_choices( $config );
			foreach ( $choices as $index => $choice ) {
				?>
				<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( str_starts_with( $choice['id'], 'legacy-choice-' ) ? substr( $choice['id'], 14 ) : $choice['id'] ); ?>"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>> <?php echo esc_html( $choice['label'] ); ?></label>
				<?php
			}
		} elseif ( 'multiple_select' === $type ) {
			$choices = self::display_choices( $config );
			?>
			<fieldset class="pf-question-multiple-select"<?php echo $config['required'] ? ' aria-required="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<legend class="pf-question-response-instruction"><?php esc_html_e( 'Select all that apply.', 'parish-formation' ); ?></legend>
				<?php foreach ( $choices as $choice ) { ?>
					<label class="pf-assessment-option"><input class="uk-checkbox" type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[]" value="<?php echo esc_attr( $choice['id'] ); ?>"<?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>> <?php echo esc_html( $choice['label'] ); ?></label>
				<?php } ?>
			</fieldset>
			<?php
		} elseif ( 'true_false' === $type ) {
			?>
			<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="true"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>> <?php esc_html_e( 'True', 'parish-formation' ); ?></label>
			<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="false"<?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>> <?php esc_html_e( 'False', 'parish-formation' ); ?></label>
			<?php
		} elseif ( 'short_answer' === $type ) {
			?>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Your answer', 'parish-formation' ); ?></span><input class="uk-input" type="text" name="<?php echo esc_attr( $field_name ); ?>" autocomplete="off"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?> /></label>
			<?php
		} elseif ( 'acknowledgement' === $type ) {
			?>
			<label class="pf-assessment-option"><input class="uk-checkbox" type="checkbox" name="<?php echo esc_attr( $field_name ); ?>" value="acknowledged"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>> <?php esc_html_e( 'I acknowledge this statement.', 'parish-formation' ); ?></label>
			<?php
		} else {
			?>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Your response', 'parish-formation' ); ?></span><textarea class="uk-textarea" name="<?php echo esc_attr( $field_name ); ?>" rows="6" placeholder="<?php esc_attr_e( 'Enter your response…', 'parish-formation' ); ?>"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>></textarea></label>
			<?php
		}

		return (string) ob_get_clean();
	}

	private static function display_choices( $config ) {
		$choices = $config['choices'];
		if ( $config['randomize_choices'] && count( $choices ) > 1 ) { shuffle( $choices ); }
		return $choices;
	}
}
