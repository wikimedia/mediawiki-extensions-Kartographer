/* globals module */
/**
 * Module executing code to load {@link Kartographer.Preview ext.kartographer.preview}
 * when it detects preview edit mode.
 *
 * @alias enablePreview
 * @class Kartographer.Live.enablePreview
 * @private
 */
module.enablePreview = ( function ( $, mw ) {

	if ( mw.config.get( 'wgAction' ) === 'submit' && !$( 'html' ).hasClass( 've-active' ) ) {
		mw.loader.using( 'ext.kartographer.preview' );
	}

} )( jQuery, mediaWiki );
