/**
 * Replace Leaflet.draw i18n object messages with MediaWiki messages
 *
 * Object property chains are convertered to lower-kebab-case and
 * prefixed with 'leafletdraw-', e.g.
 *  L.drawLocal.edit.toolbar.buttons.editDisabled =
 *  mw.msg( 'leafletdraw-edit-toolbar-buttons-editdisabled' )
 */

function replaceMessages( obj, keys ) {
	var key, newKeys, value;

	keys = keys || [];

	for ( key in obj ) {
		value = obj[ key ];
		newKeys = keys.slice();
		newKeys.push( key );
		if ( typeof value === 'string' ) {
			obj[ key ] = mw.msg( 'leafletdraw-' + newKeys.join( '-' ).toLowerCase() );
		} else if ( typeof value === 'object' ) {
			replaceMessages( value, newKeys );
		}
	}
}

// Start recursive replacement of messages
replaceMessages( L.drawLocal );
