'use strict';

/* globals module */
/**
 * Data loader.
 *
 * @class Kartographer.Data.DataLoader
 */
var __moduleExports$1 = function ( createPromise, mwApi, clientStore, title, debounce, bind ) {

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

		if ( debounce && bind ) {
			this.fetch = debounce( 100, bind( this.fetch, this ) );
		}
	};

	clientStore = clientStore || {};

	/**
	 * @param {string} groupId
	 * @return {jQuery.Promise}
	 */
	DataLoader.prototype.fetchGroup = function ( groupId ) {
		var promise = this.promiseByGroup[ groupId ];
		if ( !promise ) {
			promise = this.promiseByGroup[ groupId ] = createPromise();

			if ( clientStore[ groupId ] ) {
				promise.resolve( clientStore[ groupId ] );
			} else {
				this.nextFetch.push( groupId );
			}
		}
		return promise;
	};

	/**
	 * @return {jQuery.Promise}
	 */
	DataLoader.prototype.fetch = function () {
		var groupsToLoad = this.nextFetch,
			loader = this,
			deferred = createPromise();

		this.nextFetch = [];

		if ( groupsToLoad.length ) {
			mwApi( 'get', {
				action: 'query',
				formatversion: '2',
				titles: title,
				prop: 'mapdata',
				mpdgroups: groupsToLoad.join( '|' )
			} ).then( function ( data ) {
				var rawMapData = data.query.pages[ 0 ].mapdata,
					i;

				rawMapData = rawMapData && JSON.parse( rawMapData ) || {};

				for ( i = 0; i < groupsToLoad.length; i++ ) {
					loader.promiseByGroup[ groupsToLoad[ i ] ].resolve( rawMapData[ groupsToLoad[ i ] ] );
				}
				deferred.resolve();
			} );
		}
		return deferred;
	};

	return new DataLoader();
}

/* globals module */
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
var Group = function ( ) {
	// call the constructor
	this.initialize.apply( this, arguments );
};

Group.prototype.initialize = function ( groupId, geoJSON, options ) {
	options = options || {};

	this.id = groupId;
	this.geoJSON = geoJSON || null;
};

/**
 * @return {Object} Group GeoJSON
 */
Group.prototype.getGeoJSON = function () {
	return this.geoJSON;
};

/**
 * @return {string} Group annotation
 */
Group.prototype.getAttribution = function () {
	return this.options.attribution;
};

var __moduleExports$2 = Group;

/* globals module */
/**
 * External Data Group.
 *
 * @class Kartographer.Data.Group.External
 * @extends Kartographer.Data.Group
 */
