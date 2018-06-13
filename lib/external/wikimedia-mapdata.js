'use strict';

/**
 * Data loader.
 *
 * @class Kartographer.Data.DataLoader
 */
// eslint-disable-next-line valid-jsdoc
var DataLoader = function ( createPromise, createResolvedPromise, mwApi, clientStore, title, debounce, getGroupIdsToExclude ) {

	var DataLoader = function () {
		/**
     * @type {Object} Hash of group ids and associated promises.
     * @private
     */
		this.promiseByGroup = {};
		/**
     * @type {string[]} List of group ids to fetch next time
     *   {@link #fetch} is called.
     *
     * @private
     */
		this.nextFetch = [];

		if ( debounce ) {
			this.fetch = debounce( 100, this.fetch.bind( this ) );
		}
	};

	clientStore = clientStore || {};

	/**
   * @param {string} groupId
   * @return {Promise}
   */
	DataLoader.prototype.fetchGroup = function ( groupId ) {
		var promise = this.promiseByGroup[ groupId ],
			resolveFunc, rejectFunc;
		if ( !promise ) {
			if ( clientStore[ groupId ] ) {
				promise = createResolvedPromise( clientStore[ groupId ] );
			} else {
				// FIXME: this is a horrible hack
				// The resolve and reject functions are attached to the promise object's instance
				// so that they can be called from the fetch function later
				this.nextFetch.push( groupId );
				promise = createPromise( function ( resolve, reject ) {
					resolveFunc = resolve;
					rejectFunc = reject;
				} );
				promise.mwResolve = resolveFunc;
				promise.mwReject = rejectFunc;
			}

			this.promiseByGroup[ groupId ] = promise;
		}
		return promise;
	};

	/**
   * @return {Promise}
   */
	DataLoader.prototype.fetch = function () {
		var loader = this,
			groupsToLoad = loader.nextFetch,
			groupsToExclude = [],
			query = {
				action: 'query',
				formatversion: '2',
				titles: title,
				prop: 'mapdata',
				mpdlimit: 'max'
			};

		if ( !groupsToLoad.length ) {
			return createResolvedPromise();
		}

		loader.nextFetch = [];

		// FIXME: we need to fix this horrid hack
		// http://stackoverflow.com/questions/39970101/combine-multiple-debounce-promises-in-js
		function setPromises( groupsToLoad, values, err ) {
			var i, promise;

			for ( i = 0; i < groupsToLoad.length; i++ ) {
				promise = loader.promiseByGroup[ groupsToLoad[ i ] ];
				if ( promise.mwResolve ) {
					if ( err ) {
						promise.mwReject( err );
					} else {
						promise.mwResolve( values[ groupsToLoad[ i ] ] || {} );
					}
					delete promise.mwResolve;
					delete promise.mwReject;
				}
			}
		}

		if ( groupsToLoad.indexOf( 'all' ) === -1 ) {
			query.mpdgroups = groupsToLoad.join( '|' );
		} else {
			groupsToExclude = getGroupIdsToExclude( groupsToLoad );
		}

		return mwApi( query ).then( function ( data ) {
			var rawMapData = data.query.pages[ 0 ].mapdata;
			groupsToExclude.forEach( function ( group ) {
				delete rawMapData[ group ];
			} );
			setPromises( groupsToLoad, rawMapData && JSON.parse( rawMapData ) || {} );
		}, function ( err ) {
			setPromises( groupsToLoad, undefined, err );
		} );
	};

	return new DataLoader();
};

/**
 * Group parent class.
 *
 * @class Kartographer.Data.Group
 * @extends L.Class
 * @abstract
 */

/**
 * @param {string} groupId
 * @param {Object} [geoJSON]
 * @param {Object} [options]
 * @constructor
 */
var Group$1 = function () {
	// call the constructor
	this.initialize.apply( this, arguments );
};

Group$1.prototype.initialize = function ( groupId, geoJSON, options ) {
	this.id = groupId;
	this.geoJSON = geoJSON || null;
	this.options = options || {};
};

/**
 * @return {Object} Group GeoJSON
 */
Group$1.prototype.getGeoJSON = function () {
	return this.geoJSON;
};

/**
 * @return {string} Group annotation
 */
