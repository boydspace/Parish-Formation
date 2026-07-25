( function () {
	'use strict';

	const tabs = document.querySelectorAll( '.pf-notification-template-tab' );
	const panel = document.getElementById( 'pf-notification-template-panel' );
	const designTab = document.getElementById( 'pf-notification-design-tab' );
	const designPanel = document.getElementById( 'pf-notification-design-panel' );

	if ( ! tabs.length || ! panel || typeof pfNotificationAdmin === 'undefined' ) {
		return;
	}

	const setEditorContent = function ( content ) {
		if ( window.tinymce && window.tinymce.get( 'pf_notification_template_body' ) ) {
			window.tinymce.get( 'pf_notification_template_body' ).setContent( content );
		}
		const textarea = document.getElementById( 'pf_notification_template_body' );
		if ( textarea ) {
			textarea.value = content;
		}
	};

	const updateNonceActions = function ( type ) {
		document.querySelectorAll( '#pf-notification-template-panel input[name="template_type"]' ).forEach( function ( input ) {
			input.value = type;
		} );
		// Nonces are tied to the template. Fetching fresh form markup would recreate the editor,
		// so actions use the page nonce and validate the selected type server-side after AJAX switches.
		document.querySelectorAll( '#pf-notification-template-panel input[name="_wpnonce"]' ).forEach( function ( input ) {
			input.value = pfNotificationAdmin.actionNonces[ type ][ input.closest( 'form' ).id ];
		} );
	};

	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			const type = tab.dataset.templateType;
			if ( designPanel ) {
				designPanel.style.display = 'none';
			}
			panel.style.display = '';
			if ( designTab ) {
				designTab.classList.remove( 'nav-tab-active' );
			}
			panel.setAttribute( 'aria-busy', 'true' );
			fetch( pfNotificationAdmin.ajaxUrl + '?action=pf_load_notification_template&template_type=' + encodeURIComponent( type ) + '&_ajax_nonce=' + encodeURIComponent( pfNotificationAdmin.nonce ), { credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) {
					if ( ! response.success ) {
						throw new Error( 'load_failed' );
					}
					document.querySelectorAll( '.pf-notification-template-tab' ).forEach( function ( item ) { item.classList.toggle( 'nav-tab-active', item.dataset.templateType === type ); } );
					document.getElementById( 'pf-notification-template-title' ).textContent = response.data.label;
					document.getElementById( 'pf-template-subject' ).value = response.data.subject;
					document.getElementById( 'pf-notification-placeholders' ).textContent = response.data.placeholders;
					document.getElementById( 'pf-notification-template-preview' ).innerHTML = response.data.preview;
					setEditorContent( response.data.body );
					updateNonceActions( type );
					window.history.replaceState( {}, '', tab.href );
				} )
				.catch( function () { window.alert( pfNotificationAdmin.errorMessage ); } )
				.finally( function () { panel.removeAttribute( 'aria-busy' ); } );
		} );
	} );

	if ( designTab && designPanel ) {
		designTab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			document.querySelectorAll( '.pf-notification-template-tab' ).forEach( function ( item ) { item.classList.remove( 'nav-tab-active' ); } );
			designTab.classList.add( 'nav-tab-active' );
			panel.style.display = 'none';
			designPanel.style.display = '';
			window.history.replaceState( {}, '', designTab.href );
		} );
	}

	const designControls = document.querySelectorAll( '.pf-design-control' );
	const previewPage = document.getElementById( 'pf-design-preview-page' );
	const previewContainer = document.getElementById( 'pf-design-preview-container' );
	const previewHeader = document.getElementById( 'pf-design-preview-header' );
	const previewContent = document.getElementById( 'pf-design-preview-content' );
	const previewFooter = document.getElementById( 'pf-design-preview-footer' );
	const previewLink = document.getElementById( 'pf-design-preview-link' );
	const previewLogo = document.getElementById( 'pf-design-preview-logo' );

	const updateDesignPreview = function ( control ) {
		const role = control.dataset.preview;
		const value = control.value;
		if ( role === 'header-name' ) document.getElementById( 'pf-design-preview-header-name' ).textContent = value;
		if ( role === 'logo' && previewLogo ) { previewLogo.src = value; previewLogo.style.display = value ? 'block' : 'none'; }
		if ( role === 'page-color' && previewPage ) previewPage.style.backgroundColor = value;
		if ( role === 'header-color' && previewHeader ) previewHeader.style.backgroundColor = value;
		if ( role === 'header-text-color' && previewHeader ) previewHeader.style.color = value;
		if ( role === 'content-color' ) { if ( previewContainer ) previewContainer.style.backgroundColor = value; if ( previewContent ) previewContent.style.backgroundColor = value; }
		if ( role === 'text-color' && previewContent ) previewContent.style.color = value;
		if ( role === 'link-color' && previewLink ) previewLink.style.color = value;
		if ( role === 'footer-color' && previewFooter ) previewFooter.style.backgroundColor = value;
		if ( role === 'footer-text' ) document.getElementById( 'pf-design-preview-footer-text' ).textContent = value;
		if ( role === 'contact-text' ) { const contact = document.getElementById( 'pf-design-preview-contact' ); contact.textContent = value ? '\n' + value : ''; contact.style.whiteSpace = 'pre-line'; }
		if ( role === 'width' && previewContainer ) { previewContainer.style.maxWidth = value + 'px'; document.getElementById( 'pf-design-width-output' ).textContent = value + 'px'; }
	};

	designControls.forEach( function ( control ) {
		control.addEventListener( 'input', function () { updateDesignPreview( control ); } );
	} );

	const selectLogo = document.getElementById( 'pf-design-select-logo' );
	if ( selectLogo && window.wp && window.wp.media ) {
		selectLogo.addEventListener( 'click', function () {
			const frame = window.wp.media( { title: 'Select Email Logo', button: { text: 'Use this logo' }, multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				const logoInput = document.getElementById( 'pf-design-logo-url' );
				logoInput.value = frame.state().get( 'selection' ).first().toJSON().url;
				updateDesignPreview( logoInput );
			} );
			frame.open();
		} );
	}

	if ( previewLink ) {
		previewLink.addEventListener( 'click', function ( event ) { event.preventDefault(); } );
	}
}() );
