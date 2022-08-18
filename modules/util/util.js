/**
 * Kartographer utility functions.
 *
 * @alternateClassName ext.kartographer.util
 * @class Kartographer.Util
 * @singleton
 */
module.exports = {
	/**
	 * @return {string}
	 */
	getDefaultLanguage: function () {
		return mw.config.get( 'wgKartographerUsePageLanguage' ) ?
			mw.config.get( 'wgPageContentLanguage' ) :
			'local';
	}
};
