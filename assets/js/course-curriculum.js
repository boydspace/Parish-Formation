( function ( $ ) {
	'use strict';

	const $list = $( '#pf-course-curriculum' );
	const $status = $( '#pf-curriculum-save-status' );
	if ( ! $list.length ) {
		return;
	}

	$list.sortable( {
		handle: '.pf-curriculum-handle',
		axis: 'y',
		update: function () {
			const items = $list.children( '.pf-curriculum-item' ).map( function () {
				return { id: $( this ).data( 'item-id' ), type: $( this ).data( 'item-type' ) };
			} ).get();
			$status.removeClass( 'pf-curriculum-error pf-curriculum-success' ).text( pfCourseCurriculum.saving );
			$.post( window.ajaxurl, {
				action: 'pf_save_curriculum_order',
				nonce: pfCourseCurriculum.nonce,
				course_id: $list.data( 'course-id' ),
				items: items
			} ).done( function ( response ) {
				if ( response.success ) {
					$status.addClass( 'pf-curriculum-success' ).text( response.data.message );
				} else {
					$status.addClass( 'pf-curriculum-error' ).text( response.data && response.data.message ? response.data.message : pfCourseCurriculum.error );
				}
			} ).fail( function ( response ) {
				const message = response.responseJSON && response.responseJSON.data && response.responseJSON.data.message;
				$status.addClass( 'pf-curriculum-error' ).text( message || pfCourseCurriculum.error );
			} );
		}
	} );
}( jQuery ) );
