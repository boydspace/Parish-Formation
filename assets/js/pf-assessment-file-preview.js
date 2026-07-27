( function () {
	'use strict';
	let modal, image, title, opener;

	function closeModal() {
		if ( ! modal || modal.hidden ) { return; }
		modal.hidden = true;
		image.removeAttribute( 'src' );
		document.body.classList.remove( 'pf-file-preview-open' );
		if ( opener ) { opener.focus(); }
	}

	function createModal() {
		if ( modal ) { return; }
		modal = document.createElement( 'div' );
		modal.className = 'pf-file-preview-modal';
		modal.hidden = true;
		modal.innerHTML = '<div class="pf-file-preview-backdrop"></div><div class="pf-file-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="pf-file-preview-title"><div class="pf-file-preview-header"><h2 id="pf-file-preview-title"></h2><button type="button" class="button pf-file-preview-close" aria-label="Close preview">Close</button></div><div class="pf-file-preview-body"><img alt="" /></div></div>';
		document.body.appendChild( modal );
		image = modal.querySelector( 'img' );
		title = modal.querySelector( 'h2' );
		modal.querySelectorAll( '.pf-file-preview-close, .pf-file-preview-backdrop' ).forEach( function ( control ) { control.addEventListener( 'click', closeModal ); } );
	}

	function openModal( button ) {
		createModal();
		opener = button;
		title.textContent = button.dataset.previewTitle || 'Image preview';
		image.alt = title.textContent;
		image.src = button.dataset.previewUrl;
		modal.hidden = false;
		document.body.classList.add( 'pf-file-preview-open' );
		modal.querySelector( '.pf-file-preview-close' ).focus();
	}

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.pf-assessment-file-preview-button' );
		if ( button ) { openModal( button ); }
	} );
	document.addEventListener( 'keydown', function ( event ) { if ( 'Escape' === event.key ) { closeModal(); } } );
}() );
