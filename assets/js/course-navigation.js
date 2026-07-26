( function () {
	'use strict';

	let activeRequest = null;

	function announceError( layout, message ) {
		let alert = layout.querySelector( '.pf-course-navigation-error' );
		if ( ! alert ) {
			alert = document.createElement( 'div' );
			alert.className = 'pf-course-navigation-error uk-alert uk-alert-danger';
			alert.setAttribute( 'role', 'alert' );
			layout.prepend( alert );
		}
		alert.textContent = message;
		alert.setAttribute( 'tabindex', '-1' );
		alert.focus();
	}

	function parseCourseUrl( url ) {
		const parts = new URL( url, window.location.href ).pathname.split( '/' ).filter( Boolean );
		const courseIndex = parts.lastIndexOf( 'course' );
		if ( courseIndex < 0 || ! parts[ courseIndex + 1 ] ) {
			return null;
		}
		return {
			courseSlug: decodeURIComponent( parts[ courseIndex + 1 ] ),
			itemType: parts[ courseIndex + 2 ] || '',
			itemSlug: parts[ courseIndex + 3 ] ? decodeURIComponent( parts[ courseIndex + 3 ] ) : '',
			baseUrl: window.location.origin + '/' + parts.slice( 0, courseIndex ).join( '/' ) + '/'
		};
	}

	function loadCourseView( url, pushHistory ) {
		const route = parseCourseUrl( url );
		const currentLayout = document.querySelector( '.pf-learning-layout' );
		if ( ! route || ! currentLayout ) {
			window.location.href = url;
			return;
		}
		if ( activeRequest ) {
			activeRequest.abort();
		}
		activeRequest = new AbortController();
		currentLayout.classList.add( 'pf-is-loading' );
		currentLayout.setAttribute( 'aria-busy', 'true' );
		const endpoint = new URL( pfCourseNavigation.endpoint );
		endpoint.searchParams.set( 'course_slug', route.courseSlug );
		endpoint.searchParams.set( 'item_type', route.itemType );
		endpoint.searchParams.set( 'item_slug', route.itemSlug );
		endpoint.searchParams.set( 'base_url', route.baseUrl );

		window.fetch( endpoint.toString(), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': pfCourseNavigation.nonce },
			signal: activeRequest.signal
		} ).then( function ( response ) {
			return response.json().then( function ( body ) {
				if ( ! response.ok ) {
					throw new Error( body.message || pfCourseNavigation.error );
				}
				return body;
			} );
		} ).then( function ( data ) {
			const template = document.createElement( 'template' );
			template.innerHTML = data.html.trim();
			const newLayout = template.content.querySelector( '.pf-learning-layout' );
			if ( ! newLayout ) {
				throw new Error( pfCourseNavigation.error );
			}
			currentLayout.replaceWith( newLayout );
			if ( pushHistory ) {
				window.history.pushState( { parishFormationCourse: true }, '', url );
			}
			const titleParts = document.title.split( ' – ' );
			titleParts[ 0 ] = data.title;
			document.title = titleParts.join( ' – ' );
			newLayout.focus( { preventScroll: true } );
			const reducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			window.scrollTo( { top: newLayout.getBoundingClientRect().top + window.scrollY - 32, behavior: reducedMotion ? 'auto' : 'smooth' } );
		} ).catch( function ( error ) {
			if ( error.name === 'AbortError' ) {
				return;
			}
			currentLayout.classList.remove( 'pf-is-loading' );
			currentLayout.removeAttribute( 'aria-busy' );
			announceError( currentLayout, error.message || pfCourseNavigation.error );
		} ).finally( function () {
			activeRequest = null;
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		const link = event.target.closest( '.pf-learning-layout a' );
		if ( ! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target ) {
			return;
		}
		if ( ! parseCourseUrl( link.href ) ) {
			return;
		}
		event.preventDefault();
		loadCourseView( link.href, true );
	} );

	document.addEventListener( 'submit', function ( event ) {
		const form = event.target.closest( '.parish-formation-complete-lesson' );
		if ( ! form || ! window.fetch ) {
			return;
		}
		event.preventDefault();
		const submitter = event.submitter || form.querySelector( 'button[type="submit"]' );
		const buttons = form.querySelectorAll( 'button[type="submit"]' );
		buttons.forEach( function ( button ) { button.disabled = true; } );
		window.fetch( pfCourseNavigation.lessonEndpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': pfCourseNavigation.nonce },
			body: JSON.stringify( {
				enrollment_id: form.elements.enrollment_id.value,
				course_id: form.elements.course_id.value,
				lesson_id: form.elements.lesson_id.value,
				progress_action: submitter.value,
				base_url: form.elements.formation_base_url.value
			} )
		} ).then( function ( response ) {
			return response.json().then( function ( body ) {
				if ( ! response.ok ) { throw new Error( body.message || pfCourseNavigation.error ); }
				return body;
			} );
		} ).then( function ( data ) {
			loadCourseView( data.nextUrl, true );
		} ).catch( function ( error ) {
			buttons.forEach( function ( button ) { button.disabled = false; } );
			const layout = form.closest( '.pf-learning-layout' );
			if ( layout ) {
				announceError( layout, error.message || pfCourseNavigation.error );
			}
		} );
	} );

	window.addEventListener( 'popstate', function () {
		if ( parseCourseUrl( window.location.href ) && document.querySelector( '.pf-learning-layout' ) ) {
			loadCourseView( window.location.href, false );
		} else {
			window.location.reload();
		}
	} );
}() );
