'use strict';

let submitHandler;
let keydownHandler;
let inputHandler;
let clickHandler;
let requestBody;
const reflectionCounter = { textContent: '' };
global.document = {
	addEventListener: function ( event, handler ) {
		if ( event === 'submit' ) { submitHandler = handler; }
		if ( event === 'keydown' ) { keydownHandler = handler; }
		if ( event === 'input' ) { inputHandler = handler; }
		if ( event === 'click' ) { clickHandler = handler; }
	},
	createElement: function () { return { appendChild: function () {} }; },
	getElementById: function ( id ) { return id === 'reflection-counter' ? reflectionCounter : null; },
	querySelectorAll: function () { return []; },
	querySelector: function () { return null; }
};
global.pfAssessmentSubmission = { endpoint: '/test', nonce: 'nonce', submitting: 'Submitting', error: 'Error' };
global.window = {
	location: { href: 'http://example.test/course/' },
	pfAssessmentSubmission: global.pfAssessmentSubmission,
	fetch: function ( unusedUrl, options ) {
		requestBody = JSON.parse( options.body );
		return new Promise( function () {} );
	}
};

require( '../assets/js/assessment-submission.js' );

const button = { disabled: false, hidden: false, textContent: 'Submit' };
const resultBox = { replaceChildren: function () {} };
const inputs = [
	{ type: 'checkbox', checked: true, name: 'pf_answers[246][]', value: 'choice-a' },
	{ type: 'checkbox', checked: true, name: 'pf_answers[246][]', value: 'choice-b' },
	{ type: 'checkbox', checked: false, name: 'pf_answers[246][]', value: 'choice-c' },
	{ type: 'text', checked: false, name: 'pf_answers[247]', value: 'Original response' },
	{ type: 'text', checked: false, name: 'pf_answers[248][blank-one]', value: 'Baptism' },
	{ type: 'text', checked: false, name: 'pf_answers[248][blank-two]', value: 'Confirmation' },
	{ type: 'select-one', checked: false, name: 'pf_answers[249][prompt-one]', value: 'answer-two' },
	{ type: 'select-one', checked: false, name: 'pf_answers[249][prompt-two]', value: 'answer-one' },
	{ type: 'hidden', checked: false, name: 'pf_answers[250][]', value: 'item-three' },
	{ type: 'hidden', checked: false, name: 'pf_answers[250][]', value: 'item-one' },
	{ type: 'hidden', checked: false, name: 'pf_answers[250][]', value: 'item-two' }
	,{ type: 'hidden', checked: false, name: 'pf_answers[251][policy_opened]', value: '1' }
	,{ type: 'checkbox', checked: true, name: 'pf_answers[251][acknowledged]', value: 'acknowledged' }
	,{ type: 'radio', checked: true, name: 'pf_answers[252][value]', value: '4' }
	,{ type: 'checkbox', checked: true, name: 'pf_answers[253][]', value: 'image-one' }
	,{ type: 'checkbox', checked: true, name: 'pf_answers[253][]', value: 'image-three' }
];
const form = {
	previousElementSibling: resultBox,
	elements: { enrollment_id: { value: '1' }, course_id: { value: '2' }, assessment_id: { value: '3' }, formation_base_url: { value: '/formation/' } },
	querySelector: function () { return button; },
	querySelectorAll: function () { return inputs; },
	setAttribute: function () {},
	removeAttribute: function () {}
};

submitHandler( { preventDefault: function () {}, target: { closest: function () { return form; } } } );

