jQuery( document ).ready( function( $ ) {
    $( '.wassce-checker-pro-popup-overlay' ).show();
    $( '.wassce-checker-pro-popup-close' ).on( 'click', function( e ) {
        e.preventDefault();
        $( '.wassce-checker-pro-popup-overlay' ).hide();
    } );
} );
