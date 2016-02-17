<?php

namespace Kartographer\Tag;

use Html;

/**
 * The <maplink> tag creates a link that, when clicked,
 */
class MapLink extends TagHandler {

	protected function parseArgs() {
		$this->parseMapArgs();
	}

	protected function render() {
		$counter = $this->counter;
		if ( is_numeric( $counter ) ) {
			$counter = $this->parser->getTargetLanguage()->formatNum( $counter );
		}
		$text = $this->parser->recursiveTagParse(
			$this->getText( 'text', $counter, '/\S+/' ),
			$this->frame
		);
		$attrs = $this->defaultAttributes;
		$attrs['class'] .= ' mw-kartographer-link';
		$attrs['data-style'] = $this->style;
		$attrs['data-zoom'] = $this->zoom;
		$attrs['data-lat'] = $this->lat;
		$attrs['data-lon'] = $this->lon;

		return Html::rawElement( 'a', $attrs, $text );
	}
}