if ( JSON.stringify( requestBody.answers['246'] ) !== JSON.stringify( [ 'choice-a', 'choice-b' ] ) ) {
	throw new Error( 'Multiple Select values were not serialized as an array.' );
}
if ( requestBody.answers['247'] !== 'Original response' ) {
	throw new Error( 'Scalar assessment response changed during serialization.' );
}
if ( JSON.stringify( requestBody.answers['248'] ) !== JSON.stringify( { 'blank-one': 'Baptism', 'blank-two': 'Confirmation' } ) ) {
	throw new Error( 'Fill in the Blank values were not serialized by stable blank ID.' );
}
if ( JSON.stringify( requestBody.answers['249'] ) !== JSON.stringify( { 'prompt-one': 'answer-two', 'prompt-two': 'answer-one' } ) ) {
	throw new Error( 'Matching values were not serialized by stable pair IDs.' );
}
if ( JSON.stringify( requestBody.answers['250'] ) !== JSON.stringify( [ 'item-three', 'item-one', 'item-two' ] ) ) {
	throw new Error( 'Ordering values were not serialized in their current DOM order.' );
}
if ( JSON.stringify( requestBody.answers['251'] ) !== JSON.stringify( { policy_opened: '1', acknowledged: 'acknowledged' } ) ) {
	throw new Error( 'Acknowledgment audit values were not serialized together.' );
}
if ( JSON.stringify( requestBody.answers['252'] ) !== JSON.stringify( { value: '4' } ) ) {
	throw new Error( 'Rating Scale value was not serialized as a structured response.' );
}
if ( JSON.stringify( requestBody.answers['253'] ) !== JSON.stringify( [ 'image-one', 'image-three' ] ) ) {
	throw new Error( 'Multiple Image Selection values were not serialized by stable image ID.' );
}
const acknowledgementOpened = { value: '0' };
const acknowledgementCheckbox = { disabled: true };
const acknowledgementNotice = { textContent: '' };
const acknowledgementResponse = { querySelector: function ( selector ) {
	if ( selector === '.pf-acknowledgement-policy-opened' ) { return acknowledgementOpened; }
	if ( selector.indexOf( '.pf-acknowledgement-checkbox' ) === 0 ) { return acknowledgementCheckbox; }
	if ( selector === '.pf-acknowledgement-open-notice' ) { return acknowledgementNotice; }
	return null;
} };
const acknowledgementLink = { closest: function ( selector ) { return selector === '.pf-acknowledgement-response' ? acknowledgementResponse : acknowledgementLink; } };
clickHandler( { target: { closest: function () { return acknowledgementLink; } } } );
if ( acknowledgementOpened.value !== '1' || acknowledgementCheckbox.disabled || acknowledgementNotice.textContent.indexOf( 'opened' ) === -1 ) {
	throw new Error( 'Opening an acknowledgment policy did not unlock and audit the checkbox.' );
}
const orderingStatus = { textContent: '' };
const orderingList = {
	children: [], parentElement: { querySelector: function () { return orderingStatus; } },
	insertBefore: function ( node, reference ) {
		this.children = this.children.filter( function ( child ) { return child !== node; } );
		this.children.splice( this.children.indexOf( reference ), 0, node );
		this.children.forEach( function ( child, index ) { child.previousElementSibling = orderingList.children[ index - 1 ] || null; child.nextElementSibling = orderingList.children[ index + 1 ] || null; } );
	}
};
function orderingItem( label ) { return { parentElement: orderingList, previousElementSibling: null, nextElementSibling: null, querySelector: function () { return { textContent: label }; } }; }
const orderingFirst = orderingItem( 'First' );
const orderingSecond = orderingItem( 'Second' );
orderingList.children = [ orderingFirst, orderingSecond ]; orderingFirst.nextElementSibling = orderingSecond; orderingSecond.previousElementSibling = orderingFirst;
orderingSecond.closest = function () { return orderingSecond; }; orderingSecond.focus = function () {};
keydownHandler( { target: orderingSecond, key: 'ArrowUp', preventDefault: function () {} } );
if ( orderingList.children[0] !== orderingSecond || orderingStatus.textContent.indexOf( 'position 1' ) === -1 ) {
	throw new Error( 'Ordering Arrow Up control did not update the DOM order and live status.' );
}
const reflectionField = { value: '1 2 3 4 5 6 7', dataset: { minCharacters: '10', maxCharacters: '20' }, matches: function () { return true; }, getAttribute: function () { return 'reflection-counter'; } };
inputHandler( { target: reflectionField } );
if ( reflectionCounter.textContent.indexOf( '3 more required' ) === -1 || reflectionCounter.textContent.indexOf( '13 non-space characters remaining' ) === -1 ) {
	throw new Error( 'Reflection character counter did not report minimum and maximum remaining values.' );
}
process.stdout.write( 'Assessment submission JavaScript test passed: 11 checks.\n' );
