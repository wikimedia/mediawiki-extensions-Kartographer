/*!
 * VisualEditor MWMapsContextItem class.
 *
 * @copyright 2011-2017 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWInlineMapsNode or MWMapsNode.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWMapsContextItem = function VeUiMWMapsContextItem() {
	// Parent constructor
	ve.ui.MWMapsContextItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMapsContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWMapsContextItem.static.name = 'mwMaps';

ve.ui.MWMapsContextItem.static.icon = 'map';

ve.ui.MWMapsContextItem.static.label = OO.ui.deferMsg( 'visualeditor-mwmapscontextitem-title' );

ve.ui.MWMapsContextItem.static.modelClasses = [ ve.dm.MWInlineMapsNode, ve.dm.MWMapsNode ];

ve.ui.MWMapsContextItem.static.commandName = 'mwMaps';

/* Methods */

/**
 * Get a DOM rendering of the reference.
 *
 * @private
 * @return {jQuery} DOM rendering of reference
 */
ve.ui.MWMapsContextItem.prototype.getRendering = function () {
	if ( !this.model.isEditable() ) {
		return $( '<div>' )
			.addClass( 've-ui-mwMapsContextItem-nosupport' )
			.text( this.getDescription() );
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsContextItem.prototype.getDescription = function () {
	return this.model.isEditable() ? '' : ve.msg( 'visualeditor-mwmapscontextitem-nosupport' );
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsContextItem.prototype.renderBody = function () {
	this.$body.empty().append( this.getRendering() );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWMapsContextItem );
