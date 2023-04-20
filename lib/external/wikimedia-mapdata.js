'use strict';

/**
 * Factory function returning an instance.
 *
 * @param {Function} extend Reference to e.g. {@see jQuery.extend}
 * @param {Function} createResolvedPromise
 * @param {Function} mwApi Reference to {@see mw.Api.get}
 * @param {Object} [clientStore] External cache for groups, supplied by the caller.
 * @return {Kartographer.Data.MapdataLoader}
 */
var MapdataLoader = function (
	extend,
	createResolvedPromise,
	mwApi,
	clientStore
) {
	clientStore = clientStore || {};

	/**
	 * Fetches GeoJSON content for a mapframe or maplink tag from the Kartographer MediaWiki API
	 *
	 * @class Kartographer.Data.MapdataLoader
	 * @constructor
	 */
	var MapdataLoader = function () {};

	/**
	 * @param {string[]} groupIds
	 * @param {string} [title] Will be ignored if revid is supplied.
	 * @param {string|false} [revid] Either title or revid must be set. If false
	 * or missing, falls back to a title-only request.
	 * @param {string|false} [lang] Language, used for variants
	 * @return {Promise<Object>} Resolves to the returned mapdata, or rejects.
	 */
	MapdataLoader.prototype.fetchGroups = function ( groupIds, title, revid, lang ) {
		if ( !groupIds.length ) {
			return createResolvedPromise( {} );
		}
		var cachedResults = {};
		var fetchGroups = [];
		groupIds.forEach( function ( groupId ) {
			if ( clientStore[ groupId ] ) {
				cachedResults[ groupId ] = clientStore[ groupId ];
			} else {
				fetchGroups.push( groupId );
			}
		} );
		if ( fetchGroups.length === 0 ) {
			return createResolvedPromise( cachedResults );
		}

		var params = {
			action: 'query',
			formatversion: '2',
			titles: title,
			revids: revid,
			prop: 'mapdata',
			mpdlimit: 'max',
			mpdgroups: fetchGroups
		};
		delete params[ revid ? 'titles' : 'revids' ];
		if ( lang ) {
			params.uselang = lang;
		}

		return mwApi( params ).then( function ( data ) {
			if ( data && data.error ) {
				throw new Error( 'Mapdata error: ' + ( data.error.info || data.error.code ) );
			}
			if ( !data || !data.query || !data.query.pages ||
				!data.query.pages[ 0 ] || !data.query.pages[ 0 ].mapdata
			) {
				throw new Error( 'Invalid mapdata response for ' + JSON.stringify( params ) );
			}
			return extend( cachedResults, JSON.parse( data.query.pages[ 0 ].mapdata ) );
		} );
	};

	return new MapdataLoader();
};

/**
 * @class Kartographer.Data.Group
 * @property {Object|Object[]|null} geoJSON
 * @property {string|null} name The group key in mapdata.
 * @property {boolean} failed Flag is true if the group failed to fully load.
 * @property {Error|null} failureReason Details about a failure, if any.
 *
 * @constructor
 * @param {Object|Object[]} [geoJSON] The group's geometry, or empty for
 *   incomplete ExternalData.
 */
var Group$1 = function ( geoJSON ) {
	this.geoJSON = geoJSON || null;
	this.name = null;
	this.failed = false;
	this.failureReason = null;
};

/**
 * @return {Object|null} Group GeoJSON
 */
Group$1.prototype.getGeoJSON = function () {
	return this.geoJSON;
};

/**
 * Set data to flag this group as failed.
 *
 * @param {Error} err
 */
Group$1.prototype.fail = function ( err ) {
	this.failed = true;
	this.failureReason = err;
};

var Group_1 = Group$1;

/**
 * Factory function returning an instance.
 *
 * @param {Function} getJSON
 * @param {Function} createPromise
 * @return {Kartographer.Data.ExternalDataLoader}
 */
var ExternalDataLoader$1 = function (
	getJSON,
	createPromise
) {
	/**
	 * @class Kartographer.Data.ExternalDataLoader
	 * @constructor
	 */
	var ExternalDataLoader = function () {};

	/**
	 * @param {Object} geoJSON
	 * @return {Promise} Resolved with the raw, externally-fetched data.
	 */
	ExternalDataLoader.prototype.fetch = function ( geoJSON ) {
		if ( !geoJSON || !geoJSON.url ) {
			return createPromise( function ( _resolve, reject ) {
				reject( new Error( 'ExternalData has no url' ) );
			} );
		}

		return getJSON( geoJSON.url );
	};

	return new ExternalDataLoader();
};

