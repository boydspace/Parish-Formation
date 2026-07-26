( function () {
	'use strict';

	function addText( parent, tag, text ) {
		const node = document.createElement( tag );
		node.textContent = text;
		parent.appendChild( node );
		return node;
	}

	function collectAnswers( form ) {
		const answers = {};
		form.querySelectorAll( '[name^="pf_answers["]' ).forEach( function ( input ) {
			if ( ( input.type === 'radio' || input.type === 'checkbox' ) && ! input.checked ) {
				return;
			}
			const match = input.name.match( /pf_answers\[(\d+)\]/ );
			if ( ! match ) {
				return;
			}
			const questionId = match[ 1 ];
			const childMatch = input.name.match( /pf_answers\[\d+\]\[([a-z0-9_-]+)\]$/i );
			if ( childMatch && ! [ '__proto__', 'prototype', 'constructor' ].includes( childMatch[ 1 ] ) ) {
				if ( ! answers[ questionId ] || Array.isArray( answers[ questionId ] ) ) { answers[ questionId ] = {}; }
				answers[ questionId ][ childMatch[ 1 ] ] = input.value;
			} else if ( input.name.endsWith( '[]' ) ) {
				if ( ! Array.isArray( answers[ questionId ] ) ) {
					answers[ questionId ] = [];
				}
				answers[ questionId ].push( input.value );
			} else {
				answers[ questionId ] = input.value;
			}
		} );
		return answers;
	}

	function announceOrderingPosition( item ) {
		const list = item && item.parentElement;
		if ( ! list ) { return; }
		const items = Array.from( list.children );
		const status = list.parentElement.querySelector( '.pf-ordering-status' );
		if ( status ) {
			const label = ( item.querySelector( '.pf-ordering-label' ) || item ).textContent.trim();
			status.textContent = ( pfAssessmentSubmission.orderingMoved || '%1$s moved to position %2$d.' ).replace( '%1$s', label ).replace( '%2$d', items.indexOf( item ) + 1 );
		}
	}

	document.addEventListener( 'keydown', function ( event ) {
		const item = event.target.closest ? event.target.closest( '.pf-ordering-item[draggable="true"]' ) : null;
		if ( ! item || ! [ 'ArrowUp', 'ArrowDown' ].includes( event.key ) ) { return; }
		const sibling = 'ArrowUp' === event.key ? item.previousElementSibling : item.nextElementSibling;
		if ( ! sibling ) { return; }
		event.preventDefault();
		if ( 'ArrowUp' === event.key ) { item.parentElement.insertBefore( item, sibling ); } else { item.parentElement.insertBefore( sibling, item ); }
		announceOrderingPosition( item );
		item.focus();
	} );

	let draggedOrderingItem = null;
	document.addEventListener( 'dragstart', function ( event ) {
		const item = event.target.closest ? event.target.closest( '.pf-ordering-item[draggable="true"]' ) : null;
		if ( ! item ) { return; }
		draggedOrderingItem = item;
		item.classList.add( 'is-dragging' );
		if ( event.dataTransfer ) {
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData( 'text/plain', ( item.querySelector( 'input[type="hidden"]' ) || {} ).value || '' );
		}
	} );
	document.addEventListener( 'dragover', function ( event ) {
		if ( ! draggedOrderingItem ) { return; }
		const target = event.target.closest ? event.target.closest( '.pf-ordering-item[draggable="true"]' ) : null;
		if ( ! target || target === draggedOrderingItem || target.parentElement !== draggedOrderingItem.parentElement ) { return; }
		event.preventDefault();
		target.parentElement.querySelectorAll( '.is-drag-target' ).forEach( function ( item ) { item.classList.remove( 'is-drag-target' ); } );
		target.classList.add( 'is-drag-target' );
		const rect = target.getBoundingClientRect();
		if ( event.clientY < rect.top + rect.height / 2 ) {
			target.parentElement.insertBefore( draggedOrderingItem, target );
		} else {
			target.parentElement.insertBefore( draggedOrderingItem, target.nextElementSibling );
		}
	} );
	document.addEventListener( 'drop', function ( event ) {
		if ( ! draggedOrderingItem ) { return; }
		const target = event.target.closest ? event.target.closest( '.pf-ordering-item[draggable="true"]' ) : null;
		if ( ! target || target === draggedOrderingItem || target.parentElement !== draggedOrderingItem.parentElement ) { return; }
		event.preventDefault();
		announceOrderingPosition( draggedOrderingItem );
	} );
	document.addEventListener( 'dragend', function () {
		if ( draggedOrderingItem ) { announceOrderingPosition( draggedOrderingItem ); }
		document.querySelectorAll( '.pf-ordering-item.is-dragging, .pf-ordering-item.is-drag-target' ).forEach( function ( item ) { item.classList.remove( 'is-dragging', 'is-drag-target' ); } );
		draggedOrderingItem = null;
	} );

	function renderQuestionFeedback( feedbackItems ) {
		document.querySelectorAll( '.pf-question-feedback' ).forEach( function ( item ) { item.remove(); } );
		Object.keys( feedbackItems || {} ).forEach( function ( questionId ) {
			const section = document.querySelector( '.pf-assessment-question[data-question-id="' + questionId + '"]' );
			const feedback = feedbackItems[ questionId ];
			if ( ! section || ( ! feedback.messages.length && ! feedback.choice_feedback.length ) ) { return; }
			const box = document.createElement( 'div' );
			box.className = 'pf-question-feedback uk-alert uk-alert-primary';
			box.setAttribute( 'role', 'status' );
			feedback.choice_feedback.forEach( function ( choice ) { addText( box, 'p', choice.label + ': ' + choice.message ); } );
			feedback.messages.forEach( function ( message ) { addText( box, 'p', message ); } );
			section.appendChild( box );
		} );
	}

	document.addEventListener( 'submit', function ( event ) {
		const form = event.target.closest( '.pf-assessment-questions' );
		if ( ! form || ! window.fetch || ! window.pfAssessmentSubmission ) {
			return;
		}
		event.preventDefault();
		const button = form.querySelector( 'button[type="submit"]' );
		const resultBox = form.previousElementSibling;
		const answers = collectAnswers( form );

		button.disabled = true;
		const originalLabel = button.textContent;
		button.textContent = pfAssessmentSubmission.submitting;
		resultBox.replaceChildren();
		form.setAttribute( 'aria-busy', 'true' );

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
			const previousResult = document.querySelector( '.pf-assessment-latest-result' );
			if ( previousResult ) { previousResult.remove(); }
			const alert = document.createElement( 'div' );
			alert.className = 'uk-alert ' + ( data.status === 'passed' ? 'uk-alert-success' : data.status === 'failed' ? 'uk-alert-danger' : 'uk-alert-primary' );
			addText( alert, 'p', data.statusLabel );
			if ( data.assessmentMode !== 'acknowledgement' && data.status !== 'pending_review' ) {
				addText( alert, 'p', 'Score: ' + data.score + ' of ' + data.maximum + ' points; ' + data.correct + ' of ' + data.totalGraded + ' correct.' );
			}
			if ( data.assessmentMode !== 'acknowledgement' ) {
				addText( alert, 'p', 'Attempt ' + data.attempt + ' of ' + data.maxAttempts + '.' );
			}
			resultBox.appendChild( alert );
			resultBox.focus();
			renderQuestionFeedback( data.questionFeedback );

			document.querySelectorAll( '.uk-progress' ).forEach( function ( progress ) { progress.value = data.progress; } );
			const progressText = document.querySelector( '.pf-progress-text' );
			if ( progressText ) { progressText.textContent = data.progress + '% complete'; }

			if ( data.status === 'passed' || data.assessmentMode === 'acknowledgement' ) {
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
			resultBox.focus();
			button.disabled = false;
			button.textContent = originalLabel;
		} ).finally( function () {
			form.removeAttribute( 'aria-busy' );
		} );
	} );
}() );
