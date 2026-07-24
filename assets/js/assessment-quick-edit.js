( function ( $ ) {
	'use strict';

	const originalEdit = inlineEditPost.edit;

	inlineEditPost.edit = function ( postId ) {
		originalEdit.apply( this, arguments );

		const id = parseInt( typeof postId === 'object' ? this.getId( postId ) : postId, 10 );
		if ( ! id ) {
			return;
		}

		const courseId = $( '#post-' + id ).find( '.pf-assessment-quick-edit-data' ).data( 'course-id' );
		$( '#edit-' + id ).find( '[name="pf_assessment_course"]' ).val( courseId || 0 );
	};
}( jQuery ) );
