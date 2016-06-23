/* globals module */
module.enablePreview = ( function ( $, mw ) {

	if ( mw.config.get( 'wgAction' ) === 'submit' && !$( 'html' ).hasClass( 've-active' ) ) {
		mw.loader.using( 'ext.kartographer.preview' );
	}

} )( jQuery, mediaWiki );
