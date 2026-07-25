( function () {
	'use strict';
	const config = window.pfAdminDashboard;
	const dashboard = document.querySelector( '.pf-dashboard' );
	if ( ! config || ! dashboard ) { return; }
	const button = document.getElementById( 'pf-dashboard-refresh' );
	const content = document.getElementById( 'pf-dashboard-content' );
	const status = document.getElementById( 'pf-dashboard-status' );
	if ( ! button || ! content || ! status ) { return; }
	button.addEventListener( 'click', async function () {
		button.disabled = true; dashboard.setAttribute( 'aria-busy', 'true' ); status.textContent = config.refreshingMessage;
		try {
			const endpoint = config.ajaxUrl + '?action=pf_refresh_dashboard&nonce=' + encodeURIComponent( config.nonce );
			const response = await fetch( endpoint, { credentials: 'same-origin' } );
			const result = await response.json();
			if ( ! result.success ) { throw new Error( result.data && result.data.message ? result.data.message : config.errorMessage ); }
			content.innerHTML = result.data.html; status.textContent = 'Refreshed at ' + result.data.refreshed + '.';
		} catch ( error ) { status.textContent = error.message || config.errorMessage; }
		finally { button.disabled = false; dashboard.removeAttribute( 'aria-busy' ); }
	} );
}() );