var __moduleExports$3 = function ( extend, createPromise, isEmptyObject, isArray, getJSON, mwMsg, mwUri, Group ) {

	var ExternalGroup = function () {
		// call the constructor
		this.initialize.apply( this, arguments );
	};

	extend( ExternalGroup.prototype, Group.prototype );

	ExternalGroup.prototype.initialize = function ( groupId, geoJSON, options ) {
		options = options || {};

		Group.prototype.initialize.call( this, groupId, geoJSON, options );
		this.isExternal = true;
	};

	/**
	 * @return {jQuery.Promise}
	 */
	ExternalGroup.prototype.fetch = function () {
		var uri, deferred,
			group = this,
			data = group.geoJSON;

		if ( group.promise ) {
			return group.promise;
		}

		group.promise = deferred = createPromise();

		if ( data.href ) {
			uri = mwUri( data.href );
			// If url begins with   protocol:///...  mark it as having relative host
			if ( /^[a-z]+:\/\/\//.test( data.href ) ) {
				uri.isRelativeHost = true;
			}
		} else if ( data.service ) {
			// Construct URI out of the parameters in the externalData object
			uri = mwUri( {
				protocol: data.service,
				host: data.host,
				path: '/'
			} );
			uri.isRelativeHost = !data.host;
			uri.query = {};
			switch ( data.service ) {
				case 'geoshape':
				case 'geoline':
					if ( data.query ) {
						if ( typeof data.query === 'string' ) {
							uri.query.query = data.query;
						} else {
							throw new Error( 'Invalid "query" parameter in ExternalData' );
						}
					}
					if ( data.ids ) {
						if ( isArray( data.ids ) ) {
							uri.query.ids = data.ids.join( ',' );
						} else if ( typeof data.ids === 'string' ) {
							uri.query.ids = data.ids.replace( /\s*,\s*/, ',' );
						} else {
							throw new Error( 'Invalid "ids" parameter in ExternalData' );
						}
					}
					break;
				default:
					throw new Error( 'Unknown externalData protocol ' + data.service );
			}
		}

		switch ( uri.protocol ) {
			case 'geoshape':
			case 'geoline':

				// geoshape:///?ids=Q16,Q30
				// geoshape:///?query=SELECT...
				// Get geo shapes data from OSM database by supplying Wikidata IDs or query
				// https://maps.wikimedia.org/geoshape?ids=Q16,Q30
				if ( !uri.query || ( !uri.query.ids && !uri.query.query ) ) {
					throw new Error( uri.protocol + ': missing ids or query parameter in externalData' );
				}
				if ( !uri.isRelativeHost && uri.host !== 'maps.wikimedia.org' ) {
					throw new Error( uri.protocol + ': hostname must be missing or "maps.wikimedia.org"' );
				}
				uri.host = 'maps.wikimedia.org';
				uri.port = undefined;
				uri.path = '/' + uri.protocol;
				uri.protocol = 'https';
				uri.query.origin = location.protocol + '//' + location.host;
				// HACK: workaround for T144777
				uri.query.getgeojson = 1;

				getJSON( uri.toString() ).then( function ( geodata ) {
					var baseProps = data.properties,
						ids = [],
						i,
						links = [];
					delete data.href;

					// HACK: workaround for T144777 - we should be using topojson instead
					extend( group.geoJSON, geodata );

					if ( mwMsg ) {
						if ( uri.query.query ) {
							links.push( '<a target="_blank" href="//query.wikidata.org/#' + encodeURI( uri.query.query ) + '">' + mwMsg( 'kartographer-attribution-externaldata-query' ) + '</a>' );
						} else {
							ids = uri.query.ids.split( ',' );

							for ( i = 0; i < ids.length; i++ ) {
								links.push( '<a target="_blank" href="//www.wikidata.org/wiki/' + encodeURI( ids[ i ] ) + '">' + encodeURI( ids[ i ] ) + '</a>' );
							}
						}
						group.attribution = mwMsg(
							'kartographer-attribution-externaldata',
							mwMsg( 'project-localized-name-wikidatawiki' ),
							links
						);
					}

					// console.log( 'data', data );

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
					return deferred.resolve();
				} );
				break;
			default:
				throw new Error( 'Unknown externalData protocol ' + uri.protocol );
		}
		return group.promise;
	};

	return ExternalGroup;
};

/* globals module */
/**
 * Data store.
 *
 * @class Kartographer.Data.DataStore
 */
var __moduleExports$4 = ( function () {

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
	 * @return {Kartographer.Data.Group}
	 */
	DataStore.prototype.get = function ( groupId ) {
		return this.groups[ groupId ];
	};

	/**
	 * @return {boolean}
	 */
	DataStore.prototype.has = function ( groupId ) {
		return ( groupId in this.groups );
	};

	return new DataStore();
} )();

/* globals module */
/**
 * Internal Data Group.
 *
 * @class Kartographer.Data.Group.HybridGroup
 * @extends Kartographer.Data.Group
 */
var __moduleExports$5 = function ( extend, createPromise, isPlainObject, isArray, whenAllPromises, Group, ExternalGroup, DataLoader, DataStore ) {

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
		this.isExternal = false;
		this.externals = [];
	};

	/**
	 * @return {jQuery.Promise}
	 */
	HybridGroup.prototype.load = function () {
		var group = this;

		return group.parse( group.getGeoJSON() ).then( function ( group ) {
			return group.fetchExternalGroups();
		} );
	};

	/**
	 * @return {jQuery.Promise}
	 */
	HybridGroup.prototype.fetchExternalGroups = function () {
		var promises = [],
			deferred = createPromise(),
			group = this,
			key,
			externals = group.externals;

		for ( key in externals ) {
			promises.push( externals[ key ].fetch() );
		}

		return whenAllPromises( promises ).then( function () {
			return deferred.resolve( group ).promise();
		} );
	};

	/**
	 * @return {jQuery.Promise}
	 */
	HybridGroup.prototype.parse = function ( apiGeoJSON ) {
		var group = this,
			deferred = createPromise(),
			geoJSON,
			externalKey,
			i;

		group.apiGeoJSON = apiGeoJSON;
		apiGeoJSON = JSON.parse( JSON.stringify( apiGeoJSON ) );
		if ( isArray( apiGeoJSON ) ) {
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

		return deferred.resolve( group ).promise();
	};

	return HybridGroup;
};

