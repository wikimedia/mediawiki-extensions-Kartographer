/* globals module */
/**
 * **Resource Loader module: {@link Kartographer.Box ext.kartographer.box}**
 *
 * @alias ext.kartographer.box
 * @class Kartographer.Box
 * @singleton
 */
L.kartographer = module.exports = {
	/**
	 * @type {Kartographer.Box.OpenFullScreenControl}
	 * @ignore
	 */
	OpenFullScreenControl: module.OpenFullScreenControl,

	/**
	 * @type {Kartographer.Box.ScaleControl}
	 * @ignore
	 */
	ScaleControl: module.ScaleControl,

	/**
	 * @type {Kartographer.Box.MWMap}
	 * @ignore
	 */
	Map: module.Map,

	/**
	 * Use this method to create a {@link Kartographer.Box.MapClass Map}
	 * object.
	 *
	 * See {@link Kartographer.Box.MapClass#constructor} for the list of options.
	 *
	 * @return {Kartographer.Box.MapClass}
	 * @member Kartographer.Box
	 */
	map: function ( options ) {
		var Map = this.Map;
		return new Map( options );
	},

	/**
	 * @type {Kartographer.Box.LinkClass}
	 * @ignore
	 */
	Link: module.Link,

	/**
	 * Use this method to create a {@link Kartographer.Box.LinkClass Link}
	 * object.
	 *
	 * See {@link Kartographer.Box.LinkClass#constructor} for the list of options.
	 *
	 * @return {Kartographer.Box.LinkClass}
	 */
	link: function ( options ) {
		var Link = this.Link;
		return new Link( options );
	}
};