Group$1.prototype.getAttribution = function () {
	return this.options.attribution;
};

var Group_1 = Group$1;

/**
 * External Data Group.
 *
 * @class Kartographer.Data.Group.External
 * @extends Kartographer.Data.Group
 */
// eslint-disable-next-line valid-jsdoc
var Group_External = function ( extend, isEmptyObject, getJSON, mwMsg, mwUri, mwHtmlElement, Group ) {

	var ExternalGroup = function () {
		// call the constructor
		this.initialize.apply( this, arguments );
		this.isExternal = true;
	};

	extend( ExternalGroup.prototype, Group.prototype );

	ExternalGroup.prototype.initialize = function ( groupId, geoJSON, options ) {
		options = options || {};

		Group.prototype.initialize.call( this, groupId, geoJSON, options );
	};

	/**
   * @return {Promise}
   */
	ExternalGroup.prototype.fetch = function () {
		var group = this,
			data = group.geoJSON;

		if ( group.promise ) {
			return group.promise;
		}

		if ( !data.url ) {
			throw new Error( 'ExternalData has no url' );
		}

		group.promise = getJSON( data.url ).then( function ( geodata ) {
			var baseProps = data.properties,
				geometry,
				coordinates,
				i, j;

			switch ( data.service ) {

				case 'page':
					if ( geodata.jsondata && geodata.jsondata.data ) {
						extend( data, geodata.jsondata.data );
					}
					// FIXME: error reporting, at least to console.log
					break;

				case 'geomask':
					// Mask-out the entire world 10 times east and west,
					// and add each result geometry as a hole
					coordinates = [ [ [ 3600, -180 ], [ 3600, 180 ], [ -3600, 180 ], [ -3600, -180 ], [ 3600, -180 ] ] ];
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
				case 'geoline':

					// HACK: workaround for T144777 - we should be using topojson instead
					extend( data, geodata );

					// data.type = 'FeatureCollection';
					// data.features = [];
					// $.each( geodata.objects, function ( key ) {
					// 	data.features.push( topojson.feature( geodata, geodata.objects[ key ] ) );
					// } );

					// Each feature returned from geoshape service may contain "properties"
					// If externalData element has properties, merge it with properties in the feature
					if ( baseProps ) {
						for ( i = 0; i < data.features.length; i++ ) {
							if ( isEmptyObject( data.features[ i ].properties ) ) {
								data.features[ i ].properties = baseProps;
							} else {
								data.features[ i ].properties = extend( {}, baseProps, data.features[ i ].properties );
							}
						}
					}
					break;

				default:
					throw new Error( 'Unknown externalData service ' + data.service );
			}

			if ( mwMsg ) {
				group.parseAttribution();
			}
		}, function () {
			group.failed = true;
		} );

		return group.promise;
	};

	ExternalGroup.prototype.parseAttribution = function () {
		var group = this,
			links = [],
			uri = mwUri( group.geoJSON.url );

		if ( group.geoJSON.service === 'page' ) {
			links.push(
				mwHtmlElement( 'a',
					{
						target: '_blank',
						href: '//commons.wikimedia.org/wiki/Data:' + encodeURIComponent( uri.query.title )
					},
					uri.query.title
				)
			);
			group.attribution = mwMsg(
				'kartographer-attribution-externaldata',
				mwMsg( 'project-localized-name-commonswiki' ),
				links
			);
		}
	};

	return ExternalGroup;
};

/**
 * Data store.
 *
 * @class Kartographer.Data.DataStore
 */
var DataStore = function () {

	var DataStore = function () {
		this.groups = {};
	};

	/**
	 * @param {Kartographer.Data.Group} group
	 * @return {Kartographer.Data.Group}
	 */
	DataStore.prototype.add = function ( group ) {
		this.groups[ group.id ] = group;
		return group;
	};

	/**
	 * @param {string} groupId
	 * @return {Kartographer.Data.Group}
	 */
	DataStore.prototype.get = function ( groupId ) {
		return this.groups[ groupId ];
	};

	/**
	 * @return {Map<string, Kartographer.Data.Group>}
	 */
	DataStore.prototype.getAll = function () {
		return this.groups;
	};

	/**
	 * @param {string} groupId
	 * @return {boolean}
	 */
	DataStore.prototype.has = function ( groupId ) {
		return ( groupId in this.groups );
	};

	return new DataStore();
};

