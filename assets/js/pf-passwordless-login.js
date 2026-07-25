( function () {
	'use strict';

	const config = window.pfPasswordlessLogin;
	if ( ! config ) {
		return;
	}

	function message( container, text, type ) {
		container.replaceChildren();
		const alert = document.createElement( 'div' );
		alert.className = 'uk-alert ' + ( 'success' === type ? 'uk-alert-success' : 'uk-alert-danger' );
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = text;
		alert.appendChild( paragraph );
		container.appendChild( alert );
	}

	function codeForm( container, requestId ) {
		const form = document.createElement( 'form' );
		form.className = 'pf-account-form pf-account-code-form';
		const label = document.createElement( 'label' );
		label.textContent = config.codeLabel;
		const input = document.createElement( 'input' );
		input.className = 'uk-input';
		input.type = 'text';
		input.inputMode = 'numeric';
		input.pattern = '[0-9]{6}';
		input.maxLength = 6;
		input.required = true;
		input.autocomplete = 'one-time-code';
		label.appendChild( input );
		const button = document.createElement( 'button' );
		button.className = 'uk-button uk-button-primary';
		button.type = 'submit';
		button.textContent = config.loginLabel;
		form.append( label, button );
		container.appendChild( form );
		input.focus();

		form.addEventListener( 'submit', async function ( event ) {
			event.preventDefault();
			if ( ! form.reportValidity() ) {
				return;
			}
			button.disabled = true;
			const data = new URLSearchParams( { action: 'pf_ajax_verify_passwordless_code', nonce: config.verifyNonce, passwordless_request: requestId, login_code: input.value } );
			try {
				const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: data.toString() } );
				const result = await response.json();
				if ( result.success && result.data.redirect ) {
					window.location.assign( result.data.redirect );
					return;
				}
				message( container, result.data && result.data.message ? result.data.message : config.errorMessage, 'error' );
				codeForm( container, requestId );
			} catch ( error ) {
				message( container, config.errorMessage, 'error' );
				codeForm( container, requestId );
			}
		} );
	}

	document.querySelectorAll( '.pf-account-form button[name="login_method"][value="passwordless"]' ).forEach( function ( button ) {
		button.addEventListener( 'click', async function ( event ) {
			event.preventDefault();
			const form = button.closest( 'form' );
			const card = button.closest( '.pf-account-card' );
			const container = card ? card.querySelector( '.pf-passwordless-response' ) : null;
			const email = form ? form.querySelector( 'input[name="log"]' ) : null;
			if ( ! form || ! container || ! email || ! email.reportValidity() ) {
				return;
			}
			button.disabled = true;
			const returnInput = form.querySelector( 'input[name="return_url"]' );
			const data = new URLSearchParams( { action: 'pf_ajax_request_passwordless_login', nonce: config.requestNonce, email: email.value, return_url: returnInput ? returnInput.value : window.location.href } );
			try {
				const response = await fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: data.toString() } );
				const result = await response.json();
				if ( ! result.success || ! result.data.request ) {
					message( container, result.data && result.data.message ? result.data.message : config.errorMessage, 'error' );
					return;
				}
				message( container, config.sentMessage, 'success' );
				codeForm( container, result.data.request );
			} catch ( error ) {
				message( container, config.errorMessage, 'error' );
			} finally {
				button.disabled = false;
			}
		} );
	} );
}() );
