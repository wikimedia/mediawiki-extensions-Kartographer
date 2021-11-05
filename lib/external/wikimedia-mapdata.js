'use strict';

/**
 * @class Kartographer.Data.DataLoader
 * @param {Function} createPromise
 * @param {Function} createResolvedPromise
 * @param {Function} mwApi
 * @param {Object} [clientStore]
 * @param {string} [title] Will be ignored if revid is supplied.
 * @param {string|boolean} [revid] Either title or revid must be set.  If false,
 *     falls back to a title-only request.
 * @param {Function} [debounce]
 * @constructor
 */
var DataLoader = function ( createPromise, createResolvedPromise, mwApi, clientStore, title, revid, debounce ) {

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
			params;

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

		params = {
			action: 'query',
			formatversion: '2',
			titles: title,
			revids: revid,
			prop: 'mapdata',
			mpdlimit: 'max',
			mpdgroups: groupsToLoad.join( '|' )
		};
		delete params[ revid ? 'titles' : 'revids' ];

		return mwApi( params ).then( function ( data ) {
			var rawMapData = data.query.pages[ 0 ].mapdata;
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
 * @class Kartographer.Data.Group.External
 * @extends Kartographer.Data.Group
 * @param {Function} extend
 * @param {Function} isEmptyObject
 * @param {Function} getJSON
 * @param {Function} [mwMsg]
 * @param {Function} mwUri
 * @param {Function} mwHtmlElement
 * @param {Function} Group
 * @return {Function}
 */
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
 * @class Kartographer.Data.DataStore
 * @constructor
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
 * @param {Function} extend
 * @param {Function} createResolvedPromise
 * @param {Function} isPlainObject
 * @param {Function} whenAllPromises
 * @param {Function} Group
 * @param {Function} ExternalGroup
 * @param {Kartographer.Data.DataStore} DataStore
 * @return {Function}
 */
var Group_Hybrid = function ( extend, createResolvedPromise, isPlainObject, whenAllPromises, Group, ExternalGroup, DataStore ) {

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
	 * @param {Object[]|Object} apiGeoJSON The GeoJSON as returned by the API.
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
		} else if ( isExternalDataGroup( apiGeoJSON ) ) {
			externalKey = JSON.stringify( apiGeoJSON );
			group.externals.push(
				DataStore.get( externalKey ) ||
				DataStore.add( new ExternalGroup( externalKey, apiGeoJSON ) )
			);
			geoJSON = {};
		}

		group.geoJSON = geoJSON;

		return createResolvedPromise( group );
	};

	return HybridGroup;
};

/**
 * @class Kartographer.Data.Group.Internal
 * @extends Kartographer.Data.Group.HybridGroup
 * @param {Function} extend
 * @param {Function} HybridGroup
 * @param {Kartographer.Data.DataLoader} DataLoader
 * @return {Function}
 */
var Group_Internal = function ( extend, HybridGroup, DataLoader ) {

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

var dataLoaderLib = DataLoader;
var Group = Group_1;
var externalGroupLib = Group_External;
var dataStoreLib = DataStore;
var hybridGroupLib = Group_Hybrid;
var internalGroupLib = Group_Internal;

/**
 * @class Kartographer.Data.DataManager
 * @param {Object} wrappers
 * @param {Object} [wrappers.clientStore]
 * @param {Function} wrappers.createPromise
 * @param {Function} [wrappers.debounce]
 * @param {Function} wrappers.extend
 * @param {Function} wrappers.getJSON
 * @param {Function} wrappers.isEmptyObject
 * @param {Function} wrappers.isPlainObject
 * @param {Function} wrappers.mwApi
 * @param {Function} wrappers.mwHtmlElement
 * @param {Function} [wrappers.mwMsg]
 * @param {Function} wrappers.mwUri
 * @param {string} wrappers.title
 * @param {string} wrappers.revid
 * @param {Function} wrappers.whenAllPromises
 * @constructor
 */
var DataManager = function ( wrappers ) {

	var createResolvedPromise = function ( value ) {
			return wrappers.createPromise( function ( resolve ) {
				resolve( value );
			} );
		},
		DataLoader$$1 = dataLoaderLib(
			wrappers.createPromise,
			createResolvedPromise,
			wrappers.mwApi,
			wrappers.clientStore,
			wrappers.title,
			wrappers.revid,
			wrappers.debounce
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
			DataStore$$1
		),
		InternalGroup = internalGroupLib(
			wrappers.extend,
			HybridGroup,
			DataLoader$$1
		),
		DataManager = function () {};

	/**
	 * @param {string[]|string} groupIds List of group ids to load.
	 * @return {Promise}
	 */
	DataManager.prototype.loadGroups = function ( groupIds ) {
		var promises = [],
			group,
			i;

		if ( !Array.isArray( groupIds ) ) {
			groupIds = [ groupIds ];
		}
		for ( i = 0; i < groupIds.length; i++ ) {
			group = DataStore$$1.get( groupIds[ i ] ) || DataStore$$1.add( new InternalGroup( groupIds[ i ] ) );
			// eslint-disable-next-line no-loop-func
			promises.push( wrappers.createPromise( function ( resolve ) {
				group.fetch().then( resolve, resolve );
			} ) );
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
