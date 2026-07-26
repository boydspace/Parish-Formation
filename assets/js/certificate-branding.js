( function () {
	'use strict';

	function value( id, fallback ) {
		const field = document.getElementById( id );
		return field && field.value.trim() ? field.value.trim() : fallback;
	}

	function updatePreview() {
		const preview = document.getElementById( 'pf-certificate-branding-preview' );
		if ( ! preview ) { return; }
		preview.style.setProperty( '--pf-certificate-accent', value( 'pf-certificate-accent-color', '#1c5b8f' ) );
		preview.style.setProperty( '--pf-certificate-border', value( 'pf-certificate-border-color', '#b58d18' ) );
		const logoWidth = value( 'pf-certificate-logo-width', '140' );
		preview.style.setProperty( '--pf-certificate-logo-width', logoWidth + 'px' );
		const logoWidthOutput = document.getElementById( 'pf-certificate-logo-width-output' );
		if ( logoWidthOutput ) { logoWidthOutput.textContent = logoWidth + ' px'; }
		preview.classList.toggle( 'is-portrait', value( 'pf-certificate-orientation', 'landscape' ) === 'portrait' );
		preview.querySelector( '.pf-preview-title' ).textContent = value( 'pf-certificate-title', 'Certificate of Completion' );
		preview.querySelector( '.pf-preview-issuer' ).textContent = value( 'pf-certificate-issuer', document.title.split( '‹' )[ 0 ].trim() );
		preview.querySelector( '.pf-preview-heading' ).textContent = value( 'pf-certificate-heading', 'This certifies that' );
		preview.querySelector( '.pf-preview-completion' ).textContent = value( 'pf-certificate-completion-text', 'has successfully completed' );
		preview.querySelector( '.pf-preview-signer span' ).textContent = value( 'pf-certificate-signatory-name', 'Signatory Name' );
		preview.querySelector( '.pf-preview-signer small' ).textContent = value( 'pf-certificate-signatory-title', 'Title' );
	}

	document.addEventListener( 'input', function ( event ) {
		if ( event.target.closest( '.pf-certificate-branding-fields' ) ) { updatePreview(); }
	} );
	document.addEventListener( 'change', function ( event ) {
		if ( event.target.closest( '.pf-certificate-branding-fields' ) ) { updatePreview(); }
	} );

	document.querySelectorAll( '.pf-certificate-media-field' ).forEach( function ( field ) {
		const input = field.querySelector( 'input[type="hidden"]' );
		const mediaPreview = field.querySelector( '.pf-certificate-media-preview' );
		const remove = field.querySelector( '.pf-certificate-remove-media' );
		field.querySelector( '.pf-certificate-select-media' ).addEventListener( 'click', function () {
			const frame = wp.media( { title: field.dataset.mediaTitle, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				const url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				input.value = attachment.id;
				const signatureRemove = field.querySelector( '.pf-certificate-signature-remove' );
				if ( signatureRemove ) { signatureRemove.value = '0'; }
				mediaPreview.replaceChildren( Object.assign( document.createElement( 'img' ), { src: url, alt: '' } ) );
				remove.hidden = false;
				const target = input.id === 'pf-certificate-logo-id' ? '.pf-preview-logo' : '.pf-preview-signature-image';
				document.querySelector( target ).replaceChildren( Object.assign( document.createElement( 'img' ), { src: url, alt: '' } ) );
			} );
			frame.open();
		} );
		remove.addEventListener( 'click', function () {
			input.value = '';
			const signatureRemove = field.querySelector( '.pf-certificate-signature-remove' );
			if ( signatureRemove ) { signatureRemove.value = '1'; }
			mediaPreview.textContent = mediaPreview.dataset.placeholder;
			remove.hidden = true;
			const target = input.id === 'pf-certificate-logo-id' ? '.pf-preview-logo' : '.pf-preview-signature-image';
			document.querySelector( target ).replaceChildren();
		} );
	} );
	updatePreview();
}() );
