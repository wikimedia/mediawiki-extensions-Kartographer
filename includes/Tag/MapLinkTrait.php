<?php

namespace Kartographer\Tag;

use Config;
use FormatJson;
use Kartographer\SpecialMap;
use stdClass;

trait MapLinkTrait {

	/**
	 * Prepare MapLink array of attributes to be passed to the Node element
	 * @param array $options inject attributes from main TagHandler
	 * @param Config $configService inject Config from main TagHandler
	 * @return array
	 */
	private function prepareAttrs( array $options, Config $configService ): array {
		$attrs = [
			'class' => 'mw-kartographer-maplink',
			'data-mw' => 'interface',
			'data-style' => $options['mapStyle'],
			'href' => SpecialMap::link(
					$options['lat'],
					$options['lon'],
					$options['zoom'],
					$options['resolvedLangCode']
				)
				->getLocalURL()
		];

		if ( $options['zoom'] !== null ) {
			$attrs['data-zoom'] = (string)$options['zoom'];
		}

		if ( $options['lat'] !== null && $options['lon'] !== null ) {
			$attrs['data-lat'] = (string)$options['lat'];
			$attrs['data-lon'] = (string)$options['lon'];
		}

		if ( $options['specifiedLangCode'] !== null ) {
			$attrs['data-lang'] = (string)$options['specifiedLangCode'];
		}

		$style = $this->extractMarkerCss( $configService, $options['markerProperties'] );

		if ( $style ) {
			$attrs['class'] .= ' mw-kartographer-autostyled';
			$attrs['style'] = $style;
		}

		if ( $options['cssClass'] !== '' ) {
			$attrs['class'] .= ' ' . $options['cssClass'];
		}

		if ( $options['showGroups'] ) {
			$attrs['data-overlays'] = FormatJson::encode( $options['showGroups'], false,
				FormatJson::ALL_OK );
		}

		return $attrs;
	}

	/**
	 * Extracts CSS style to be used by the link from GeoJSON
	 * @param Config $configService inject Config from prepareAttrs
	 * @param ?stdClass $markerProperties marker properties object to extract CSS
	 * @return string
	 */
	private function extractMarkerCss( Config $configService, ?stdClass $markerProperties ): string {
		if ( $configService->get( 'KartographerUseMarkerStyle' )
			&& $markerProperties
			&& property_exists( $markerProperties, 'marker-color' )
			// JsonSchema already validates this value for us, however this regex will also fail
			// if the color is invalid
			&& preg_match( '/^#?((?:[\da-f]{3}){1,2})$/i', $markerProperties->{'marker-color'}, $m )
		) {
			return "background: #{$m[1]};";
		}

		return '';
	}
}
