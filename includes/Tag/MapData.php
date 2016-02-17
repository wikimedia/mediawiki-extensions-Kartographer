<?php

namespace Kartographer\Tag;


/**
 * The <mapdata> tag adds geometry that can be used by <mapframe> or <maplink>
 */
class MapData extends TagHandler {
	protected function parseArgs() {
		// This tag is really really supposed to have actual data, y'know
		if ( !$this->geometries ) {
			$this->status->error( 'kartographer-error-no-geometry' );
		}
	}

	protected function render() {
		return '';
	}
}
