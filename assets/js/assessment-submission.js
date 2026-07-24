( function () {
	'use strict';

	function addText( parent, tag, text ) {
		const node = document.createElement( tag );
		node.textContent = text;
		parent.appendChild( node );
		return node;
	}

	document.addEventListener( 'submit', function ( event ) {
		const form = event.target.closest( '.pf-assessment-questions' );
		if ( ! form || ! window.fetch || ! window.pfAssessmentSubmission ) {
			return;
		}
		event.preventDefault();
		const button = form.querySelector( 'button[type="submit"]' );
		const resultBox = form.previousElementSibling;
		const answers = {};
		form.querySelectorAll( '[name^="pf_answers["]' ).forEach( function ( input ) {
			if ( ( input.type === 'radio' || input.type === 'checkbox' ) && ! input.checked ) {
				return;
			}
			const match = input.name.match( /pf_answers\[(\d+)\]/ );
			if ( match ) {
				answers[ match[ 1 ] ] = input.value;
			}
		} );

		button.disabled = true;
		const originalLabel = button.textContent;
		button.textContent = pfAssessmentSubmission.submitting;
		resultBox.replaceChildren();

		window.fetch( pfAssessmentSubmission.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': pfAssessmentSubmission.nonce },
			body: JSON.stringify( {
				enrollment_id: form.elements.enrollment_id.value,
				course_id: form.elements.course_id.value,
				assessment_id: form.elements.assessment_id.value,
				return_url: window.location.href,
				base_url: form.elements.formation_base_url.value,
				answers: answers
			} )
		} ).then( function ( response ) {
			return response.json().then( function ( body ) {
				if ( ! response.ok ) {
					throw new Error( body.message || pfAssessmentSubmission.error );
				}
				return body;
			} );
		} ).then( function ( data ) {
			const alert = document.createElement( 'div' );
			alert.className = 'uk-alert ' + ( data.status === 'passed' ? 'uk-alert-success' : data.status === 'failed' ? 'uk-alert-danger' : 'uk-alert-primary' );
			addText( alert, 'p', data.statusLabel );
			if ( data.status !== 'pending_review' ) {
				addText( alert, 'p', 'Score: ' + data.score + ' of ' + data.maximum + ' points; ' + data.correct + ' of ' + data.totalGraded + ' correct.' );
			}
			addText( alert, 'p', 'Attempt ' + data.attempt + ' of ' + data.maxAttempts + '.' );
			resultBox.appendChild( alert );

			document.querySelectorAll( '.uk-progress' ).forEach( function ( progress ) { progress.value = data.progress; } );
			const progressText = document.querySelector( '.pf-progress-text' );
			if ( progressText ) { progressText.textContent = data.progress + '% complete'; }

			if ( data.status === 'passed' ) {
				form.querySelectorAll( 'input, textarea, button' ).forEach( function ( field ) { field.disabled = true; } );
				button.hidden = true;
				const item = document.querySelector( '.pf-lesson-navigation [data-item-id="' + form.elements.assessment_id.value + '"]' );
				if ( item ) {
					item.classList.add( 'is-complete' );
					const marker = item.querySelector( '.pf-lesson-marker' );
					if ( marker ) { marker.innerHTML = '&#10003;'; }
					if ( item.nextElementSibling ) { item.nextElementSibling.classList.remove( 'is-locked' ); }
				}
				const continuation = document.createElement( 'div' );
				continuation.className = 'pf-assessment-continue uk-margin-top';
				const link = addText( continuation, 'a', data.nextLabel + ' →' );
				link.className = 'uk-button uk-button-primary';
				link.href = data.nextUrl;
				form.insertAdjacentElement( 'afterend', continuation );
			} else if ( data.status === 'pending_review' || data.attempt >= data.maxAttempts ) {
				button.hidden = true;
				form.querySelectorAll( 'input, textarea' ).forEach( function ( field ) { field.disabled = true; } );
			} else {
				button.disabled = false;
				button.textContent = originalLabel;
			}
		} ).catch( function ( error ) {
			const alert = document.createElement( 'div' );
			alert.className = 'uk-alert uk-alert-danger';
			addText( alert, 'p', error.message || pfAssessmentSubmission.error );
			resultBox.appendChild( alert );
			button.disabled = false;
			button.textContent = originalLabel;
		} );
	} );
}() );
