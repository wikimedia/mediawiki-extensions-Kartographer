/* eslint-disable no-underscore-dangle */
/**
 * # Control to display the scale.
 *
 * See [L.Control](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-control/)
 * documentation for more details.
 *
 * @class Kartographer.Box.ScaleControl
 * @extends L.Control
 */
module.ScaleControl = L.Control.Scale.extend( {

	isMetric: true,

	/**
	 * @override
	 * @private
	 */
	_updateScales: function ( options, maxMeters ) {

		L.Control.Scale.prototype._updateScales.call( this, options, maxMeters );

		this._toggleScale();
	},

	/**
	 * @override
	 * @private
	 */
	_addScales: function ( options, className, container ) {
		L.Control.Scale.prototype._addScales.call( this, options, className, container );

		if ( options.metric && options.imperial ) {
			L.DomEvent.addListener( this._mScale, 'click', this._onToggleScale, this );
			L.DomEvent.addListener( this._iScale, 'click', this._onToggleScale, this );
			L.DomEvent.disableClickPropagation( container );
		}
	},

	/**
	 * @protected
	 */
	_toggleScale: function () {
		if ( this.options.metric && this.options.imperial ) {
			this._mScale.style.display = this.isMetric ? 'block' : 'none';
			this._iScale.style.display = !this.isMetric ? 'block' : 'none';
		}
	},

	/**
	 * @protected
	 */
	_onToggleScale: function () {
		this.isMetric = !this.isMetric;
		this._toggleScale();
	}
} );
