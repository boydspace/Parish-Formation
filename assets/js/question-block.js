( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	const el = element.createElement;
	const __ = i18n.__;
	const InspectorControls = blockEditor.InspectorControls;
	const RichText = blockEditor.RichText;
	const Button = components.Button;
	const CheckboxControl = components.CheckboxControl;
	const Notice = components.Notice;
	const PanelBody = components.PanelBody;
	const SelectControl = components.SelectControl;
	const TextareaControl = components.TextareaControl;
	const TextControl = components.TextControl;
	const ToggleControl = components.ToggleControl;

	function legacyChoices( attrs ) {
		return ( attrs.options || [] ).filter( Boolean ).map( function ( label, index ) {
			const answer = String( attrs.answer || '' ).toLowerCase();
			return { id: 'choice-' + ( index + 1 ), label: label, correct: answer === String( index + 1 ) || answer === String( label ).toLowerCase(), feedback: '' };
		} );
	}

	blocks.registerBlockType( 'parish-formation/question', {
		title: __( 'Assessment Question', 'parish-formation' ),
		description: __( 'Add a graded, reviewed, or formation question to an assessment.', 'parish-formation' ),
		icon: 'editor-help', category: 'widgets',
		keywords: [ __( 'question', 'parish-formation' ), __( 'assessment', 'parish-formation' ), __( 'quiz', 'parish-formation' ) ],
		attributes: {
			questionId: { type: 'integer', default: 0 }, prompt: { type: 'string', default: '' }, type: { type: 'string', default: 'multiple_choice' },
			options: { type: 'array', default: [] }, choices: { type: 'array', default: [] }, answer: { type: 'string', default: '' }, acceptedAnswers: { type: 'array', default: [] },
			points: { type: 'integer', default: 1 }, required: { type: 'boolean', default: true }, instructions: { type: 'string', default: '' }, graded: { type: 'boolean', default: true },
			explanation: { type: 'string', default: '' }, correctFeedback: { type: 'string', default: '' }, incorrectFeedback: { type: 'string', default: '' }, feedbackTiming: { type: 'string', default: 'assessment' },
			manualReview: { type: 'boolean', default: false }, adminNotes: { type: 'string', default: '' }, randomizeChoices: { type: 'boolean', default: false }, gradingMode: { type: 'string', default: 'all_or_nothing' },
			caseSensitive: { type: 'boolean', default: false }, trimSpaces: { type: 'boolean', default: true }, normalizeSpaces: { type: 'boolean', default: false }, ignorePunctuation: { type: 'boolean', default: false }, matchMode: { type: 'string', default: 'exact' }
		},
		supports: { html: false, reusable: false },
		edit: function ( props ) {
			const attrs = props.attributes;
			const choiceType = [ 'multiple_choice', 'multiple_select' ].includes( attrs.type );
			const choices = ( attrs.choices || [] ).length ? attrs.choices : legacyChoices( attrs );
			const supportsCorrectFeedback = [ 'multiple_choice', 'multiple_select', 'true_false', 'short_answer' ].includes( attrs.type );
			const correctCount = choices.filter( function ( choice ) { return choice.correct; } ).length;
			const choiceError = choiceType && ( choices.length < 2 || correctCount < 1 ) ? __( 'Add at least two choices and mark at least one correct answer.', 'parish-formation' ) : '';

			function saveChoices( next ) {
				props.setAttributes( { choices: next, options: next.map( function ( choice ) { return choice.label; } ), answer: attrs.type === 'multiple_choice' ? ( ( next.find( function ( choice ) { return choice.correct; } ) || {} ).id || '' ) : '' } );
			}
			function updateChoice( index, changes ) {
				let next = choices.map( function ( choice ) { return Object.assign( {}, choice ); } );
				if ( changes.correct && attrs.type === 'multiple_choice' ) { next = next.map( function ( choice ) { return Object.assign( {}, choice, { correct: false } ); } ); }
				next[ index ] = Object.assign( {}, next[ index ], changes ); saveChoices( next );
			}
			function moveChoice( index, direction ) {
				const target = index + direction; if ( target < 0 || target >= choices.length ) { return; }
				const next = choices.slice(); const row = next[ index ]; next[ index ] = next[ target ]; next[ target ] = row; saveChoices( next );
			}
			function addChoice() {
				const id = 'choice-' + props.clientId.replace( /[^a-z0-9]/gi, '' ).slice( 0, 8 ) + '-' + Date.now().toString( 36 );
				saveChoices( choices.concat( [ { id: id, label: '', correct: false, feedback: '' } ] ) );
			}

			return el( 'div', blockEditor.useBlockProps( { className: 'pf-question-block' } ),
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Question Settings', 'parish-formation' ), initialOpen: true },
						el( SelectControl, { label: __( 'Question type', 'parish-formation' ), value: attrs.type, options: [
							{ label: __( '— Automatically Graded —', 'parish-formation' ), value: '__automatic', disabled: true },
							{ label: __( 'Multiple Choice', 'parish-formation' ), value: 'multiple_choice' }, { label: __( 'Multiple Select', 'parish-formation' ), value: 'multiple_select' }, { label: __( 'True / False', 'parish-formation' ), value: 'true_false' }, { label: __( 'Short Answer', 'parish-formation' ), value: 'short_answer' },
							{ label: __( 'Fill in the Blank (coming next)', 'parish-formation' ), value: '__fill_blank', disabled: true }, { label: __( 'Matching (coming next)', 'parish-formation' ), value: '__matching', disabled: true }, { label: __( 'Ordering (coming next)', 'parish-formation' ), value: '__ordering', disabled: true }, { label: __( 'Numeric Response (Phase 3)', 'parish-formation' ), value: '__numeric', disabled: true },
							{ label: __( '— Instructor Reviewed —', 'parish-formation' ), value: '__review', disabled: true }, { label: __( 'Paragraph Response (Phase 3)', 'parish-formation' ), value: '__paragraph', disabled: true }, { label: __( 'File Upload (Phase 3)', 'parish-formation' ), value: '__file_upload', disabled: true },
							{ label: __( '— Formation and Feedback —', 'parish-formation' ), value: '__formation', disabled: true }, { label: __( 'Reflection Response', 'parish-formation' ), value: 'reflection' }, { label: __( 'Rating Scale (Phase 3)', 'parish-formation' ), value: '__rating', disabled: true }, { label: __( 'Yes / No (Phase 3)', 'parish-formation' ), value: '__yes_no', disabled: true }, { label: __( 'Acknowledgment', 'parish-formation' ), value: 'acknowledgement' }, { label: __( 'Image Selection (Phase 3)', 'parish-formation' ), value: '__image', disabled: true }
						], onChange: function ( value ) { if ( value.indexOf( '__' ) === 0 ) { return; } props.setAttributes( { type: value, graded: ! [ 'reflection', 'acknowledgement' ].includes( value ), manualReview: value === 'reflection' } ); } } ),
						el( TextareaControl, { label: __( 'Optional instructions', 'parish-formation' ), value: attrs.instructions, onChange: function ( value ) { props.setAttributes( { instructions: value } ); } } ),
						el( ToggleControl, { label: __( 'Graded question', 'parish-formation' ), checked: attrs.graded, onChange: function ( value ) { props.setAttributes( { graded: value } ); } } ),
						attrs.graded && el( TextControl, { label: __( 'Points', 'parish-formation' ), type: 'number', min: 1, value: attrs.points, onChange: function ( value ) { props.setAttributes( { points: Math.max( 1, parseInt( value, 10 ) || 1 ) } ); } } ),
						el( ToggleControl, { label: __( 'Required question', 'parish-formation' ), checked: attrs.required, onChange: function ( value ) { props.setAttributes( { required: value } ); } } ),
						choiceType && el( ToggleControl, { label: __( 'Randomize answer choices', 'parish-formation' ), checked: attrs.randomizeChoices, onChange: function ( value ) { props.setAttributes( { randomizeChoices: value } ); } } ),
						attrs.type === 'reflection' && el( ToggleControl, { label: __( 'Require staff review', 'parish-formation' ), checked: attrs.manualReview, onChange: function ( value ) { props.setAttributes( { manualReview: value } ); } } )
					),
					el( PanelBody, { title: __( 'Feedback and Staff Notes', 'parish-formation' ), initialOpen: false },
						el( TextareaControl, { label: __( 'Explanation after submission', 'parish-formation' ), value: attrs.explanation, onChange: function ( value ) { props.setAttributes( { explanation: value } ); } } ),
						supportsCorrectFeedback && el( TextareaControl, { label: __( 'Correct-answer feedback', 'parish-formation' ), value: attrs.correctFeedback, onChange: function ( value ) { props.setAttributes( { correctFeedback: value } ); } } ),
						supportsCorrectFeedback && el( TextareaControl, { label: __( 'Incorrect-answer feedback', 'parish-formation' ), value: attrs.incorrectFeedback, onChange: function ( value ) { props.setAttributes( { incorrectFeedback: value } ); } } ),
						el( SelectControl, { label: __( 'Show feedback', 'parish-formation' ), value: attrs.feedbackTiming, options: [ { label: __( 'After the assessment', 'parish-formation' ), value: 'assessment' }, { label: __( 'Immediately after submission', 'parish-formation' ), value: 'immediate' } ], onChange: function ( value ) { props.setAttributes( { feedbackTiming: value } ); } } ),
						el( TextareaControl, { label: __( 'Administrative notes', 'parish-formation' ), help: __( 'Visible only to parish staff.', 'parish-formation' ), value: attrs.adminNotes, onChange: function ( value ) { props.setAttributes( { adminNotes: value } ); } } )
					)
				),
				el( 'div', { className: 'pf-question-block__label' }, __( 'Assessment Question', 'parish-formation' ) ),
				el( RichText, { tagName: 'div', className: 'pf-question-block__prompt', value: attrs.prompt, placeholder: __( 'Type the question…', 'parish-formation' ), onChange: function ( value ) { props.setAttributes( { prompt: value } ); } } ),
				choiceType && el( 'div', { className: 'pf-question-choices' },
					choiceError && el( Notice, { status: 'warning', isDismissible: false }, choiceError ),
					choices.map( function ( choice, index ) { return el( 'div', { className: 'pf-question-choice-row', key: choice.id },
						el( TextControl, { label: __( 'Answer text', 'parish-formation' ), value: choice.label, onChange: function ( value ) { updateChoice( index, { label: value } ); } } ),
						el( CheckboxControl, { label: __( 'Correct answer', 'parish-formation' ), checked: !! choice.correct, onChange: function ( value ) { updateChoice( index, { correct: value } ); } } ),
						el( TextControl, { label: __( 'Answer-specific feedback (optional)', 'parish-formation' ), value: choice.feedback || '', onChange: function ( value ) { updateChoice( index, { feedback: value } ); } } ),
						el( 'div', { className: 'pf-question-choice-actions' },
							el( Button, { variant: 'secondary', disabled: index === 0, onClick: function () { moveChoice( index, -1 ); } }, __( 'Move up', 'parish-formation' ) ),
							el( Button, { variant: 'secondary', disabled: index === choices.length - 1, onClick: function () { moveChoice( index, 1 ); } }, __( 'Move down', 'parish-formation' ) ),
							el( Button, { isDestructive: true, onClick: function () { saveChoices( choices.filter( function ( unused, rowIndex ) { return rowIndex !== index; } ) ); } }, __( 'Remove', 'parish-formation' ) )
						)
					); } ),
					el( Button, { variant: 'primary', onClick: addChoice }, __( 'Add answer choice', 'parish-formation' ) ),
					attrs.type === 'multiple_select' && el( SelectControl, { label: __( 'Grading mode', 'parish-formation' ), value: attrs.gradingMode, options: [ { label: __( 'All or nothing', 'parish-formation' ), value: 'all_or_nothing' }, { label: __( 'Partial credit', 'parish-formation' ), value: 'partial' }, { label: __( 'Partial credit with incorrect-selection penalty', 'parish-formation' ), value: 'partial_penalty' } ], onChange: function ( value ) { props.setAttributes( { gradingMode: value } ); } } )
				),
				attrs.type === 'true_false' && el( SelectControl, { label: __( 'Correct answer', 'parish-formation' ), value: attrs.answer, options: [ { label: __( 'Select the correct answer', 'parish-formation' ), value: '' }, { label: __( 'True', 'parish-formation' ), value: 'true' }, { label: __( 'False', 'parish-formation' ), value: 'false' } ], onChange: function ( value ) { props.setAttributes( { answer: value } ); } } ),
				attrs.type === 'short_answer' && el( 'div', { className: 'pf-question-short-answer-settings' },
					attrs.graded && el( TextareaControl, { label: __( 'Accepted answers (one per line)', 'parish-formation' ), value: ( attrs.acceptedAnswers || [] ).join( '\n' ), help: __( 'The learner response is stored exactly as entered.', 'parish-formation' ), onChange: function ( value ) { props.setAttributes( { acceptedAnswers: value.split( /\r?\n/ ) } ); } } ),
					el( SelectControl, { label: __( 'Matching method', 'parish-formation' ), value: attrs.matchMode, options: [ { label: __( 'Exact normalized match', 'parish-formation' ), value: 'exact' }, { label: __( 'Response contains an accepted answer', 'parish-formation' ), value: 'contains' } ], onChange: function ( value ) { props.setAttributes( { matchMode: value } ); } } ),
					el( ToggleControl, { label: __( 'Case-sensitive comparison', 'parish-formation' ), checked: attrs.caseSensitive, onChange: function ( value ) { props.setAttributes( { caseSensitive: value } ); } } ),
					el( ToggleControl, { label: __( 'Ignore leading and trailing spaces', 'parish-formation' ), checked: attrs.trimSpaces, onChange: function ( value ) { props.setAttributes( { trimSpaces: value } ); } } ),
					el( ToggleControl, { label: __( 'Normalize repeated spaces', 'parish-formation' ), checked: attrs.normalizeSpaces, onChange: function ( value ) { props.setAttributes( { normalizeSpaces: value } ); } } ),
					el( ToggleControl, { label: __( 'Ignore punctuation', 'parish-formation' ), checked: attrs.ignorePunctuation, onChange: function ( value ) { props.setAttributes( { ignorePunctuation: value } ); } } ),
					el( ToggleControl, { label: __( 'Require staff review', 'parish-formation' ), checked: attrs.manualReview, onChange: function ( value ) { props.setAttributes( { manualReview: value } ); } } )
				),
				el( 'div', { className: 'pf-question-block__summary' }, __( 'Type:', 'parish-formation' ) + ' ' + attrs.type.replaceAll( '_', ' ' ) + ' · ' + ( attrs.graded ? attrs.points : 0 ) + ' ' + __( 'point(s)', 'parish-formation' ) )
			);
		},
		save: function () { return null; }
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
