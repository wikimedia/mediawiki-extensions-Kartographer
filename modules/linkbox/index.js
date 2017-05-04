/**
 * **Resource Loader module: {@link Kartographer.Linkbox ext.kartographer.linkbox}**
 *
 * @alias ext.kartographer.linkbox
 * @class Kartographer.Linkbox
 * @singleton
 */
module.exports = {

	/**
	 * @type {Kartographer.Linkbox.LinkClass}
	 * @ignore
	 */
	Link: module.Link,

	/**
	 * Use this method to create a {@link Kartographer.Linkbox.LinkClass Link}
	 * object.
	 *
	 * See {@link Kartographer.Linkbox.LinkClass#constructor} for the list of options.
	 *
	 * @param {Object} options
	 * @return {Kartographer.Linkbox.LinkClass}
	 */
	link: function ( options ) {
		var Link = this.Link;
		return new Link( options );
	}
};
