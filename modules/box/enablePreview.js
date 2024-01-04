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

	const action = mw.config.get( 'wgAction' );
	if ( ( action === 'edit' || action === 'submit' ) &&
		// eslint-disable-next-line no-jquery/no-class-state
		!$( document.documentElement ).hasClass( 've-active' )
	) {
		mw.loader.using( 'ext.kartographer.preview' );
	}

}() );