/**
 * A hybrid group is a group that is not considered as a {@link Kartographer.Data.Group.HybridGroup}
 * because it does not implement a `fetch` method.
 *
 * This abstraction is useful for the Developer API: the data is passed directly but still needs to
 * be parsed to extract the external sub-groups.
 *
 * @class Kartographer.Data.Group.HybridGroup
 * @extends Kartographer.Data.Group
 */
// eslint-disable-next-line valid-jsdoc
var Group_Hybrid = function ( extend, createResolvedPromise, isPlainObject, whenAllPromises, Group, ExternalGroup, DataLoader, DataStore ) {

	var HybridGroup = function () {
		// call the constructor
		this.initialize.apply( this, arguments );
	};

	function isExternalDataGroup( data ) {
		return isPlainObject( data ) && data.type && data.type === 'ExternalData';
	}

	extend( HybridGroup.prototype, Group.prototype );

	HybridGroup.prototype.initialize = function ( groupId, geoJSON, options ) {
		options = options || {};

		Group.prototype.initialize.call( this, groupId, geoJSON, options );
		this.externals = [];
		this.isExternal = false;
	};

	/**
   * @return {Promise}
   */
	HybridGroup.prototype.load = function () {
		var group = this;

		return group.parse( group.getGeoJSON() ).then( function ( group ) {
			return group.fetchExternalGroups();
		} );
	};

	/**
   * @return {Promise}
   */
	HybridGroup.prototype.fetchExternalGroups = function () {
		var promises = [],
			group = this,
			i,
			externals = group.externals;

		for ( i = 0; i < externals.length; i++ ) {
			promises.push( externals[ i ].fetch() );
		}

		return whenAllPromises( promises ).then( function () {
			return group;
		} );
	};

	/**
   * Parses the GeoJSON to extract the external data sources.
   *
   * Creates {@link Kartographer.Data.Group.External external data groups} and
   * keeps references of them in {@link #externals}.
   *
   * @param {Object|Array} apiGeoJSON The GeoJSON as returned by the API.
   * @return {Promise}
   */
	HybridGroup.prototype.parse = function ( apiGeoJSON ) {
		var group = this,
			geoJSON,
			externalKey,
			i;

		group.apiGeoJSON = apiGeoJSON;
		apiGeoJSON = JSON.parse( JSON.stringify( apiGeoJSON ) );
		if ( Array.isArray( apiGeoJSON ) ) {
			geoJSON = [];
			for ( i = 0; i < apiGeoJSON.length; i++ ) {
				if ( isExternalDataGroup( apiGeoJSON[ i ] ) ) {
					externalKey = JSON.stringify( apiGeoJSON[ i ] );
					group.externals.push(
						DataStore.get( externalKey ) ||
            DataStore.add( new ExternalGroup( externalKey, apiGeoJSON[ i ] ) )
					);
				} else {
					geoJSON.push( apiGeoJSON[ i ] );
				}
			}
		} else if ( isExternalDataGroup( geoJSON ) ) {
			externalKey = JSON.stringify( geoJSON );
			group.externals.push(
				DataStore.get( externalKey ) ||
        DataStore.add( new ExternalGroup( externalKey, geoJSON ) )
			);
			geoJSON = {};
		}

		group.geoJSON = geoJSON;

		return createResolvedPromise( group );
	};

	return HybridGroup;
};

/**
 * Internal Data Group.
 *
 * @class Kartographer.Data.Group.Internal
 * @extends Kartographer.Data.Group.HybridGroup
 */
// eslint-disable-next-line valid-jsdoc
var Group_Internal = function ( extend, HybridGroup, ExternalGroup, DataLoader ) {

	var InternalGroup = function () {
		// call the constructor
		this.initialize.apply( this, arguments );
	};

	extend( InternalGroup.prototype, HybridGroup.prototype );

	/**
   * @return {Promise}
   */
	InternalGroup.prototype.fetch = function () {
		var group = this;

		if ( group.promise ) {
			return group.promise;
		}

		group.promise = DataLoader.fetchGroup( group.id ).then( function ( apiGeoJSON ) {
			return group.parse( apiGeoJSON ).then( function ( group ) {
				return group.fetchExternalGroups();
			} );
		}, function () {
			group.failed = true;
		} );
		return group.promise;
	};
	return InternalGroup;
};

