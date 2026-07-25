( function () {
	'use strict';
	const config = window.pfAdminHubs;
	const hub = document.querySelector( '.pf-admin-hub' );
	if ( ! config || ! hub ) { return; }
	const content = hub.querySelector( '.pf-admin-hub-content' );
	const status = hub.querySelector( '.pf-admin-hub-status' );

	function executeScripts( root ) {
		root.querySelectorAll( 'script' ).forEach( function ( oldScript ) {
			const script = document.createElement( 'script' );
			Array.from( oldScript.attributes ).forEach( function ( attribute ) { script.setAttribute( attribute.name, attribute.value ); } );
			script.textContent = oldScript.textContent;
			oldScript.replaceWith( script );
		} );
	}

	async function loadTab( tab, url, updateHistory ) {
		hub.setAttribute( 'aria-busy', 'true' );
		status.textContent = '';
		try {
			const endpoint = config.ajaxUrl + '?action=pf_load_admin_hub_tab&hub=' + encodeURIComponent( hub.dataset.hub ) + '&tab=' + encodeURIComponent( tab ) + '&nonce=' + encodeURIComponent( config.nonce );
			const response = await fetch( endpoint, { credentials: 'same-origin' } );
			const result = await response.json();
			if ( ! result.success ) { throw new Error( result.data && result.data.message ? result.data.message : config.errorMessage ); }
			if ( window.wp && window.wp.editor && document.getElementById( 'pf_notification_template_body' ) ) { window.wp.editor.remove( 'pf_notification_template_body' ); }
			content.innerHTML = result.data.html;
			executeScripts( content );
			document.querySelectorAll( '.pf-admin-hub-tab' ).forEach( function ( item ) { item.classList.toggle( 'nav-tab-active', item.dataset.tab === tab ); } );
			if ( updateHistory ) { window.history.pushState( { pfHubTab: tab }, '', url ); }
			if ( typeof window.pfInitializeNotificationAdmin === 'function' ) { window.pfInitializeNotificationAdmin(); }
		} catch ( error ) {
			status.textContent = error.message || config.errorMessage;
		} finally {
			hub.removeAttribute( 'aria-busy' );
		}
	}

	document.querySelectorAll( '.pf-admin-hub-tab' ).forEach( function ( tab ) { tab.addEventListener( 'click', function ( event ) { event.preventDefault(); loadTab( tab.dataset.tab, tab.href, true ); } ); } );
	window.addEventListener( 'popstate', function () { const params = new URLSearchParams( window.location.search ); const tab = params.get( 'hub_tab' ); if ( tab ) { loadTab( tab, window.location.href, false ); } } );
}() );
