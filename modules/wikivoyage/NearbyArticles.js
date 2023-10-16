/**
 * Module to fetch nearby articles.
 *
 * @deprecated since 1.41
 * @borrows Kartographer.Wikivoyage.NearbyArticles as NearbyArticles
 * @class Kartographer.Wikivoyage.NearbyArticles
 * @singleton
 */

module.exports = {
	/**
	 * @ignore
	 * @deprecated since 1.41
	 */
	// TODO: Remove when there's no usage anywhere see T332785
	setConfig: function () {
		mw.log.warn(
			'Use of new NearbyArticles.setConfig() is deprecated. It got replaced and should not be used anymore.'
		);
		mw.track( 'mw.deprecate', 'NearbyArticles.setConfig' );
	}
};
