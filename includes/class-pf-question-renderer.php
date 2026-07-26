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
		} elseif ( 'fill_blank' === $type ) {
			$blanks = $config['type_config']['blanks'] ?? array();
			$segments = preg_split( '/\[blank\]/i', wp_kses_post( $question->post_content ) );
			?><div class="pf-fill-blank-response"><?php
			foreach ( $segments as $index => $segment ) {
				echo wp_kses_post( $segment );
				if ( isset( $blanks[ $index ] ) ) {
					$blank = $blanks[ $index ];
					?><label class="pf-fill-blank-field"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Blank %d', 'parish-formation' ), $index + 1 ) ); ?></span><input class="uk-input" type="text" name="<?php echo esc_attr( $field_name . '[' . $blank['id'] . ']' ); ?>" autocomplete="off"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?> /></label><?php
				}
			}
			?></div><?php
		} elseif ( 'short_answer' === $type ) {
			?>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Your answer', 'parish-formation' ); ?></span><input class="uk-input" type="text" name="<?php echo esc_attr( $field_name ); ?>" autocomplete="off"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?> /></label>
			<?php
		} elseif ( 'matching' === $type ) {
			$pairs = $config['type_config']['pairs'] ?? array();
			$answers = self::randomized_order( $pairs, 'answer_id' );
			?><div class="pf-matching-response">
				<p class="pf-question-response-instruction"><?php esc_html_e( 'Choose the matching answer for each item.', 'parish-formation' ); ?></p>
				<?php foreach ( $pairs as $index => $pair ) { ?>
					<div class="pf-matching-row">
						<label for="pf-match-<?php echo esc_attr( $question->ID . '-' . $pair['id'] ); ?>"><?php echo esc_html( $pair['prompt'] ); ?></label>
						<select class="uk-select" id="pf-match-<?php echo esc_attr( $question->ID . '-' . $pair['id'] ); ?>" name="<?php echo esc_attr( $field_name . '[' . $pair['id'] . ']' ); ?>"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
							<option value=""><?php esc_html_e( 'Select a match', 'parish-formation' ); ?></option>
							<?php foreach ( $answers as $answer ) { ?><option value="<?php echo esc_attr( $answer['answer_id'] ); ?>"><?php echo esc_html( $answer['answer'] ); ?></option><?php } ?>
						</select>
					</div>
				<?php } ?>
			</div><?php
		} elseif ( 'ordering' === $type ) {
			$items = $config['type_config']['items'] ?? array();
			$items = self::randomized_order( $items, 'id' );
			?><div class="pf-ordering-response">
				<p class="pf-question-response-instruction"><?php esc_html_e( 'Drag the items into the correct order. Keyboard users can focus an item and press the Up or Down arrow key.', 'parish-formation' ); ?></p>
				<ol class="pf-ordering-list" aria-label="<?php esc_attr_e( 'Items to arrange', 'parish-formation' ); ?>">
					<?php foreach ( $items as $item ) { ?><li class="pf-ordering-item"<?php echo $disabled ? '' : ' draggable="true" tabindex="0"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[]" value="<?php echo esc_attr( $item['id'] ); ?>" />
						<?php if ( ! $disabled ) { ?><span class="pf-ordering-drag-handle" aria-hidden="true">&#x2630;</span><?php } ?>
						<span class="pf-ordering-label"><?php echo esc_html( $item['label'] ); ?></span>
					</li><?php } ?>
				</ol>
				<?php if ( $disabled ) { ?><p class="pf-ordering-closed-notice"><?php esc_html_e( 'This submitted order can no longer be changed.', 'parish-formation' ); ?></p><?php } else { ?><p class="screen-reader-text pf-ordering-status" aria-live="polite"></p><?php } ?>
			</div><?php
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
		if ( $config['randomize_choices'] ) { $choices = self::randomized_order( $choices, 'id' ); }
		return $choices;
	}

	/** Randomize while guaranteeing a visibly different order when possible. */
	private static function randomized_order( $items, $id_key ) {
		if ( count( $items ) < 2 ) { return $items; }
		$original_ids = array_column( $items, $id_key );
		shuffle( $items );
		if ( $original_ids === array_column( $items, $id_key ) ) {
			$first = array_shift( $items );
			$items[] = $first;
		}
		return $items;
	}
}
