/**
 * # Preview mode
 *
 * Module executing code to load {@link Kartographer.Preview ext.kartographer.preview}
 * when it detects preview edit mode.
 *
 * @class Kartographer.Box.enablePreview
 * @singleton
 * @private
 */
module.exports = ( function () {

	// eslint-disable-next-line no-jquery/no-class-state
	if ( mw.config.get( 'wgAction' ) === 'submit' && !$( document.documentElement ).hasClass( 've-active' ) ) {
		mw.loader.using( 'ext.kartographer.preview' );
	}

}() );
