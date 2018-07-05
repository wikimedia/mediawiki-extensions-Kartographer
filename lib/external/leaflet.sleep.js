/*
 * Leaflet.Sleep
 */

/*
 * The Sleep Handler
 */

L.Map.mergeOptions({
  sleep: true,
  sleepTime: 750,
  wakeTime: 750,
  hoverToWake: true,
  sleepOpacity: 0.7
});

L.Map.Sleep = L.Handler.extend({

  addHooks: function () {
    var self = this;
    this._enterTimeout = null;
    this._exitTimeout = null;

    var mapStyle = this._map._container.style;
    mapStyle.WebkitTransition += 'opacity .5s';
    mapStyle.MozTransition += 'opacity .5s';

    this._sleepMap();
  },

  removeHooks: function () {
    if (!this._map.scrollWheelZoom.enabled()){
      this._map.scrollWheelZoom.enable();
    }
    if (this._map.tap && !this._map.tap.enabled()) {
      this._map.touchZoom.enable();
      this._map.dragging.enable();
      this._map.tap.enable();
    }
    L.DomUtil.setOpacity( this._map._container, 1);
    this._removeSleepingListeners();
    this._removeAwakeListeners();
  },

  _wakeMap: function (e) {
    this._stopWaiting();
    this._map.scrollWheelZoom.enable();
    this._map.dragging.enable();
    if (this._map.tap) {
      this._map.touchZoom.enable();
      this._map.tap.enable();
    }
    L.DomUtil.setOpacity( this._map._container, 1);
    this._addAwakeListeners();
  },

  _sleepMap: function () {
    this._stopWaiting();
    this._map.scrollWheelZoom.disable();
    this._map.dragging.disable();

    if (this._map.tap) {
      this._map.touchZoom.disable();
      this._map.tap.disable();
    }

    L.DomUtil.setOpacity( this._map._container, this._map.options.sleepOpacity);
    this._addSleepingListeners();
  },

  _wakePending: function () {
    this._map.once('mousedown', this._wakeMap, this);
    if (this._map.options.hoverToWake){
      var self = this;
      this._map.once('mouseout', this._sleepMap, this);
      self._enterTimeout = setTimeout(function(){
          self._map.off('mouseout', self._sleepMap, self);
          self._wakeMap();
      } , self._map.options.wakeTime);
    }
  },

  _sleepPending: function () {
    var self = this;
    self._map.once('mouseover', self._wakeMap, self);
    self._exitTimeout = setTimeout(function(){
        self._map.off('mouseover', self._wakeMap, self);
        self._sleepMap();
    } , self._map.options.sleepTime);
  },

  _addSleepingListeners: function(){
    this._map.once('mouseover', this._wakePending, this);
    this._map.once('click', this._wakeMap, this);
  },

  _addAwakeListeners: function(){
    this._map.once('mouseout', this._sleepPending, this);
  },

  _removeSleepingListeners: function(){
    this._map.options.hoverToWake &&
      this._map.off('mouseover', this._wakePending, this);
    this._map.off('mousedown', this._wakeMap, this);
    this._map.off('click', this._wakeMap, this);
  },

  _removeAwakeListeners: function(){
    this._map.off('mouseout', this._sleepPending, this);
  },

  _stopWaiting: function () {
    this._removeSleepingListeners();
    this._removeAwakeListeners();
    var self = this;
    if(this._enterTimeout) clearTimeout(self._enterTimeout);
    if(this._exitTimeout) clearTimeout(self._exitTimeout);
    this._enterTimeout = null;
    this._exitTimeout = null;
  }
});
