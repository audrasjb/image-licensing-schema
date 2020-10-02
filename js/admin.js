( function( $ ) {
	"use strict";

	$( window ).load( function() {

		$( '.imalisch_toggle_license_details' ).hide();

		$( '.imalisch-button-link' ).on( 'click', function( e ) {
			e.preventDefault();
			$( this ).parent().next( '.imalisch_toggle_license_details' ).slideToggle();
		} );

	} );

} ) ( jQuery );