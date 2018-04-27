<?php

namespace Kartographer\Tag;

use FormatJson;
use Html;
use Kartographer\CoordFormatter;
use Kartographer\SpecialMap;

/**
 * The <maplink> tag creates a link that, when clicked,
 */
class MapLink extends TagHandler {
	protected $tag = 'maplink';

	protected $cssClass;

	protected function parseArgs() {
		$this->state->useMaplink();
		parent::parseArgs();
		$this->cssClass = $this->getText( 'class', '', '/^(|[a-zA-Z][-_a-zA-Z0-9]*)$/' );
	}

	protected function render() {
		$output = $this->parser->getOutput();
		$output->addModules( 'ext.kartographer.link' );

		// @todo: Mapbox markers don't support localized numbers yet
		$text = $this->getText( 'text', null, '/\S+/' );
		if ( $text === null ) {
			$text = $this->counter
				?: CoordFormatter::format( $this->lat, $this->lon, $this->getLanguage() );
		}
		$text = $this->parser->recursiveTagParse( $text, $this->frame );

		$attrs = [
			'class' => 'mw-kartographer-maplink',
			'mw-data' => 'interface',
			'data-style' => $this->mapStyle,
			'href' => SpecialMap::link( $this->lat, $this->lon, $this->zoom, $this->resolvedLangCode )
				->getLocalURL()
		];

		if ( $this->zoom !== null ) {
			$attrs['data-zoom'] = $this->zoom;
		}
		if ( $this->lat !== null && $this->lon !== null ) {
			$attrs['data-lat'] = $this->lat;
			$attrs['data-lon'] = $this->lon;
		}
		if ( $this->specifiedLangCode !== null ) {
			$attrs['data-lang'] = $this->specifiedLangCode;
		}
		$style = $this->extractMarkerCss();
		if ( $style ) {
			$attrs['class'] .= ' mw-kartographer-autostyled';
			$attrs['style'] = $style;
		}
		if ( $this->cssClass !== '' ) {
			$attrs['class'] .= ' ' . $this->cssClass;
		}
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
			// JsonSchema already validates this value for us, however this regex will also fail
			// if the color is invalid
			preg_match( '/^#?(([0-9a-fA-F]{3}){1,2})$/', $this->markerProperties->{'marker-color'}, $m );
			if ( $m && $m[2] ) {
				return "background: #{$m[1]};";
			}
		}

		return '';
	}
}
