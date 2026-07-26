<?php
/** Accessible learner rendering for registered question types. */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Parish_Formation_Question_Renderer {
	public static function render( $question, $field_name, $disabled = false ) {
		$config = Parish_Formation_Question_Config::get( $question->ID );
		$type   = $config['type'];
		$required = $config['required'] ? ' required' : '';
		$disabled_attr = $disabled ? ' disabled' : '';
		ob_start();
		if ( $config['instructions'] ) : ?><div class="pf-question-instructions"><?php echo wp_kses_post( $config['instructions'] ); ?></div><?php endif;
		if ( 'multiple_choice' === $type ) :
			foreach ( $config['choices'] as $index => $choice ) : ?>
				<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $index + 1 ); ?>"<?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>> <?php echo esc_html( $choice['label'] ); ?></label>
			<?php endforeach;
		elseif ( 'true_false' === $type ) : ?>
			<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="true"<?php echo $disabled_attr . $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>> <?php esc_html_e( 'True', 'parish-formation' ); ?></label>
			<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="false"<?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>> <?php esc_html_e( 'False', 'parish-formation' ); ?></label>
		elseif ( 'acknowledgement' === $type ) : ?>
			<label class="pf-assessment-option"><input class="uk-checkbox" type="checkbox" name="<?php echo esc_attr( $field_name ); ?>" value="acknowledged"<?php echo $disabled_attr . $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>> <?php esc_html_e( 'I acknowledge this statement.', 'parish-formation' ); ?></label>
		else : ?>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Your response', 'parish-formation' ); ?></span><textarea class="uk-textarea" name="<?php echo esc_attr( $field_name ); ?>" rows="6" placeholder="<?php esc_attr_e( 'Enter your response…', 'parish-formation' ); ?>"<?php echo $disabled_attr . $required; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></textarea></label>
		<?php endif;
		return (string) ob_get_clean();
	}
}
