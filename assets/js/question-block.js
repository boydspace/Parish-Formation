( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	const el = element.createElement;
	const __ = i18n.__;
	const InspectorControls = blockEditor.InspectorControls;
	const RichText = blockEditor.RichText;
	const PanelBody = components.PanelBody;
	const SelectControl = components.SelectControl;
	const TextareaControl = components.TextareaControl;
	const TextControl = components.TextControl;
	const ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'parish-formation/question', {
		title: __( 'Assessment Question', 'parish-formation' ),
		description: __( 'Add a graded or reviewed question to an assessment.', 'parish-formation' ),
		icon: 'editor-help',
		category: 'widgets',
		keywords: [ __( 'question', 'parish-formation' ), __( 'assessment', 'parish-formation' ), __( 'quiz', 'parish-formation' ) ],
		attributes: {
			questionId: { type: 'integer', default: 0 },
			prompt: { type: 'string', default: '' },
			type: { type: 'string', default: 'multiple_choice' },
			options: { type: 'array', default: [] },
			answer: { type: 'string', default: '' },
			points: { type: 'integer', default: 1 },
			required: { type: 'boolean', default: true },
			instructions: { type: 'string', default: '' },
			graded: { type: 'boolean', default: true },
			explanation: { type: 'string', default: '' },
			correctFeedback: { type: 'string', default: '' },
			incorrectFeedback: { type: 'string', default: '' },
			feedbackTiming: { type: 'string', default: 'assessment' },
			manualReview: { type: 'boolean', default: false },
			adminNotes: { type: 'string', default: '' }
		},
		supports: { html: false, reusable: false },
		edit: function ( props ) {
			const attrs = props.attributes;
			const isAutomaticallyGraded = [ 'multiple_choice', 'true_false' ].includes( attrs.type );
			const supportsCorrectFeedback = isAutomaticallyGraded;
			const optionsText = ( attrs.options || [] ).join( '\n' );
			const normalizedAnswer = /^\d+$/.test( attrs.answer ) ? attrs.answer : String( ( attrs.options || [] ).findIndex( function ( option ) { return option.toLowerCase() === attrs.answer.toLowerCase(); } ) + 1 || '' );
			const answerOptions = [ { label: __( 'Select the correct answer', 'parish-formation' ), value: '' } ].concat( ( attrs.options || [] ).filter( Boolean ).map( function ( option, index ) { return { label: option, value: String( index + 1 ) }; } ) );
			return el( 'div', blockEditor.useBlockProps( { className: 'pf-question-block' } ),
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Question Settings', 'parish-formation' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Question type', 'parish-formation' ), value: attrs.type,
							options: [
								{ label: __( '— Automatically Graded —', 'parish-formation' ), value: '__automatic', disabled: true },
								{ label: __( 'Multiple choice', 'parish-formation' ), value: 'multiple_choice' },
								{ label: __( 'Multiple Select (coming in Phase 2)', 'parish-formation' ), value: '__multiple_select', disabled: true },
								{ label: __( 'True / False', 'parish-formation' ), value: 'true_false' },
								{ label: __( 'Short Answer (coming in Phase 2)', 'parish-formation' ), value: '__short_answer', disabled: true },
								{ label: __( 'Fill in the Blank (coming in Phase 2)', 'parish-formation' ), value: '__fill_blank', disabled: true },
								{ label: __( 'Matching (coming in Phase 2)', 'parish-formation' ), value: '__matching', disabled: true },
								{ label: __( 'Ordering (coming in Phase 2)', 'parish-formation' ), value: '__ordering', disabled: true },
								{ label: __( 'Numeric Response (coming in Phase 3)', 'parish-formation' ), value: '__numeric', disabled: true },
								{ label: __( '— Instructor Reviewed —', 'parish-formation' ), value: '__review', disabled: true },
								{ label: __( 'Paragraph Response (coming in Phase 3)', 'parish-formation' ), value: '__paragraph', disabled: true },
								{ label: __( 'File Upload (coming in Phase 3)', 'parish-formation' ), value: '__file_upload', disabled: true },
								{ label: __( '— Formation and Feedback —', 'parish-formation' ), value: '__formation', disabled: true },
								{ label: __( 'Reflection Response', 'parish-formation' ), value: 'reflection' },
								{ label: __( 'Rating Scale (coming in Phase 3)', 'parish-formation' ), value: '__rating', disabled: true },
								{ label: __( 'Yes / No (coming in Phase 3)', 'parish-formation' ), value: '__yes_no', disabled: true },
								{ label: __( 'Acknowledgment', 'parish-formation' ), value: 'acknowledgement' },
								{ label: __( 'Image Selection (coming in Phase 3)', 'parish-formation' ), value: '__image', disabled: true }
							],
							onChange: function ( value ) {
								if ( value.indexOf( '__' ) === 0 ) { return; }
								props.setAttributes( {
									type: value,
									graded: ! [ 'reflection', 'acknowledgement' ].includes( value ),
									manualReview: value === 'reflection'
								} );
							}
						} ),
						el( TextareaControl, {
							label: __( 'Optional instructions', 'parish-formation' ), value: attrs.instructions,
							onChange: function ( value ) { props.setAttributes( { instructions: value } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Graded question', 'parish-formation' ), checked: attrs.graded,
							onChange: function ( value ) { props.setAttributes( { graded: value } ); }
						} ),
						attrs.graded && el( TextControl, {
							label: __( 'Points', 'parish-formation' ), type: 'number', min: 1, value: attrs.points,
							onChange: function ( value ) { props.setAttributes( { points: Math.max( 1, parseInt( value, 10 ) || 1 ) } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Required question', 'parish-formation' ), checked: attrs.required,
							onChange: function ( value ) { props.setAttributes( { required: value } ); }
						} ),
						( attrs.type === 'reflection' ) && el( ToggleControl, {
							label: __( 'Require staff review', 'parish-formation' ), checked: attrs.manualReview,
							onChange: function ( value ) { props.setAttributes( { manualReview: value } ); }
						} )
					),
					el( PanelBody, { title: __( 'Feedback and Staff Notes', 'parish-formation' ), initialOpen: false },
						el( TextareaControl, {
							label: __( 'Explanation after submission', 'parish-formation' ), value: attrs.explanation,
							onChange: function ( value ) { props.setAttributes( { explanation: value } ); }
						} ),
						supportsCorrectFeedback && el( TextareaControl, {
							label: __( 'Correct-answer feedback', 'parish-formation' ), value: attrs.correctFeedback,
							onChange: function ( value ) { props.setAttributes( { correctFeedback: value } ); }
						} ),
						supportsCorrectFeedback && el( TextareaControl, {
							label: __( 'Incorrect-answer feedback', 'parish-formation' ), value: attrs.incorrectFeedback,
							onChange: function ( value ) { props.setAttributes( { incorrectFeedback: value } ); }
						} ),
						el( SelectControl, {
							label: __( 'Show feedback', 'parish-formation' ), value: attrs.feedbackTiming,
							options: [ { label: __( 'After the assessment', 'parish-formation' ), value: 'assessment' }, { label: __( 'Immediately after submission', 'parish-formation' ), value: 'immediate' } ],
							onChange: function ( value ) { props.setAttributes( { feedbackTiming: value } ); }
						} ),
						el( TextareaControl, {
							label: __( 'Administrative notes', 'parish-formation' ), help: __( 'Visible only to parish staff.', 'parish-formation' ), value: attrs.adminNotes,
							onChange: function ( value ) { props.setAttributes( { adminNotes: value } ); }
						} )
					)
				),
				el( 'div', { className: 'pf-question-block__label' }, __( 'Assessment Question', 'parish-formation' ) ),
				el( RichText, {
					tagName: 'div', className: 'pf-question-block__prompt', value: attrs.prompt,
					placeholder: __( 'Type the question…', 'parish-formation' ),
					onChange: function ( value ) { props.setAttributes( { prompt: value } ); }
				} ),
				attrs.type === 'multiple_choice' && el( TextareaControl, {
					label: __( 'Answer choices (one per line)', 'parish-formation' ), value: optionsText,
					onChange: function ( value ) { props.setAttributes( { options: value.split( /\r?\n/ ) } ); }
				} ),
				attrs.type === 'multiple_choice' && el( SelectControl, {
					label: __( 'Correct answer', 'parish-formation' ), options: answerOptions, value: normalizedAnswer,
					onChange: function ( value ) { props.setAttributes( { answer: value } ); }
				} ),
				attrs.type === 'true_false' && el( SelectControl, {
					label: __( 'Correct answer', 'parish-formation' ), value: attrs.answer,
					options: [ { label: __( 'Select the correct answer', 'parish-formation' ), value: '' }, { label: __( 'True', 'parish-formation' ), value: 'true' }, { label: __( 'False', 'parish-formation' ), value: 'false' } ],
					onChange: function ( value ) { props.setAttributes( { answer: value } ); }
				} ),
				el( 'div', { className: 'pf-question-block__summary' },
					__( 'Type:', 'parish-formation' ) + ' ' + attrs.type.replaceAll( '_', ' ' ) + ' · ' + attrs.points + ' ' + __( 'point(s)', 'parish-formation' )
				)
			);
		},
		save: function () { return null; }
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
