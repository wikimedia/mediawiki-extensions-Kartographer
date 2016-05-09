<?php

namespace Kartographer;

use FormatJson;
use JsonSchema\RefResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use Parser;
use PPFrame;
use Status;
use stdClass;

/**
 * Parses and sanitizes text properties of GeoJSON/simplestyle by putting them through parser
 */
class SimpleStyleParser {
	private static $parsedProps = [ 'title', 'description' ];

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
	 * @param PPFrame|null $frame
	 */
	public function __construct( Parser $parser, PPFrame $frame = null ) {
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/**
	 * Parses string into JSON and performs validation/sanitization
	 *
	 * @param string|null $input
	 * @return Status
	 */
	public function parse( $input ) {
		$input = trim( $input );
		$status = Status::newGood( [] );
		if ( $input !== '' && $input !== null ) {
			$status = FormatJson::parse( $input, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );
			if ( $status->isOK() ) {
				$json = $status->getValue();
				if ( !is_array( $json ) ) {
					$json = [ $json ];
				}
				$status = $this->validateContent( $json );
				if ( $status->isOK() ) {
					$this->sanitize( $json );
					$status = Status::newGood( $json );
				}
			} else {
				$status = Status::newFatal( 'kartographer-error-json', $status->getMessage() );
			}
		}

		return $status;
	}

	/**
	 * @param stdClass[] $values
	 * @param stdClass $counters counter-name -> integer
	 * @return bool|array [ marker, marker properties ]
	 */
	public static function doCountersRecursive( array &$values, &$counters ) {
		$firstMarker = false;
		if ( !is_array( $values ) ) {
			return $firstMarker;
		}
		foreach ( $values as $item ) {
			if ( property_exists( $item, 'properties' ) &&
				 property_exists( $item->properties, 'marker-symbol' )
			) {
				$marker = $item->properties->{'marker-symbol'};
				// all special markers begin with a dash
				// both 'number' and 'letter' have 6 symbols
				$type = substr( $marker, 0, 7 );
				$isNumber = $type === '-number';
				if ( $isNumber || $type === '-letter' ) {
					// numbers 1..99 or letters a..z
					$count = property_exists( $counters, $marker ) ? $counters->$marker : 0;
					if ( $count < ( $isNumber ? 99 : 26 ) ) {
						$counters->$marker = ++$count;
					}
					$marker = $isNumber ? strval( $count ) : chr( ord( 'a' ) + $count - 1 );
					$item->properties->{'marker-symbol'} = $marker;
					if ( $firstMarker === false ) {
						// GeoJSON is in lowercase, but the letter is shown as uppercase
						$firstMarker = [ mb_strtoupper( $marker ), $item->properties ];
					}
				}
			}
			if ( !property_exists( $item, 'type' ) ) {
				continue;
			}
			$type = $item->type;
			if ( $type === 'FeatureCollection' && property_exists( $item, 'features' ) ) {
				$tmp = self::doCountersRecursive( $item->features, $counters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			} elseif ( $type === 'GeometryCollection' && property_exists( $item, 'geometries' ) ) {
				$tmp = self::doCountersRecursive( $item->geometries, $counters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			}
		}
		return $firstMarker;
	}


	/**
	 * @param mixed $json
	 * @return Status
	 */
	private function validateContent( $json ) {
		$schema = self::loadSchema();
		$validator = new Validator();
		$validator->check( $json, $schema );

		if ( !$validator->isValid() ) {
			return Status::newFatal( 'kartographer-error-bad_data' );
		}

		return Status::newGood();
	}

	/**
	 * Performs recursive sanitizaton.
	 * Does not attempt to be smart, just recurses through everything that can be dangerous even
	 * if not a valid GeoJSON.
	 *
	 * @param object|array $json
	 */
	private function sanitize( &$json ) {
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$this->sanitize( $element );
			}
			return;
		} elseif ( !is_object( $json ) ) {
			return;
		}

		foreach ( array_keys( get_object_vars( $json ) ) as $prop ) {
			// https://phabricator.wikimedia.org/T134719
			if ( $prop[0] === '_' ) {
				unset( $json->$prop );
			} else {
				$this->sanitize( $json->$prop );
			}
		}

		if ( property_exists( $json, 'properties' ) && is_object( $json->properties ) ) {
			$this->sanitizeProperties( $json->properties );
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
					$properties->$prop = trim( Parser::stripOuterParagraph(
						$this->parser->recursiveTagParseFully( $properties->$prop, $this->frame )
					) );
				}
			}
		}
	}

	private static function loadSchema() {
		static $schema;

		if ( !$schema ) {
			$basePath = 'file://' . dirname( __DIR__ ) . '/schemas';
			$retriever = new UriRetriever();
			$resolver = new RefResolver( $retriever );
			RefResolver::$maxDepth = 20;
			$schema = $retriever->retrieve( "$basePath/geojson.json", $basePath );
			$resolver->resolve( $schema, $basePath );
		}
		return $schema;
	}
}
