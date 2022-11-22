'use strict';

/**
 * Fetches GeoJSON content for a mapframe or maplink tag from the Kartographer MediaWiki API
 *
 * @class Kartographer.Data.MapdataLoader
 * @param {Function} extend Reference to e.g. {@see jQuery.extend}
 * @param {Function} createResolvedPromise
 * @param {Function} mwApi Reference to the {@see mw.Api} constructor
 * @param {Object} [clientStore] External cache for groups, supplied by the caller.
 */
var MapdataLoader = function (
	extend,
	createResolvedPromise,
	mwApi,
	clientStore
) {
	clientStore = clientStore || {};

	var MapdataLoader = function () {};

	/**
	 * @param {string[]} groupIds
	 * @param {string} [title] Will be ignored if revid is supplied.
	 * @param {string|false} [revid] Either title or revid must be set. If false
	 * or missing, falls back to a title-only request.
	 * @return {Promise<Object>} Resolves to the returned mapdata, or rejects.
	 */
	MapdataLoader.prototype.fetchGroups = function ( groupIds, title, revid ) {
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
			mpdgroups: fetchGroups.join( '|' )
		};
		delete params[ revid ? 'titles' : 'revids' ];

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
 */

/**
 * @param {Object|Object[]} [geoJSON] The group's geometry, or empty for
 *   incomplete ExternalData.
 * @constructor
 */
var Group$1 = function ( geoJSON ) {
	this.geoJSON = geoJSON || null;
	/**
	 * {boolean} Flag is true if the group failed to fully load.
	 */
	this.failed = false;
	/**
	 * {Error|null} Details about a failure, if any.
	 */
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
 * @class Kartographer.Data.ExternalDataLoader
 * @param {Function} getJSON
 * @param {Function} createPromise
 * @constructor
 */
var ExternalDataLoader$1 = function (
	getJSON,
	createPromise
) {
	var ExternalDataLoader = function () {};

	/**
	 * @param {Kartographer.Data.Group} group
	 * @return {Promise} Resolved with the raw, externally-fetched data.
	 */
	ExternalDataLoader.prototype.fetch = function ( group ) {
		var data = group.getGeoJSON();

		if ( !data || !data.url ) {
			return createPromise( function ( _resolve, reject ) {
				reject( new Error( 'ExternalData has no url' ) );
			} );
		}

		return getJSON( data.url );
	};

	return new ExternalDataLoader();
};

/**
 * @class Kartographer.Data.ExternalDataParser
 *
 * @param {Function} isPlainObject
 * @param {Function} isEmptyObject
 * @param {Function} extend
 * @constructor
 */
var ExternalDataParser$1 = function (
	isPlainObject,
	isEmptyObject,
	extend
) {
	var ExternalDataParser = function () {};

	/**
	 * @param {Object|null} geoJSON
	 * @return {boolean} True if this is an ExternalData
	 */
	ExternalDataParser.prototype.isExternalData = function ( geoJSON ) {
		return isPlainObject( geoJSON ) &&
			geoJSON.type === 'ExternalData';
	};

	/**
	 * Transform returned GeoJSON depending on the type of ExternalData.
	 *
	 * FIXME: Wouldn't this be a job for the mapdata API?
	 *
	 * @param {Kartographer.Data.Group} group (modified in-place)
	 * @param {Object} geodata
	 * @return {Kartographer.Data.Group} Expanded group.
	 */
	ExternalDataParser.prototype.parse = function ( group, geodata ) {
		var data = group.getGeoJSON();
		var baseProps = data.properties,
			geometry,
			coordinates,
			i, j;

		switch ( data.service ) {

			case 'page':
				extend( data, geodata.jsondata.data );
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
				for ( i = 0; i < geodata.features.length; i++ ) {
					geometry = geodata.features[ i ].geometry;
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
				data.type = 'Feature';
				data.geometry = {
					type: 'Polygon',
					coordinates: coordinates
				};
				break;

			case 'geoshape':
			case 'geopoint':
			case 'geoline':

				// HACK: workaround for T144777 - we should be using topojson instead
				extend( data, geodata );

				// data.type = 'FeatureCollection';
				// data.features = [];
				// $.each( geodata.objects, function ( key ) {
				// data.features.push( topojson.feature( geodata, geodata.objects[ key ] ) );
				// } );

				// Each feature returned from geoshape service may contain "properties"
				// If externalData element has properties, merge with properties in the feature
				if ( baseProps ) {
					for ( i = 0; i < data.features.length; i++ ) {
						if ( isEmptyObject( data.features[ i ].properties ) ) {
							data.features[ i ].properties = baseProps;
						} else {
							data.features[ i ].properties = extend( {}, baseProps,
								data.features[ i ].properties );
						}
					}
				}
				break;

			default:
				throw new Error( 'Unknown externalData service "' + data.service + '"' );
		}

		return group;
	};

	return new ExternalDataParser();
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
 * @class Kartographer.Data.DataManager
 * @param {Object} wrappers
 * @param {Object} [wrappers.clientStore] External cache of groups, supplied by the caller.
 * @param {Function} wrappers.createPromise
 * @param {Function} wrappers.extend Reference to e.g. {@see jQuery.extend}
 * @param {Function} wrappers.getJSON Reference to e.g. {@see jQuery.getJSON}
 * @param {Function} wrappers.isEmptyObject Reference to e.g. {@see jQuery.isEmptyObject}
 * @param {Function} wrappers.isPlainObject Reference to e.g. {@see jQuery.isPlainObject}
 * @param {Function} wrappers.mwApi Reference to the {@see mw.Api} constructor
 * @param {Function} wrappers.whenAllPromises Reference a function like {@see Promise.all}
 * @constructor
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
		),
		DataManager = function () {};

	/**
	 * Restructure the geoJSON from a single group, splitting out external data
	 * each into a separate group, and leaving any plain data bundled together.
	 *
	 * @param {Object|Object[]} geoJSON
	 * @return {Kartographer.Data.Group[]}
	 */
	function splitExternalGroups( geoJSON ) {
		var groups = [];
		var plainData = [];
		toArray( geoJSON ).forEach( function ( data ) {
			if ( externalDataParser.isExternalData( data ) ) {
				groups.push( new Group( data ) );
			} else {
				plainData.push( data );
			}
		} );
		if ( plainData.length ) {
			groups.push( new Group( plainData ) );
		}
		return groups;
	}

	/**
	 * Expand ExternalData for the group
	 *
	 * @param {Kartographer.Data.Group} group
	 * @return {Kartographer.Data.Group[]} groups The original group, plus any
	 * retrieved external data each as a separate group.
	 */
	function fetchExternalData( group ) {
		if ( !externalDataParser.isExternalData( group.getGeoJSON() ) ) {
			return createResolvedPromise( group );
		}
		return externalDataLoader.fetch( group )
			.then( function ( data ) {
				// Side-effect of parse is to update the group.
				externalDataParser.parse( group, data );
				return group;
			} )
			.catch( function ( err ) {
				group.fail( err );
				return group;
			} );
	}

	/**
	 * Fetch all mapdata and contained ExternalData.
	 *
	 * @param {string[]|string} groupIds List of group ids to load (will coerce
	 * from a string if needed).
	 * @param {string} [title] Will be ignored when revid is supplied
	 * @param {string|false} [revid] Either title or revid must be set.
	 * If false or missing, falls back to a title-only request.
	 * @return {Promise<Group[]>} Resolves with a list of expanded Group objects.
	 */
	DataManager.prototype.loadGroups = function ( groupIds, title, revid ) {
		groupIds = toArray( groupIds );
		// Fetch mapdata for all groups from MediaWiki.
		return dataLoader.fetchGroups(
			groupIds,
			title,
			revid
		).then( function ( mapdata ) {
			return groupIds.reduce( function ( groups, id ) {
				var groupData = mapdata[ id ];

				// Handle failed groups by replacing with an error.
				if ( !groupData ) {
					var group = new Group();
					group.fail( new Error( 'Received empty response for group "' + id + '"' ) );
					groups.push( group );
					return groups;
				}

				return groups.concat( splitExternalGroups( groupData ) );
			}, [] );
		} ).then( function ( groups ) {
			return groups.map( fetchExternalData );
		} ).then(
			wrappers.whenAllPromises
		);
	};

	/**
	 * Load any ExternalData contained by the given geojson
	 *
	 * @param {Object|Object[]} geoJSON
	 * @return {Promise<Kartographer.Data.Group[]>}
	 */
	DataManager.prototype.load = function ( geoJSON ) {
		return wrappers.whenAllPromises(
			splitExternalGroups( geoJSON )
				.map( function ( group ) {
					return fetchExternalData( group );
				} )
		);
	};

	return new DataManager();
};

var index = DataManager;

module.exports = index;
