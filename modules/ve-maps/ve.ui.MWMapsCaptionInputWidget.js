/**
 * @class
 * @extends OO.ui.MultilineTextInputWidget
 *
 * @constructor
 */
ve.ui.MWMapsCaptionInputWidget = function VeUiMWMapsCaptionInputWidget() {
	ve.ui.MWMapsCaptionInputWidget.super.call( this, {
		rows: 2,
		autosize: true
	} );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMapsCaptionInputWidget, OO.ui.MultilineTextInputWidget );

/* Methods */

ve.ui.MWMapsCaptionInputWidget.prototype.onKeyPress = function ( e ) {
	if ( e.which === OO.ui.Keys.ENTER ) {
		e.preventDefault();
	}
	ve.ui.MWMapsCaptionInputWidget.super.prototype.onKeyPress.call( this, e );
};