/* globals module */
/**
 * Internal Data Group.
 *
 * @class Kartographer.Data.Group.Internal
 * @extends Kartographer.Data.Group.HybridGroup
 */
var __moduleExports$6 = function ( extend, createPromise, HybridGroup, ExternalGroup, DataLoader ) {

	var InternalGroup = function () {
		// call the constructor
		this.initialize.apply( this, arguments );
	};

	extend( InternalGroup.prototype, HybridGroup.prototype );

	/**
	 * @return {jQuery.Promise}
	 */
	InternalGroup.prototype.fetch = function () {
		var group = this,
			deferred;

		if ( group.promise ) {
			return group.promise;
		}

		group.promise = deferred = createPromise();

		DataLoader.fetchGroup( group.id ).then( function ( apiGeoJSON ) {
			group.parse( apiGeoJSON ).then( function ( group ) {
				return group.fetchExternalGroups();
			} ).then( function () {
				deferred.resolve();
			} );
		} );

		return group.promise;
	};
	return InternalGroup;
};

/* globals module */
/**
 * Data Manager.
 *
 * @class Kartographer.Data.DataManager
 */

var __moduleExports = function ( wrappers ) {

	var DataLoader = __moduleExports$1(
		wrappers.createPromise,
		wrappers.mwApi,
		wrappers.clientStore,
		wrappers.title,
		wrappers.debounce,
		wrappers.bind
		),
		Group = __moduleExports$2,
		ExternalGroup = __moduleExports$3(
			wrappers.extend,
			wrappers.createPromise,
			wrappers.isEmptyObject,
			wrappers.isArray,
			wrappers.getJSON,
			wrappers.mwMsg,
			wrappers.mwUri,
			Group
		),
		DataStore = __moduleExports$4,
		HybridGroup = __moduleExports$5(
			wrappers.extend,
			wrappers.createPromise,
			wrappers.isPlainObject,
			wrappers.isArray,
			wrappers.whenAllPromises,
			Group,
			ExternalGroup,
			DataLoader,
			DataStore
		),
		InternalGroup = __moduleExports$6(
			wrappers.extend,
			wrappers.createPromise,
			HybridGroup,
			ExternalGroup,
			DataLoader
		),
		DataManager = function () {};

	/**
	 * @param {string[]} groupIds List of group ids to load.
	 * @return {jQuery.Promise}
	 */
	DataManager.prototype.loadGroups = function ( groupIds ) {
		var promises = [],
			groupList = [],
			deferred = wrappers.createPromise(),
			group,
			i;

		for ( i = 0; i < groupIds.length; i++ ) {
			group = DataStore.get( groupIds[ i ] ) || DataStore.add( new InternalGroup( groupIds[ i ] ) );
			promises.push( group.fetch() );
		}

		DataLoader.fetch();

		wrappers.whenAllPromises( promises ).then( function () {
			for ( i = 0; i < groupIds.length; i++ ) {

				group = DataStore.get( groupIds[ i ] );
				if ( !wrappers.isEmptyObject( group.getGeoJSON() ) ) {
					groupList = groupList.concat( group );
				}
				groupList = groupList.concat( group.externals );
			}

			return deferred.resolve( groupList );
		} );
		return deferred;
	};

	/**
	 * @param {Object} geoJSON
	 * @return {jQuery.Promise}
	 */
	DataManager.prototype.load = function ( geoJSON ) {
		var groupList = [],
			group = new HybridGroup( null, geoJSON ),
			deferred = wrappers.createPromise();

		group.load().then( function () {

			if ( !wrappers.isEmptyObject( group.getGeoJSON() ) ) {
				groupList = groupList.concat( group );
			}
			groupList = groupList.concat( group.externals );

			return deferred.resolve( groupList );
		} );
		return deferred;
	};

	return new DataManager();
};

var index = __moduleExports;

module.exports = index;