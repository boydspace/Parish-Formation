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
			$checkbox_label = $config['type_config']['checkbox_label'] ?? __( 'I acknowledge this statement.', 'parish-formation' );
			$policy_url = $config['type_config']['policy_url'] ?? '';
			$require_open = ! empty( $config['type_config']['require_policy_open'] ) && $policy_url;
			?>
			<div class="pf-acknowledgement-response">
				<?php if ( $policy_url ) { ?><p><a class="pf-acknowledgement-policy-link" href="<?php echo esc_url( $policy_url ); ?>" target="_blank" rel="noopener noreferrer" data-acknowledgement-target="<?php echo esc_attr( $question->ID ); ?>"><?php esc_html_e( 'Open the referenced policy or document', 'parish-formation' ); ?></a></p><?php } ?>
				<input type="hidden" class="pf-acknowledgement-policy-opened" name="<?php echo esc_attr( $field_name . '[policy_opened]' ); ?>" value="0" data-question-id="<?php echo esc_attr( $question->ID ); ?>" />
				<label class="pf-assessment-option"><input class="uk-checkbox pf-acknowledgement-checkbox" type="checkbox" name="<?php echo esc_attr( $field_name . '[acknowledged]' ); ?>" value="acknowledged"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $require_open && ! $disabled ? ' disabled data-policy-required="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>> <?php echo esc_html( $checkbox_label ); ?></label>
				<?php if ( $require_open && ! $disabled ) { ?><p class="description pf-acknowledgement-open-notice"><?php esc_html_e( 'Open the linked item before checking the acknowledgment.', 'parish-formation' ); ?></p><?php } ?>
			</div>
			<?php
		} elseif ( 'file_upload' === $type ) {
			$settings = $config['type_config'];
			$accept = implode( ',', array_map( static fn( $extension ) => '.' . $extension, $settings['allowed_extensions'] ?? array() ) );
			if ( ! empty( $settings['submission_instructions'] ) ) { ?><div class="pf-file-upload-instructions"><?php echo wp_kses_post( $settings['submission_instructions'] ); ?></div><?php }
			?><div class="pf-file-upload-response" data-question-id="<?php echo esc_attr( $question->ID ); ?>" data-min-files="<?php echo esc_attr( $settings['minimum_files'] ); ?>" data-max-files="<?php echo esc_attr( $settings['maximum_files'] ); ?>" data-max-size="<?php echo esc_attr( $settings['max_file_size'] ); ?>">
				<label><span><?php esc_html_e( 'Choose file(s)', 'parish-formation' ); ?></span><input class="pf-assessment-file-input" type="file" accept="<?php echo esc_attr( $accept ); ?>"<?php echo 1 < $settings['maximum_files'] ? ' multiple' : ''; ?><?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> /></label>
				<p class="description"><?php echo esc_html( sprintf( __( 'Allowed: %1$s. Maximum %2$s per file. Upload %3$d–%4$d file(s).', 'parish-formation' ), implode( ', ', $settings['allowed_extensions'] ), size_format( $settings['max_file_size'] ), $settings['minimum_files'], $settings['maximum_files'] ) ); ?></p>
				<div class="pf-file-upload-status" aria-live="polite"></div>
			</div><?php
		} elseif ( 'numeric' === $type ) {
			$settings = $config['type_config'];
			$unit = $settings['unit_label'] ?? '';
			$precision = (int) ( $settings['decimal_precision'] ?? 2 );
			$step = ! empty( $settings['integer_only'] ) || 0 === $precision ? '1' : ( '0.' . str_repeat( '0', max( 0, $precision - 1 ) ) . '1' );
			?><label class="pf-numeric-response"><span class="screen-reader-text"><?php esc_html_e( 'Numeric response', 'parish-formation' ); ?></span><input class="uk-input" type="text" inputmode="decimal" name="<?php echo esc_attr( $field_name ); ?>" data-numeric-step="<?php echo esc_attr( $step ); ?>" autocomplete="off"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> /><?php if ( $unit ) { ?><span class="pf-numeric-response__unit"><?php echo esc_html( $unit ); ?><?php echo ! empty( $settings['require_unit'] ) ? ' ' . esc_html__( '(include this unit in your answer)', 'parish-formation' ) : ''; ?></span><?php } ?></label><?php
		} elseif ( 'image_selection' === $type ) {
			$images = $config['type_config']['images'] ?? array();
			if ( $config['randomize_choices'] ) { $images = self::randomized_order( $images, 'id' ); }
			$multiple = 'multiple' === ( $config['type_config']['selection_mode'] ?? 'single' );
			?><fieldset class="pf-image-selection"<?php echo $config['required'] ? ' aria-required="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<legend class="pf-question-response-instruction"><?php echo esc_html( $multiple ? __( 'Select all that apply.', 'parish-formation' ) : __( 'Select one image.', 'parish-formation' ) ); ?></legend>
				<div class="pf-image-selection__grid">
				<?php foreach ( $images as $index => $image ) { ?>
					<label class="pf-image-selection__choice">
						<input class="<?php echo $multiple ? 'uk-checkbox' : 'uk-radio'; ?>" type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>" name="<?php echo esc_attr( $field_name . ( $multiple ? '[]' : '' ) ); ?>" value="<?php echo esc_attr( $image['id'] ); ?>"<?php echo ! $multiple && 0 === $index ? $control_attrs : $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
						<?php echo wp_get_attachment_image( $image['attachment_id'], 'medium', false, array( 'alt' => $image['alt'], 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="pf-image-selection__label"><?php echo esc_html( $image['label'] ?: $image['alt'] ); ?></span>
					</label>
				<?php } ?>
				</div>
			</fieldset><?php
		} elseif ( 'yes_no' === $type ) {
			$yes_label = $config['type_config']['yes_label'] ?? __( 'Yes', 'parish-formation' );
			$no_label = $config['type_config']['no_label'] ?? __( 'No', 'parish-formation' );
			?>
			<fieldset class="pf-yes-no-response"<?php echo $config['required'] ? ' aria-required="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<legend class="screen-reader-text"><?php esc_html_e( 'Choose one response', 'parish-formation' ); ?></legend>
				<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="yes"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> /> <?php echo esc_html( $yes_label ); ?></label>
				<label class="pf-assessment-option"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name ); ?>" value="no"<?php echo $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> /> <?php echo esc_html( $no_label ); ?></label>
			</fieldset><?php
		} elseif ( 'rating_scale' === $type ) {
			$minimum = (int) ( $config['type_config']['minimum'] ?? 1 );
			$maximum = (int) ( $config['type_config']['maximum'] ?? 5 );
			$labels = $config['type_config']['value_labels'] ?? array();
			$orientation = $config['type_config']['orientation'] ?? 'horizontal';
			?><fieldset class="pf-rating-scale pf-rating-scale--<?php echo esc_attr( $orientation ); ?>"<?php echo $config['required'] ? ' aria-required="true"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<legend class="screen-reader-text"><?php esc_html_e( 'Choose a rating', 'parish-formation' ); ?></legend>
				<div class="pf-rating-scale__choices">
				<?php for ( $value = $minimum; $value <= $maximum; ++$value ) {
					$label = $labels[ $value ] ?? ( $value === $minimum ? ( $config['type_config']['first_label'] ?? '' ) : ( $value === $maximum ? ( $config['type_config']['last_label'] ?? '' ) : '' ) ); ?>
					<label class="pf-rating-scale__choice"><input class="uk-radio" type="radio" name="<?php echo esc_attr( $field_name . '[value]' ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo  $value === $minimum ? $control_attrs : $disabled_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> /> <span class="pf-rating-scale__value"><?php echo esc_html( $value ); ?></span><?php if ( '' !== $label ) { ?><span class="pf-rating-scale__label"><?php echo esc_html( $label ); ?></span><?php } ?></label>
				<?php } ?>
				</div>
			</fieldset><?php
		} elseif ( 'reflection' === $type ) {
			$minimum = absint( $config['type_config']['minimum_characters'] ?? 0 );
			$maximum = absint( $config['type_config']['maximum_characters'] ?? 0 );
			$counter_id = 'pf-reflection-count-' . absint( $question->ID );
			if ( ! empty( $config['type_config']['private_notice'] ) ) { ?><div class="pf-reflection-private-notice uk-alert uk-alert-primary"><?php echo wp_kses_post( $config['type_config']['private_notice'] ); ?></div><?php }
			if ( ! empty( $config['type_config']['sample_prompt'] ) ) { ?><div class="pf-reflection-sample"><strong><?php esc_html_e( 'Reflection example:', 'parish-formation' ); ?></strong> <?php echo wp_kses_post( $config['type_config']['sample_prompt'] ); ?></div><?php }
			?><label><span class="screen-reader-text"><?php esc_html_e( 'Your reflection', 'parish-formation' ); ?></span><textarea class="uk-textarea pf-reflection-response" name="<?php echo esc_attr( $field_name ); ?>" rows="7" data-min-characters="<?php echo esc_attr( $minimum ); ?>" data-max-characters="<?php echo esc_attr( $maximum ); ?>" aria-describedby="<?php echo esc_attr( $counter_id ); ?>" placeholder="<?php esc_attr_e( 'Enter your reflection...', 'parish-formation' ); ?>"<?php echo $control_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></textarea></label>
			<p id="<?php echo esc_attr( $counter_id ); ?>" class="pf-reflection-character-count" aria-live="polite"><?php echo esc_html( $minimum ? sprintf( __( '0 non-space characters entered; %d more required.', 'parish-formation' ), $minimum ) : __( '0 non-space characters entered.', 'parish-formation' ) ); ?></p><?php
			if ( $minimum || $maximum ) { ?><p class="description pf-reflection-length-guidance"><?php echo esc_html( $minimum && $maximum ? sprintf( __( 'Use between %1$d and %2$d non-space characters.', 'parish-formation' ), $minimum, $maximum ) : ( $minimum ? sprintf( __( 'Use at least %d non-space characters.', 'parish-formation' ), $minimum ) : sprintf( __( 'Use no more than %d non-space characters.', 'parish-formation' ), $maximum ) ) ); ?></p><?php }
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
