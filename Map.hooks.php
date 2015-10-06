<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Map;

use Html;
use Parser;

class Singleton {

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'map', 'Map\Singleton::onMapTag' );
		return true;
	}

	/**
	 * @param $input
	 * @param array $args
	 * @param Parser $parser
	 * @param \PPFrame $frame
	 * @return string
	 */
	public static function onMapTag( $input, /** @noinspection PhpUnusedParameterInspection */
	                                   array $args, Parser $parser, \PPFrame $frame ) {
		$parserOutput = $parser->getOutput();

		$zoom = self::validateNumber( $args, 'zoom', true );
		$latitude = self::validateNumber( $args, 'latitude', false );
		$longitude = self::validateNumber( $args, 'longitude', false );
		$width = self::validateNumber( $args, 'width', true );
		$height = self::validateNumber( $args, 'height', true );

		if ( $zoom === false || $width === false || $height === false ||
			 $latitude === false || $longitude === false
		) {
			$parserOutput->setExtensionData( 'map_broken', true );
			return $input;
		}

		$parserOutput->setExtensionData( 'map_valid', true );

		// https://maps.wikimedia.org/img/osm-intl,%1$s,%2$s,%3$s,%4$sx%5$s.jpeg
		// 1=zoom, 2=lat, 3=lon, 4=width, 5=height, [6=scale]
		// http://.../img/{source},{zoom},{lat},{lon},{width}x{height}[@{scale}x].{format}
		global $wgMapStaticImgUrl;
		$html = Html::rawElement( 'img', array(
			'class' => 'mw-wiki-map-img',
			'src' => sprintf( $wgMapStaticImgUrl, $zoom, $latitude, $longitude, $width, $height ),
		) );

		return $html;
	}

	public static function onParserAfterParse( Parser $parser ) {
		$output = $parser->getOutput();
		if ( $output->getExtensionData( 'map_broken' ) ) {
			$output->addTrackingCategory( 'map-broken-category', $parser->getTitle() );
		}
		if ( $output->getExtensionData( 'map_valid' ) ) {
			$output->addTrackingCategory( 'map-tracking-category', $parser->getTitle() );
		}
		return true;
	}

	private static function validateNumber( $args, $value, $isInt ) {
		if ( !isset( $args[$value] ) ) {
			return false;
		}
		$v = $args[$value];
		$pattern = $isInt ? '/^[0-9]+$/' : '/^[-+]?[0-9]*\.?[0-9]+$/';
		if ( !preg_match( $pattern, $v ) ) {
			return false;
		}
		return $isInt ? intval( $v ) : floatval( $v );
	}
}
