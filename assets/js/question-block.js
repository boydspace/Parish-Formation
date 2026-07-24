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
			required: { type: 'boolean', default: true }
		},
		supports: { html: false, reusable: false },
		edit: function ( props ) {
			const attrs = props.attributes;
			const optionsText = ( attrs.options || [] ).join( '\n' );
			const normalizedAnswer = /^\d+$/.test( attrs.answer ) ? attrs.answer : String( ( attrs.options || [] ).findIndex( function ( option ) { return option.toLowerCase() === attrs.answer.toLowerCase(); } ) + 1 || '' );
			const answerOptions = [ { label: __( 'Select the correct answer', 'parish-formation' ), value: '' } ].concat( ( attrs.options || [] ).filter( Boolean ).map( function ( option, index ) { return { label: option, value: String( index + 1 ) }; } ) );
			return el( 'div', blockEditor.useBlockProps( { className: 'pf-question-block' } ),
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Question Settings', 'parish-formation' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Question type', 'parish-formation' ), value: attrs.type,
							options: [
								{ label: __( 'Multiple choice', 'parish-formation' ), value: 'multiple_choice' },
								{ label: __( 'True / False', 'parish-formation' ), value: 'true_false' },
								{ label: __( 'Acknowledgement', 'parish-formation' ), value: 'acknowledgement' },
								{ label: __( 'Reflection (manual review)', 'parish-formation' ), value: 'reflection' }
							],
							onChange: function ( value ) { props.setAttributes( { type: value } ); }
						} ),
						el( TextControl, {
							label: __( 'Points', 'parish-formation' ), type: 'number', min: 1, value: attrs.points,
							onChange: function ( value ) { props.setAttributes( { points: Math.max( 1, parseInt( value, 10 ) || 1 ) } ); }
						} ),
						el( ToggleControl, {
							label: __( 'Required question', 'parish-formation' ), checked: attrs.required,
							onChange: function ( value ) { props.setAttributes( { required: value } ); }
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
