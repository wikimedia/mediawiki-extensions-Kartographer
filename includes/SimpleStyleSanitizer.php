<?php

namespace Kartographer;

use Parser;
use PPFrame;

/**
 * Sanitizes text properties of GeoJSON/simplestyle by putting them through parser
 */
class SimpleStyleSanitizer {
	private static $parsedProps = array( 'title', 'description' );

	private static $recursedProps = array( 'geometry', 'geometries', 'features' );

	/** @var Parser */
	private $parser;

	/**
	 * @var PPFrame
	 */
	private $frame;

	/**
	 * Constructor
	 *
	 * @param Parser $parser Parser used for wikitext processing
	 * @param PPFrame $frame
	 */
	public function __construct( Parser $parser, PPFrame $frame ) {
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/**
	 * Performs recursive sanitizaton.
	 * Does not attempt to be smart, just recurses through everything that can be dangerous even
	 * if not a valid GeoJSON.
	 *
	 * @param object|array $json
	 */
	public function sanitize( &$json ) {
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$this->sanitize( $element );
			}
			return;
		} elseif ( !is_object( $json ) ) {
			return;
		}

		if ( property_exists( $json, 'properties' ) && is_object( $json->properties ) ) {
			$this->sanitizeProperties( $json->properties );
		}

		foreach ( self::$recursedProps as $prop ) {
			if ( property_exists( $json, $prop ) ) {
				$this->sanitize( $json->$prop );
			}
		}
	}

	/**
	 * Sanitizes properties
	 * @param object $properties
	 */
	private function sanitizeProperties( &$properties ) {
		foreach ( self::$parsedProps as $prop ) {
			if ( property_exists( $properties, $prop ) ) {
				if ( !is_string( $properties->$prop ) ) {
					unset( $properties->$prop ); // Dunno what the hell it is, ditch
				} else {
					$properties->$prop = Parser::stripOuterParagraph(
						$this->parser->recursiveTagParseFully( $properties->$prop, $this->frame )
					);
				}
			}
		}
	}
}
