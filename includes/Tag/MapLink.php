<?php

namespace Kartographer\Tag;

use FormatJson;
use Html;
use Kartographer\CoordFormatter;

/**
 * The <maplink> tag creates a link that, when clicked,
 */
class MapLink extends TagHandler {
	protected $tag = 'maplink';

	protected function parseArgs() {
		$this->parseMapArgs();
	}

	protected function render() {
		$counter = $this->counter;
		if ( is_numeric( $counter ) ) {
			$counter = $this->parser->getTargetLanguage()->formatNum( $counter );
		}
		$text = $this->getText( 'text', null, '/\S+/' );
		if ( $text === null ) {
			$text = $counter ?: CoordFormatter::format( $this->lat, $this->lon, $this->language );
		}
		$text = $this->parser->recursiveTagParse( $text, $this->frame );
		$attrs = $this->defaultAttributes;
		$attrs['class'] .= ' mw-kartographer-link';
		$attrs['data-style'] = $this->style;
		$attrs['data-zoom'] = $this->zoom;
		$attrs['data-lat'] = $this->lat;
		$attrs['data-lon'] = $this->lon;
		if ( $this->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $this->showGroups, false,
				FormatJson::ALL_OK );
		}

		return Html::rawElement( 'a', $attrs, $text );
	}
}
