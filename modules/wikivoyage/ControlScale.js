/* globals module */
/*jscs:disable disallowDanglingUnderscores, requireVarDeclFirst */
/**
 * Control to display the scale.
 *
 * @alias ControlScale
 * @class Kartographer.Wikivoyage.ControlScale
 * @extends L.Control.Scale
 * @private
 */
module.ControlScale = L.Control.Scale.extend( {

	isMetric: true,

	/**
	 * @inheritdoc
	 */
	_updateScales: function ( options, maxMeters ) {

		L.Control.Scale.prototype._updateScales.call( this, options, maxMeters );

		this._toggleScale();
	},

	/**
	 * @inheritdoc
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
	 * @private
	 */
	_toggleScale: function () {
		if ( this.options.metric && this.options.imperial ) {
			this._mScale.style.display = this.isMetric ? 'block' : 'none';
			this._iScale.style.display = !this.isMetric ? 'block' : 'none';
		}
	},

	/**
	 * @private
	 */
	_onToggleScale: function () {
		this.isMetric = !this.isMetric;
		this._toggleScale();
	}
} );