/**
 * Factory function returning an instance.
 *
 * @param {Function} isPlainObject
 * @param {Function} isEmptyObject
 * @param {Function} extend
 * @return {Object}
 */
var ExternalDataParser$1 = function (
	isPlainObject,
	isEmptyObject,
	extend
) {

	/**
	 * @param {Object|null} geoJSON
	 * @return {boolean} True if this is an ExternalData
	 * @public
	 */
	function isExternalData( geoJSON ) {
		return isPlainObject( geoJSON ) &&
			geoJSON.type === 'ExternalData';
	}

	/**
	 * Transform returned GeoJSON depending on the type of ExternalData.
	 *
	 * FIXME: Wouldn't this be a job for the mapdata API?
	 *
	 * @param {Object} geoJSON (modified in-place)
	 * @param {Object} externalData fetched ExternalData blob
	 * @return {Object} Expanded geoJSON
	 * @public
	 */
	function parse( geoJSON, externalData ) {
		var baseProps = geoJSON.properties,
			geometry,
			coordinates,
			i, j;

		switch ( geoJSON.service ) {

			case 'page':
				extend( geoJSON, externalData.jsondata.data );
				break;

			case 'geomask':
				// Mask-out the entire world 10 times east and west,
				// and add each result geometry as a hole
				coordinates = [ [
					[ 3600, -180 ],
					[ 3600, 180 ],
					[ -3600, 180 ],
					[ -3600, -180 ],
					[ 3600, -180 ]
				] ];
				for ( i = 0; i < externalData.features.length; i++ ) {
					geometry = externalData.features[ i ].geometry;
					if ( !geometry ) {
						continue;
					}
					// Only add the very first (outer) polygon
					switch ( geometry.type ) {
						case 'Polygon':
							coordinates.push( geometry.coordinates[ 0 ] );
							break;
						case 'MultiPolygon':
							for ( j = 0; j < geometry.coordinates.length; j++ ) {
								coordinates.push( geometry.coordinates[ j ][ 0 ] );
							}
							break;
					}
				}
				geoJSON.type = 'Feature';
				geoJSON.geometry = {
					type: 'Polygon',
					coordinates: coordinates
				};
				break;

			case 'geoshape':
			case 'geopoint':
			case 'geoline':

				// HACK: workaround for T144777 - we should be using topojson instead
				extend( geoJSON, externalData );

				// geoJSON.type = 'FeatureCollection';
				// geoJSON.features = [];
				// $.each( externalData.objects, function ( key ) {
				// geoJSON.features.push( topojson.feature( externalData, externalData.objects[ key ] ) );
				// } );

				// Each feature returned from geoshape service may contain "properties"
				// If externalData element has properties, merge with properties in the feature
				if ( baseProps ) {
					for ( i = 0; i < geoJSON.features.length; i++ ) {
						if ( isEmptyObject( geoJSON.features[ i ].properties ) ) {
							geoJSON.features[ i ].properties = baseProps;
						} else {
							geoJSON.features[ i ].properties = extend( {}, baseProps,
								geoJSON.features[ i ].properties );
						}
					}
				}
				break;

			default:
				throw new Error( 'Unknown externalData service "' + geoJSON.service + '"' );
		}

		return geoJSON;
	}

	return {
		isExternalData: isExternalData,
		parse: parse
	};
};

var dataLoaderLib = MapdataLoader;
var Group = Group_1;
var ExternalDataLoader = ExternalDataLoader$1;
var ExternalDataParser = ExternalDataParser$1;

/**
 * @param {Array|Object} data
 * @return {Array} Data wrapped in an array if necessary.
 */
function toArray( data ) {
	if ( Array.isArray( data ) ) {
		return data;
	} else {
		return [ data ];
	}
}

