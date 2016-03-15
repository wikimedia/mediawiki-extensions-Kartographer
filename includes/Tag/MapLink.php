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

	protected function render() {
		// @todo: Mapbox markers don't support localized numbers yet
		$text = $this->getText( 'text', null, '/\S+/' );
		if ( $text === null ) {
			$text = $this->counter
				?: CoordFormatter::format( $this->lat, $this->lon, $this->getLanguage() );
		}
		$text = $this->parser->recursiveTagParse( $text, $this->frame );
		$style = $this->extractMarkerCss();

		$attrs = $this->getDefaultAttributes( $style );
		$attrs['class'] .= ' mw-kartographer-link';
		if ( $style ) {
			$attrs['class'] .= ' mw-kartographer-autostyled';
		}
		$attrs['data-style'] = $this->mapStyle;
		$attrs['data-zoom'] = $this->zoom;
		$attrs['data-lat'] = $this->lat;
		$attrs['data-lon'] = $this->lon;
		if ( $this->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $this->showGroups, false,
				FormatJson::ALL_OK );
		}

		return Html::rawElement( 'a', $attrs, $text );
	}

	/**
	 * Extracts CSS style to be used by the link from GeoJSON
	 * @return string
	 */
	private function extractMarkerCss() {
		global $wgKartographerUseMarkerStyle;

		if ( $wgKartographerUseMarkerStyle
			&& $this->markerProperties
			&& property_exists( $this->markerProperties, 'marker-color' )
		) {
			preg_match( '/^#?(([0-9a-fA-F]{3}){1,2})$/', $this->markerProperties->{'marker-color'}, $m );
			if ( $m && $m[2] ) {
				return "background: #{$m[2]};";
			}
		}

		return '';
	}
}
