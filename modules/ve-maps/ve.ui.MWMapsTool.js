/*!
 * VisualEditor MediaWiki UserInterface gallery tool class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface gallery tool.
 *
 * @class
 * @extends ve.ui.FragmentWindowTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWMapsDialogTool = function VeUiMWMapsDialogTool() {
	ve.ui.MWMapsDialogTool.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMapsDialogTool, ve.ui.FragmentWindowTool );

/* Static properties */

ve.ui.MWMapsDialogTool.static.name = 'mwMaps';
ve.ui.MWMapsDialogTool.static.group = 'object';
ve.ui.MWMapsDialogTool.static.icon = 'map';
ve.ui.MWMapsDialogTool.static.title = OO.ui.deferMsg( 'visualeditor-mwmapsdialog-title' );
ve.ui.MWMapsDialogTool.static.modelClasses = [ ve.dm.MWMapsNode, ve.dm.MWInlineMapsNode ];
ve.ui.MWMapsDialogTool.static.commandName = 'mwMaps';

if ( mw.config.get( 'wgKartographerEnableMapFrame' ) ) {
	/* Registration */

	ve.ui.toolFactory.register( ve.ui.MWMapsDialogTool );

	/* Commands */

	ve.ui.commandRegistry.register(
		new ve.ui.Command(
			'mwMaps', 'window', 'open',
			{ args: [ 'mwMaps' ], supportedSelections: [ 'linear' ] }
		)
	);
}
