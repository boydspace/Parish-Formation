( function () {
	'use strict';

	document.addEventListener( 'submit', async function ( event ) {
		const form = event.target.closest( '.pf-course-access-code-form' );
		if ( ! form || typeof pfEnrollment === 'undefined' ) {
			return;
		}

		event.preventDefault();
		const button = form.querySelector( 'button[type="submit"]' );
		const input = form.querySelector( '[name="access_code"]' );
		const courseId = form.querySelector( '[name="course_id"]' );
		const message = form.querySelector( '.pf-course-access-code-message' );
		button.disabled = true;
		button.textContent = pfEnrollment.submitting;
		message.className = 'pf-course-access-code-message';
		message.textContent = '';

		try {
			const response = await fetch( pfEnrollment.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': pfEnrollment.nonce
				},
				body: JSON.stringify( {
					course_id: courseId.value,
					access_code: input.value
				} )
			} );
			const result = await response.json();
			if ( ! response.ok ) {
				throw new Error( result.message || pfEnrollment.defaultError );
			}

			const enrollment = document.createElement( 'div' );
			enrollment.className = 'pf-course-catalog-enrollment';
			const status = document.createElement( 'span' );
			status.className = 'uk-label uk-label-success';
			status.textContent = result.status_label;
			const link = document.createElement( 'a' );
			link.className = 'uk-button uk-button-primary';
			link.href = result.course_url || form.dataset.courseUrl;
			link.textContent = pfEnrollment.openFormation;
			enrollment.append( status, link );

			message.classList.add( 'is-success' );
			message.textContent = result.message;
			form.parentNode.insertBefore( message, form );
			form.replaceWith( enrollment );
		} catch ( error ) {
			message.classList.add( 'is-error' );
			message.textContent = error.message || pfEnrollment.defaultError;
			input.focus();
			button.disabled = false;
			button.textContent = button.dataset.label;
		}
	} );
}() );