/**
 * Data Manager.
 *
 * @class Kartographer.Data.DataManager
 */
var dataLoaderLib = DataLoader;
var Group = Group_1;
var externalGroupLib = Group_External;
var dataStoreLib = DataStore;
var hybridGroupLib = Group_Hybrid;
var internalGroupLib = Group_Internal;

var DataManager = function ( wrappers ) {

	var createResolvedPromise = function ( value ) {
			return wrappers.createPromise( function ( resolve ) {
				resolve( value );
			} );
		},
		getGroupIdsToExclude = function ( groupIds ) {
			return groupIds.filter( function ( groupId ) {
				return groupId.indexOf( '-' ) === 0;
			} ).map( function ( groupId ) {
				return groupId.slice( 1 );
			} );
		},
		DataLoader$$1 = dataLoaderLib(
			wrappers.createPromise,
			createResolvedPromise,
			wrappers.mwApi,
			wrappers.clientStore,
			wrappers.title,
			wrappers.debounce,
			getGroupIdsToExclude
		),
		ExternalGroup = externalGroupLib(
			wrappers.extend,
			wrappers.isEmptyObject,
			wrappers.getJSON,
			wrappers.mwMsg,
			wrappers.mwUri,
			wrappers.mwHtmlElement,
			Group
		),
		DataStore$$1 = dataStoreLib(),
		HybridGroup = hybridGroupLib(
			wrappers.extend,
			createResolvedPromise,
			wrappers.isPlainObject,
			wrappers.whenAllPromises,
			Group,
			ExternalGroup,
			DataLoader$$1,
			DataStore$$1
		),
		InternalGroup = internalGroupLib(
			wrappers.extend,
			HybridGroup,
			ExternalGroup,
			DataLoader$$1
		),
		DataManager = function () {};

	/**
   * @param {string[]} groupIds List of group ids to load.
   * @return {Promise}
   */
	DataManager.prototype.loadGroups = function ( groupIds ) {
		var promises = [],
			group,
			groupId,
			allGroups,
			groupIdsToExclude,
			i;

		function pushPromiseForGroup( group ) {
			promises.push( wrappers.createPromise( function ( resolve ) {
				group.fetch().then( resolve, resolve );
			} ) );
		}

		if ( !Array.isArray( groupIds ) ) {
			groupIds = [ groupIds ];
		}
		if ( groupIds.indexOf( 'all' ) === -1 ) {
			for ( i = 0; i < groupIds.length; i++ ) {
				group = DataStore$$1.get( groupIds[ i ] ) || DataStore$$1.add( new InternalGroup( groupIds[ i ] ) );
				pushPromiseForGroup( group );
			}
		} else {
			groupIdsToExclude = getGroupIdsToExclude( groupIds );
			allGroups = DataStore$$1.getAll();
			for ( groupId in allGroups ) {
				if ( allGroups.hasOwnProperty( groupId ) && groupIdsToExclude.indexOf( groupId ) === -1 ) {
					pushPromiseForGroup( allGroups[ groupId ] );
				}
			}
		}

		DataLoader$$1.fetch();

		return wrappers.whenAllPromises( promises ).then( function () {
			var groupList = [],
				group,
				i;

			for ( i = 0; i < groupIds.length; i++ ) {

				group = DataStore$$1.get( groupIds[ i ] );
				if ( group.failed || !wrappers.isEmptyObject( group.getGeoJSON() ) ) {
					groupList = groupList.concat( group );
				}
				groupList = groupList.concat( group.externals );
			}

			return groupList;
		} );
	};

	/**
   * @param {Object} geoJSON
   * @return {Promise}
   */
	DataManager.prototype.load = function ( geoJSON ) {
		var group = new HybridGroup( null, geoJSON );

		return group.load().then( function () {
			var groupList = [];

			if ( !wrappers.isEmptyObject( group.getGeoJSON() ) ) {
				groupList = groupList.concat( group );
			}

			return groupList.concat( group.externals );
		} );
	};

	return new DataManager();
};

var index = DataManager;

module.exports = index;
