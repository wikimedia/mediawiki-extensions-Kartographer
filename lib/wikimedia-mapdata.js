'use strict';

/* globals module */
/**
 * Data loader.
 *
 * @class Kartographer.Data.DataLoader
 */
var DataLoader = function ( createPromise, createResolvedPromise, mwApi, clientStore, title, debounce, bind ) {

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
        groupsToLoad = loader.nextFetch;

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
            promise.mwResolve( values[ groupsToLoad[ i ] ] );
          }
          delete promise.mwResolve;
          delete promise.mwReject;
        }
      }
    }

    return mwApi( {
      action: 'query',
      formatversion: '2',
      titles: title,
      prop: 'mapdata',
      mpdlimit: 'max',
      mpdgroups: groupsToLoad.join( '|' )
    } ).then( function ( data ) {
      var rawMapData = data.query.pages[ 0 ].mapdata;
      setPromises( groupsToLoad, rawMapData && JSON.parse( rawMapData ) || {} );
    }, function ( err ) {
      setPromises( groupsToLoad, undefined, err );
    } );
  };

  return new DataLoader();
};

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
var Group$1 = function ( ) {
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

/* globals module */
/**
 * External Data Group.
 *
 * @class Kartographer.Data.Group.External
 * @extends Kartographer.Data.Group
 */
var Group_External = function ( extend, isEmptyObject, isArray, getJSON, mwMsg, mwUri, Group ) {

  var ExternalGroup = function () {
    // call the constructor
    this.initialize.apply( this, arguments );
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
          i;

      switch ( data.service ) {
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
    } );

    return group.promise;
  };

  ExternalGroup.prototype.parseAttribution = function () {
    var i,
        group = this,
        ids = [],
        links = [],
        uri = mwUri( group.geoJSON.url );

    switch ( group.geoJSON.service ) {
      case 'geoshape':
      case 'geoline':
        if ( uri.query.query ) {
          links.push( '<a target="_blank" href="//query.wikidata.org/#' +
              encodeURI( uri.query.query ) +
              '">' +
              mwMsg( 'kartographer-attribution-externaldata-query' ) +
              '</a>' );
        }

        if ( uri.query.ids ) {
          ids = uri.query.ids.split( ',' );

          for ( i = 0; i < ids.length; i++ ) {
            links.push( '<a target="_blank" href="//www.wikidata.org/wiki/' +
                encodeURI( ids[ i ] ) +
                '">' +
                encodeURI( ids[ i ] ) +
                '</a>' );
          }
        }
        group.attribution = mwMsg(
          'kartographer-attribution-externaldata',
          mwMsg( 'project-localized-name-wikidatawiki' ),
          links
        );
        break;
    }
  };

  return ExternalGroup;
};

/* globals module */
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
};

/* globals module */
/**
 * Internal Data Group.
 *
 * @class Kartographer.Data.Group.HybridGroup
 * @extends Kartographer.Data.Group
 */
var Group_Hybrid = function ( extend, createResolvedPromise, isPlainObject, isArray, whenAllPromises, Group, ExternalGroup, DataLoader, DataStore ) {

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
        key,
        externals = group.externals;

    for ( key in externals ) {
      promises.push( externals[ key ].fetch() );
    }

    return whenAllPromises( promises ).then( function () {
      return group;
    } );
  };

  /**
   * @return {Promise}
   */
  HybridGroup.prototype.parse = function ( apiGeoJSON ) {
    var group = this,
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

    return createResolvedPromise( group );
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

var dataLoaderLib = DataLoader;
var Group = Group_1;
var externalGroupLib = Group_External;
var dataStoreLib = DataStore;
var hybridGroupLib = Group_Hybrid;
var internalGroupLib = Group_Internal;

var DataManager = function ( wrappers ) {

  var
    createResolvedPromise = function ( value ) {
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
      wrappers.debounce,
      wrappers.bind
    ),
    ExternalGroup = externalGroupLib(
      wrappers.extend,
      wrappers.isEmptyObject,
      wrappers.isArray,
      wrappers.getJSON,
      wrappers.mwMsg,
      wrappers.mwUri,
      Group
    ),
    DataStore$$1 = dataStoreLib(),
    HybridGroup = hybridGroupLib(
      wrappers.extend,
      createResolvedPromise,
      wrappers.isPlainObject,
      wrappers.isArray,
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
        i;

    if ( !wrappers.isArray( groupIds ) ) {
      groupIds = [ groupIds ];
    }
    for ( i = 0; i < groupIds.length; i++ ) {
      group = DataStore$$1.get( groupIds[ i ] ) || DataStore$$1.add( new InternalGroup( groupIds[ i ] ) );
      promises.push( group.fetch() );
    }

    DataLoader$$1.fetch();

    return wrappers.whenAllPromises( promises ).then( function () {
      var groupList = [],
          group,
          i;

      for ( i = 0; i < groupIds.length; i++ ) {

        group = DataStore$$1.get( groupIds[ i ] );
        if ( !wrappers.isEmptyObject( group.getGeoJSON() ) ) {
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
