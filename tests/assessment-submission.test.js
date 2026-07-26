'use strict';

let submitHandler;
let requestBody;
global.document = {
	addEventListener: function ( event, handler ) {
		if ( event === 'submit' ) { submitHandler = handler; }
	},
	createElement: function () { return { appendChild: function () {} }; },
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
	{ type: 'text', checked: false, name: 'pf_answers[248][blank-two]', value: 'Confirmation' }
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
process.stdout.write( 'Assessment submission JavaScript test passed: 3 checks.\n' );
