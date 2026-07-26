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
			caseSensitive: { type: 'boolean', default: false }, trimSpaces: { type: 'boolean', default: true }, normalizeSpaces: { type: 'boolean', default: false }, ignorePunctuation: { type: 'boolean', default: false }, matchMode: { type: 'string', default: 'exact' },
			blanks: { type: 'array', default: [] }, blankPointMode: { type: 'string', default: 'equal' },
			matchingPairs: { type: 'array', default: [] }, matchingPointMode: { type: 'string', default: 'equal' },
			orderingItems: { type: 'array', default: [] }, orderingPointMode: { type: 'string', default: 'equal' }, orderingGradingMode: { type: 'string', default: 'all_or_nothing' },
			reflectionMinCharacters: { type: 'integer', default: 0 }, reflectionMaxCharacters: { type: 'integer', default: 0 }, reflectionCompletionCredit: { type: 'boolean', default: false }, reflectionPrivateNotice: { type: 'string', default: '' }, reflectionSamplePrompt: { type: 'string', default: '' },
			acknowledgementCheckboxLabel: { type: 'string', default: 'I acknowledge this statement.' }, acknowledgementPolicyUrl: { type: 'string', default: '' }, acknowledgementRequireOpen: { type: 'boolean', default: false }, acknowledgementCompletionCredit: { type: 'boolean', default: false },
			ratingMinimum: { type: 'integer', default: 1 }, ratingMaximum: { type: 'integer', default: 5 }, ratingFirstLabel: { type: 'string', default: 'Lowest' }, ratingLastLabel: { type: 'string', default: 'Highest' }, ratingValueLabels: { type: 'array', default: [] }, ratingOrientation: { type: 'string', default: 'horizontal' }
		},
		supports: { html: false, reusable: false },
		edit: function ( props ) {
			const attrs = props.attributes;
			const choiceType = [ 'multiple_choice', 'multiple_select' ].includes( attrs.type );
			const choices = ( attrs.choices || [] ).length ? attrs.choices : legacyChoices( attrs );
			const supportsCorrectFeedback = [ 'multiple_choice', 'multiple_select', 'true_false', 'short_answer' ].includes( attrs.type );
			const correctCount = choices.filter( function ( choice ) { return choice.correct; } ).length;
			const choiceError = choiceType && ( choices.length < 2 || correctCount < 1 ) ? __( 'Add at least two choices and mark at least one correct answer.', 'parish-formation' ) : '';
			const blankCount = ( ( attrs.prompt || '' ).match( /\[blank\]/gi ) || [] ).length;
			const blanks = ( attrs.blanks || [] ).slice( 0, blankCount );
			while ( blanks.length < blankCount ) { blanks.push( { id: 'blank-' + props.clientId.replace( /[^a-z0-9]/gi, '' ).slice( 0, 8 ) + '-' + ( blanks.length + 1 ), acceptedAnswers: [], caseSensitive: false, matchMode: 'normalized', points: 1 } ); }
			const blankError = attrs.type === 'fill_blank' && ( ! blankCount || blanks.some( function ( blank ) { return ! ( blank.acceptedAnswers || [] ).filter( Boolean ).length; } ) ) ? __( 'Use at least one [blank] placeholder and enter an accepted answer for every blank.', 'parish-formation' ) : '';
			const matchingPairs = attrs.matchingPairs || [];
			const matchingError = attrs.type === 'matching' && ( matchingPairs.length < 2 || matchingPairs.some( function ( pair ) { return ! String( pair.prompt || '' ).trim() || ! String( pair.answer || '' ).trim(); } ) ) ? __( 'Add at least two complete prompt-and-answer pairs.', 'parish-formation' ) : '';
			const orderingItems = attrs.orderingItems || [];
			const orderingError = attrs.type === 'ordering' && ( orderingItems.length < 2 || orderingItems.some( function ( item ) { return ! String( item.label || '' ).trim(); } ) ) ? __( 'Add at least two complete items in the correct order.', 'parish-formation' ) : '';

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
			function updateBlank( index, changes ) {
				const next = blanks.map( function ( blank ) { return Object.assign( {}, blank ); } );
				next[ index ] = Object.assign( {}, next[ index ], changes );
				props.setAttributes( { blanks: next } );
			}
			function updatePrompt( value ) {
				const count = ( value.match( /\[blank\]/gi ) || [] ).length;
				const next = blanks.slice( 0, count );
				while ( next.length < count ) { next.push( { id: 'blank-' + props.clientId.replace( /[^a-z0-9]/gi, '' ).slice( 0, 8 ) + '-' + ( next.length + 1 ), acceptedAnswers: [], caseSensitive: false, matchMode: 'normalized', points: 1 } ); }
				props.setAttributes( { prompt: value, blanks: next } );
			}
			function saveMatchingPairs( next ) { props.setAttributes( { matchingPairs: next } ); }
			function updateMatchingPair( index, changes ) {
				const next = matchingPairs.map( function ( pair ) { return Object.assign( {}, pair ); } );
				next[ index ] = Object.assign( {}, next[ index ], changes ); saveMatchingPairs( next );
			}
			function moveMatchingPair( index, direction ) {
				const target = index + direction; if ( target < 0 || target >= matchingPairs.length ) { return; }
				const next = matchingPairs.slice(); const row = next[ index ]; next[ index ] = next[ target ]; next[ target ] = row; saveMatchingPairs( next );
			}
			function addMatchingPair() {
				const id = 'pair-' + props.clientId.replace( /[^a-z0-9]/gi, '' ).slice( 0, 8 ) + '-' + Date.now().toString( 36 );
				saveMatchingPairs( matchingPairs.concat( [ { id: id, answerId: 'answer-' + props.clientId.replace( /[^a-z0-9]/gi, '' ).slice( 0, 8 ) + '-' + ( Date.now() + 1 ).toString( 36 ), prompt: '', answer: '', points: 1 } ] ) );
			}
			function saveOrderingItems( next ) { props.setAttributes( { orderingItems: next } ); }
			function updateOrderingItem( index, changes ) {
				const next = orderingItems.map( function ( item ) { return Object.assign( {}, item ); } );
				next[ index ] = Object.assign( {}, next[ index ], changes ); saveOrderingItems( next );
			}
			function moveOrderingItem( index, direction ) {
				const target = index + direction; if ( target < 0 || target >= orderingItems.length ) { return; }
				const next = orderingItems.slice(); const row = next[ index ]; next[ index ] = next[ target ]; next[ target ] = row; saveOrderingItems( next );
			}
			function addOrderingItem() {
				const id = 'item-' + props.clientId.replace( /[^a-z0-9]/gi, '' ).slice( 0, 8 ) + '-' + Date.now().toString( 36 );
				saveOrderingItems( orderingItems.concat( [ { id: id, label: '', points: 1 } ] ) );
			}

			return el( 'div', blockEditor.useBlockProps( { className: 'pf-question-block' } ),
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Question Settings', 'parish-formation' ), initialOpen: true },
						el( SelectControl, { label: __( 'Question type', 'parish-formation' ), value: attrs.type, options: [
							{ label: __( '— Automatically Graded —', 'parish-formation' ), value: '__automatic', disabled: true },
							{ label: __( 'Multiple Choice', 'parish-formation' ), value: 'multiple_choice' }, { label: __( 'Multiple Select', 'parish-formation' ), value: 'multiple_select' }, { label: __( 'True / False', 'parish-formation' ), value: 'true_false' }, { label: __( 'Short Answer', 'parish-formation' ), value: 'short_answer' },
							{ label: __( 'Fill in the Blank', 'parish-formation' ), value: 'fill_blank' }, { label: __( 'Matching', 'parish-formation' ), value: 'matching' }, { label: __( 'Ordering', 'parish-formation' ), value: 'ordering' }, { label: __( 'Numeric Response (Phase 3)', 'parish-formation' ), value: '__numeric', disabled: true },
							{ label: __( '— Instructor Reviewed —', 'parish-formation' ), value: '__review', disabled: true }, { label: __( 'Paragraph Response (Phase 3)', 'parish-formation' ), value: '__paragraph', disabled: true }, { label: __( 'File Upload (Phase 3)', 'parish-formation' ), value: '__file_upload', disabled: true },
							{ label: __( '— Formation and Feedback —', 'parish-formation' ), value: '__formation', disabled: true }, { label: __( 'Reflection Response', 'parish-formation' ), value: 'reflection' }, { label: __( 'Rating Scale', 'parish-formation' ), value: 'rating_scale' }, { label: __( 'Yes / No (Phase 3)', 'parish-formation' ), value: '__yes_no', disabled: true }, { label: __( 'Acknowledgment', 'parish-formation' ), value: 'acknowledgement' }, { label: __( 'Image Selection (Phase 3)', 'parish-formation' ), value: '__image', disabled: true }
						], onChange: function ( value ) { if ( value.indexOf( '__' ) === 0 ) { return; } props.setAttributes( { type: value, graded: ! [ 'reflection', 'acknowledgement', 'rating_scale' ].includes( value ), manualReview: value === 'reflection' } ); } } ),
						el( TextareaControl, { label: __( 'Optional instructions', 'parish-formation' ), value: attrs.instructions, onChange: function ( value ) { props.setAttributes( { instructions: value } ); } } ),
						! [ 'reflection', 'acknowledgement', 'rating_scale' ].includes( attrs.type ) && el( ToggleControl, { label: __( 'Graded question', 'parish-formation' ), checked: attrs.graded, onChange: function ( value ) { props.setAttributes( { graded: value } ); } } ),
						( attrs.graded || ( attrs.type === 'reflection' && attrs.reflectionCompletionCredit ) || ( attrs.type === 'acknowledgement' && attrs.acknowledgementCompletionCredit ) ) && ! ( ( attrs.type === 'fill_blank' && attrs.blankPointMode === 'custom' ) || ( attrs.type === 'matching' && attrs.matchingPointMode === 'custom' ) || ( attrs.type === 'ordering' && attrs.orderingPointMode === 'custom' ) ) && el( TextControl, { label: __( 'Points', 'parish-formation' ), type: 'number', min: 1, value: attrs.points, onChange: function ( value ) { props.setAttributes( { points: Math.max( 1, parseInt( value, 10 ) || 1 ) } ); } } ),
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
				el( RichText, { tagName: 'div', className: 'pf-question-block__prompt', value: attrs.prompt, placeholder: attrs.type === 'fill_blank' ? __( 'Example: The sacrament of [blank] is the gateway to Christian life.', 'parish-formation' ) : __( 'Type the question…', 'parish-formation' ), onChange: updatePrompt } ),
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
				attrs.type === 'fill_blank' && el( 'div', { className: 'pf-question-fill-blank-settings' },
					el( 'p', { className: 'description' }, __( 'Type [blank] in the prompt wherever an inline answer field should appear.', 'parish-formation' ) ),
					blankError && el( Notice, { status: 'warning', isDismissible: false }, blankError ),
					el( SelectControl, { label: __( 'Point allocation', 'parish-formation' ), value: attrs.blankPointMode, options: [ { label: __( 'Divide question points equally', 'parish-formation' ), value: 'equal' }, { label: __( 'Custom points for each blank', 'parish-formation' ), value: 'custom' } ], onChange: function ( value ) { props.setAttributes( { blankPointMode: value } ); } } ),
					blanks.map( function ( blank, index ) { return el( 'div', { className: 'pf-question-choice-row', key: blank.id },
						el( 'h4', {}, __( 'Blank', 'parish-formation' ) + ' ' + ( index + 1 ) ),
						el( TextareaControl, { label: __( 'Accepted answers (one per line)', 'parish-formation' ), value: ( blank.acceptedAnswers || [] ).join( '\n' ), onChange: function ( value ) { updateBlank( index, { acceptedAnswers: value.split( /\r?\n/ ) } ); } } ),
						el( ToggleControl, { label: __( 'Case-sensitive comparison', 'parish-formation' ), checked: !! blank.caseSensitive, onChange: function ( value ) { updateBlank( index, { caseSensitive: value } ); } } ),
						el( SelectControl, { label: __( 'Matching method', 'parish-formation' ), value: blank.matchMode || 'normalized', options: [ { label: __( 'Normalized match (trim and collapse spaces)', 'parish-formation' ), value: 'normalized' }, { label: __( 'Exact match', 'parish-formation' ), value: 'exact' } ], onChange: function ( value ) { updateBlank( index, { matchMode: value } ); } } ),
						attrs.blankPointMode === 'custom' && el( TextControl, { label: __( 'Points for this blank', 'parish-formation' ), type: 'number', min: 0, step: 0.25, value: blank.points, onChange: function ( value ) { updateBlank( index, { points: Math.max( 0, parseFloat( value ) || 0 ) } ); } } )
					); } )
				),
				attrs.type === 'matching' && el( 'div', { className: 'pf-question-matching-settings' },
					el( 'p', { className: 'description' }, __( 'Learners will choose an answer from an accessible dropdown for each prompt. Answers are always randomized.', 'parish-formation' ) ),
					matchingError && el( Notice, { status: 'warning', isDismissible: false }, matchingError ),
					el( SelectControl, { label: __( 'Point allocation', 'parish-formation' ), value: attrs.matchingPointMode, options: [ { label: __( 'Divide question points equally', 'parish-formation' ), value: 'equal' }, { label: __( 'Custom points for each pair', 'parish-formation' ), value: 'custom' } ], onChange: function ( value ) { props.setAttributes( { matchingPointMode: value } ); } } ),
					matchingPairs.map( function ( pair, index ) { return el( 'div', { className: 'pf-question-choice-row', key: pair.id },
						el( TextControl, { label: __( 'Prompt', 'parish-formation' ), value: pair.prompt || '', onChange: function ( value ) { updateMatchingPair( index, { prompt: value } ); } } ),
						el( TextControl, { label: __( 'Matching answer', 'parish-formation' ), value: pair.answer || '', onChange: function ( value ) { updateMatchingPair( index, { answer: value } ); } } ),
						attrs.matchingPointMode === 'custom' && el( TextControl, { label: __( 'Points for this pair', 'parish-formation' ), type: 'number', min: 0, step: 0.25, value: pair.points, onChange: function ( value ) { updateMatchingPair( index, { points: Math.max( 0, parseFloat( value ) || 0 ) } ); } } ),
						el( 'div', { className: 'pf-question-choice-actions' },
							el( Button, { variant: 'secondary', disabled: index === 0, onClick: function () { moveMatchingPair( index, -1 ); } }, __( 'Move up', 'parish-formation' ) ),
							el( Button, { variant: 'secondary', disabled: index === matchingPairs.length - 1, onClick: function () { moveMatchingPair( index, 1 ); } }, __( 'Move down', 'parish-formation' ) ),
							el( Button, { isDestructive: true, onClick: function () { saveMatchingPairs( matchingPairs.filter( function ( unused, rowIndex ) { return rowIndex !== index; } ) ); } }, __( 'Remove', 'parish-formation' ) )
						)
					); } ),
					el( Button, { variant: 'primary', onClick: addMatchingPair }, __( 'Add matching pair', 'parish-formation' ) )
				),
				attrs.type === 'ordering' && el( 'div', { className: 'pf-question-ordering-settings' },
					el( 'p', { className: 'description' }, __( 'Enter the items below in their correct order. Learners receive them in a randomized order.', 'parish-formation' ) ),
					orderingError && el( Notice, { status: 'warning', isDismissible: false }, orderingError ),
					el( SelectControl, { label: __( 'Grading mode', 'parish-formation' ), value: attrs.orderingGradingMode, options: [ { label: __( 'All or nothing', 'parish-formation' ), value: 'all_or_nothing' }, { label: __( 'Partial credit for correct positions', 'parish-formation' ), value: 'partial' } ], onChange: function ( value ) { props.setAttributes( { orderingGradingMode: value } ); } } ),
					el( SelectControl, { label: __( 'Point allocation', 'parish-formation' ), value: attrs.orderingPointMode, options: [ { label: __( 'Divide question points equally', 'parish-formation' ), value: 'equal' }, { label: __( 'Custom points for each position', 'parish-formation' ), value: 'custom' } ], onChange: function ( value ) { props.setAttributes( { orderingPointMode: value } ); } } ),
					orderingItems.map( function ( item, index ) { return el( 'div', { className: 'pf-question-choice-row', key: item.id },
						el( TextControl, { label: __( 'Item', 'parish-formation' ) + ' ' + ( index + 1 ), value: item.label || '', onChange: function ( value ) { updateOrderingItem( index, { label: value } ); } } ),
						attrs.orderingPointMode === 'custom' && el( TextControl, { label: __( 'Points for this position', 'parish-formation' ), type: 'number', min: 0, step: 0.25, value: item.points, onChange: function ( value ) { updateOrderingItem( index, { points: Math.max( 0, parseFloat( value ) || 0 ) } ); } } ),
						el( 'div', { className: 'pf-question-choice-actions' },
							el( Button, { variant: 'secondary', disabled: index === 0, onClick: function () { moveOrderingItem( index, -1 ); } }, __( 'Move up', 'parish-formation' ) ),
							el( Button, { variant: 'secondary', disabled: index === orderingItems.length - 1, onClick: function () { moveOrderingItem( index, 1 ); } }, __( 'Move down', 'parish-formation' ) ),
							el( Button, { isDestructive: true, onClick: function () { saveOrderingItems( orderingItems.filter( function ( unused, rowIndex ) { return rowIndex !== index; } ) ); } }, __( 'Remove', 'parish-formation' ) )
						)
					); } ),
					el( Button, { variant: 'primary', onClick: addOrderingItem }, __( 'Add ordering item', 'parish-formation' ) )
				),
				attrs.type === 'reflection' && el( 'div', { className: 'pf-question-reflection-settings' },
					el( 'p', { className: 'description' }, __( 'Reflection responses support personal and spiritual formation. They are never labeled correct or incorrect.', 'parish-formation' ) ),
					el( TextControl, { label: __( 'Minimum character count', 'parish-formation' ), type: 'number', min: 0, value: attrs.reflectionMinCharacters, onChange: function ( value ) { props.setAttributes( { reflectionMinCharacters: Math.max( 0, parseInt( value, 10 ) || 0 ) } ); } } ),
					el( TextControl, { label: __( 'Maximum character count (0 for no maximum)', 'parish-formation' ), type: 'number', min: 0, value: attrs.reflectionMaxCharacters, onChange: function ( value ) { props.setAttributes( { reflectionMaxCharacters: Math.max( 0, parseInt( value, 10 ) || 0 ) } ); } } ),
					attrs.reflectionMaxCharacters > 0 && attrs.reflectionMaxCharacters < attrs.reflectionMinCharacters && el( Notice, { status: 'warning', isDismissible: false }, __( 'The maximum will be raised to match the minimum when this question is saved.', 'parish-formation' ) ),
					el( ToggleControl, { label: __( 'Award completion credit for a valid submission', 'parish-formation' ), checked: attrs.reflectionCompletionCredit, onChange: function ( value ) { props.setAttributes( { reflectionCompletionCredit: value, graded: value } ); } } ),
					el( TextareaControl, { label: __( 'Private-response notice (optional)', 'parish-formation' ), help: __( 'Explain who can view this response.', 'parish-formation' ), value: attrs.reflectionPrivateNotice, onChange: function ( value ) { props.setAttributes( { reflectionPrivateNotice: value } ); } } ),
					el( TextareaControl, { label: __( 'Sample reflection (optional)', 'parish-formation' ), value: attrs.reflectionSamplePrompt, onChange: function ( value ) { props.setAttributes( { reflectionSamplePrompt: value } ); } } )
				),
				attrs.type === 'acknowledgement' && el( 'div', { className: 'pf-question-acknowledgement-settings' },
					el( 'p', { className: 'description' }, __( 'The question prompt is the statement preserved in the learner\'s submission record.', 'parish-formation' ) ),
					el( TextControl, { label: __( 'Checkbox label', 'parish-formation' ), value: attrs.acknowledgementCheckboxLabel, onChange: function ( value ) { props.setAttributes( { acknowledgementCheckboxLabel: value } ); } } ),
					el( TextControl, { label: __( 'Policy or document URL (optional)', 'parish-formation' ), type: 'url', value: attrs.acknowledgementPolicyUrl, onChange: function ( value ) { props.setAttributes( { acknowledgementPolicyUrl: value } ); } } ),
					attrs.acknowledgementPolicyUrl && el( ToggleControl, { label: __( 'Require the linked item to be opened first', 'parish-formation' ), checked: attrs.acknowledgementRequireOpen, onChange: function ( value ) { props.setAttributes( { acknowledgementRequireOpen: value } ); } } ),
					el( ToggleControl, { label: __( 'Award completion credit', 'parish-formation' ), checked: attrs.acknowledgementCompletionCredit, onChange: function ( value ) { props.setAttributes( { acknowledgementCompletionCredit: value, graded: value } ); } } )
				),
				attrs.type === 'rating_scale' && el( 'div', { className: 'pf-question-rating-settings' },
					el( 'p', { className: 'description' }, __( 'Rating scales record feedback or formation responses and are not marked correct or incorrect.', 'parish-formation' ) ),
					el( TextControl, { label: __( 'Minimum value', 'parish-formation' ), type: 'number', value: attrs.ratingMinimum, onChange: function ( value ) { props.setAttributes( { ratingMinimum: parseInt( value, 10 ) || 0 } ); } } ),
					el( TextControl, { label: __( 'Maximum value', 'parish-formation' ), type: 'number', value: attrs.ratingMaximum, help: __( 'The saved scale may contain no more than 21 values.', 'parish-formation' ), onChange: function ( value ) { props.setAttributes( { ratingMaximum: parseInt( value, 10 ) || 0 } ); } } ),
					attrs.ratingMaximum <= attrs.ratingMinimum && el( Notice, { status: 'warning', isDismissible: false }, __( 'The maximum must be greater than the minimum and will be corrected when saved.', 'parish-formation' ) ),
					el( TextControl, { label: __( 'First-value label', 'parish-formation' ), help: __( 'Defaults to “Lowest” if left blank.', 'parish-formation' ), placeholder: __( 'Never or Strongly Disagree', 'parish-formation' ), value: attrs.ratingFirstLabel, onChange: function ( value ) { props.setAttributes( { ratingFirstLabel: value } ); } } ),
					el( TextControl, { label: __( 'Last-value label', 'parish-formation' ), help: __( 'Defaults to “Highest” if left blank.', 'parish-formation' ), placeholder: __( 'Always or Strongly Agree', 'parish-formation' ), value: attrs.ratingLastLabel, onChange: function ( value ) { props.setAttributes( { ratingLastLabel: value } ); } } ),
					el( TextareaControl, { label: __( 'Label for every value (optional, one per line)', 'parish-formation' ), help: __( 'Lines correspond to values starting at the minimum. Leave a line blank to show only its number.', 'parish-formation' ), value: ( attrs.ratingValueLabels || [] ).join( '\n' ), onChange: function ( value ) { props.setAttributes( { ratingValueLabels: value.split( /\r?\n/ ) } ); } } ),
					el( SelectControl, { label: __( 'Display direction', 'parish-formation' ), value: attrs.ratingOrientation, options: [ { label: __( 'Horizontal', 'parish-formation' ), value: 'horizontal' }, { label: __( 'Vertical', 'parish-formation' ), value: 'vertical' } ], onChange: function ( value ) { props.setAttributes( { ratingOrientation: value } ); } } )
				),
				el( 'div', { className: 'pf-question-block__summary' }, __( 'Type:', 'parish-formation' ) + ' ' + attrs.type.replaceAll( '_', ' ' ) + ' · ' + ( attrs.graded || attrs.reflectionCompletionCredit || attrs.acknowledgementCompletionCredit ? attrs.points : 0 ) + ' ' + __( 'point(s)', 'parish-formation' ) )
			);
		},
		save: function () { return null; }
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n ) );
