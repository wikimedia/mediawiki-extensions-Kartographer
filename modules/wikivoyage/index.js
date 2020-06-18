/**
 * Wikivoyage customization.
 *
 * @alternateClassName ext.kartographer.wikivoyage
 * @class Kartographer.Wikivoyage
 * @singleton
 */
module.exports = {
	/**
	 * @type {Kartographer.Wikivoyage.wikivoyage}
	 */
	wikivoyage: require( './wikivoyage.js' ),

	/**
	 * @type {Kartographer.Wikivoyage.WVMap}
	 */
	WVMap: require( './WVMap.js' ),

	/**
	 * @type {Kartographer.Wikivoyage.NearbyArticles}
	 */
	NearbyArticles: require( './NearbyArticles.js' )
};