/**
 * Factory function returning an instance.
 *
 * @param {Object} wrappers
 * @param {Object} [wrappers.clientStore] External cache of groups, supplied by the caller.
 * @param {Function} wrappers.createPromise
 * @param {Function} wrappers.extend Reference to e.g. {@see jQuery.extend}
 * @param {Function} wrappers.getJSON Reference to e.g. {@see jQuery.getJSON}
 * @param {Function} wrappers.isEmptyObject Reference to e.g. {@see jQuery.isEmptyObject}
 * @param {Function} wrappers.isPlainObject Reference to e.g. {@see jQuery.isPlainObject}
 * @param {Function} wrappers.mwApi Reference to {@see mw.Api.get}
 * @param {Function} wrappers.whenAllPromises Reference a function like {@see Promise.all}
 * @return {Object}
 */
var DataManager = function ( wrappers ) {

	var createResolvedPromise = function ( value ) {
			return wrappers.createPromise( function ( resolve ) {
				resolve( value );
			} );
		},
		dataLoader = dataLoaderLib(
			wrappers.extend,
			createResolvedPromise,
			wrappers.mwApi,
			wrappers.clientStore
		),
		externalDataLoader = ExternalDataLoader(
			wrappers.getJSON,
			wrappers.createPromise
		),
		externalDataParser = ExternalDataParser(
			wrappers.isPlainObject,
			wrappers.isEmptyObject,
			wrappers.extend
		);

	/**
	 * Expand GeoJSON by fetching any linked ExternalData
	 *
	 * @param {Object|Object[]} geoJSON
	 * @param {string} [name] Group ID to assign to the name field, if applicable.
	 * @return {Promise<Group[]>} resolves to a list of expanded groups.  The
	 *   first group contains all successful data, and subsequent groups are a
	 *   stub holding failure metadata.
	 * @public
	 */
	function loadExternalData( geoJSON, name ) {
		var expandedData = [],
			failures = [];

		var fetchThreads = toArray( geoJSON )
			.map( function ( data ) {
				return fetchExternalData( data )
					.then( Array.prototype.push.bind( expandedData ) )
					.catch( Array.prototype.push.bind( failures ) );
			} );

		return wrappers.whenAllPromises( fetchThreads )
			.then( function () {
				var group = new Group( expandedData );
				if ( name ) {
					group.name = name;
				}
				return [ group ].concat(
					failures.map( function ( err ) {
						var errGroup = new Group();
						errGroup.fail( err );
						return errGroup;
					} ) );
			} );
	}

	/**
	 * Fetch external data, if needed.
	 *
	 * @param {Object} geoJSON to expand
	 * @return {Promise<Object>} Expanded GeoJSON including external data
	 * @private
	 */
	function fetchExternalData( geoJSON ) {
		if ( !externalDataParser.isExternalData( geoJSON ) ) {
			return createResolvedPromise( geoJSON );
		}
		return externalDataLoader.fetch( geoJSON )
			.then( function ( externalData ) {
				return externalDataParser.parse( geoJSON, externalData );
			} );
	}

	/**
	 * Fetch all mapdata and contained ExternalData for a list of group ids.
	 * Note that unused groups not included in groupIds will not be fetched.
	 *
	 * @param {string[]|string} groupIds List of group ids to load (will coerce
	 * from a string if needed).
	 * @param {string} [title] Will be ignored when revid is supplied
	 * @param {string|false} [revid] Either title or revid must be set.
	 * If false or missing, falls back to a title-only request.
	 * @param {string|false} [lang] Language, used for variants
	 * @return {Promise<Group[]>} Resolves with a list of expanded Group objects.
	 * @public
	 */
	function loadGroups( groupIds, title, revid, lang ) {
		groupIds = toArray( groupIds );
		// Fetch mapdata from MediaWiki.
		return dataLoader.fetchGroups(
			groupIds,
			title,
			revid,
			lang
		).then( function ( mapdata ) {
			return groupIds.map( function ( id ) {
				var groupData = mapdata[ id ];

				// Handle failed groups by replacing an error in its place.
				if ( !groupData ) {
					var group = new Group();
					group.name = id;
					group.fail( new Error( 'Received empty response for group "' + id + '"' ) );
					return group;
				}

				return loadExternalData( groupData, id );
			} );
		} ).then(
			wrappers.whenAllPromises
		).then( function ( groupLists ) {
			// Flatten
			return [].concat.apply( [], groupLists );
		} );
	}

	return {
		loadExternalData: loadExternalData,
		loadGroups: loadGroups
	};
};

var index = DataManager;

module.exports = index;
