/**
 * **Resource Loader module: {@link Kartographer.Box ext.kartographer.box}**
 *
 * @borrows Kartographer.Box as ext.kartographer.box
 * @class Kartographer.Box
 * @singleton
 */
L.kartographer = module.exports = {
	/**
	 * @type {Kartographer.Box.OpenFullScreenControl}
	 */
	OpenFullScreenControl: require( './openfullscreen_control.js' ),

	/**
	 * @type {Kartographer.Box.ScaleControl}
	 */
	ScaleControl: require( './scale_control.js' ),

	/**
	 * @type {Kartographer.Box.MapClass}
	 */
	Map: require( './Map.js' ).Map,

	/**
	 * Use this method to create a {@link Kartographer.Box.MapClass Map}
	 * object.
	 *
	 * See {@link Kartographer.Box.MapClass#constructor} for the list of options.
	 *
	 * @param {Object} options
	 * @return {Kartographer.Box.MapClass}
	 * @memberof Kartographer.Box
	 */
	map: require( './Map.js' ).map
};
