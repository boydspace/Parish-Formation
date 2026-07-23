( function ( $ ) {
	'use strict';

	const originalEdit = inlineEditPost.edit;

	inlineEditPost.edit = function ( postId ) {
		originalEdit.apply( this, arguments );

		let id = 0;

		if ( typeof postId === 'object' ) {
			id = parseInt( this.getId( postId ), 10 );
		} else {
			id = parseInt( postId, 10 );
		}

		if ( ! id ) {
			return;
		}

		const data = $( '#post-' + id ).find( '.pf-lesson-quick-edit-data' );
		const editRow = $( '#edit-' + id );

		editRow.find( '[name="pf_course_id"]' ).val( data.data( 'course-id' ) );
		editRow.find( '[name="pf_lesson_order"]' ).val( data.data( 'lesson-order' ) );
		editRow.find( '[name="pf_is_required"]' ).prop( 'checked', Number( data.data( 'is-required' ) ) === 1 );
	};
} )( jQuery );
