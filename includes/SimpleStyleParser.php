<?php

namespace Kartographer;

use FormatJson;
use JsonSchema\Validator;
use MediaWiki\MediaWikiServices;
use Parser;
use PPFrame;
use Status;
use stdClass;

/**
 * Parses and sanitizes text properties of GeoJSON/simplestyle by putting them through parser
 */
class SimpleStyleParser {
	private static $parsedProps = [ 'title', 'description' ];

	private static $services = [ 'geoshape', 'geoline', 'geomask' ];

	/** @var Parser */
	private $parser;

	/** @var PPFrame */
	private $frame;

	/** @var string */
	private $mapService;

	/**
	 * Constructor
	 *
	 * @param Parser $parser Parser used for wikitext processing
	 * @param PPFrame|null $frame
	 */
	public function __construct( Parser $parser, PPFrame $frame = null ) {
		$this->parser = $parser;
		$this->frame = $frame;
		// @fixme: More precise config?
		$this->mapService = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'KartographerMapServer' );
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
					$status = $this->normalize( $json );
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
	 * Normalizes JSON
	 *
	 * @param array $json
	 * @return Status
	 */
	private function normalize( array &$json ) {
		$status = Status::newGood();
		foreach ( $json as &$object ) {
			if ( $object->type === 'ExternalData' ) {
				$status->merge( $this->normalizeExternalData( $object ) );
			}
		}
		$status->value = $json;

		return $status;
	}

	/**
	 * Canonicalizes an ExternalData object
	 *
	 * @param object &$object
	 * @return Status
	 */
	private function normalizeExternalData( &$object ) {
		if ( !in_array( $object->service, self::$services ) ) {
			return Status::newFatal( 'kartographer-error-service-name', $object->service );
		}

		$ret = (object)[
			'type' => 'ExternalData',
			'service' => $object->service,
		];

		$query = [
			'getgeojson' => 1
		];

		if ( property_exists( $object, 'ids' ) ) {
			$query['ids'] = is_array( $object->ids )
				? join( ',', $object->ids )
				: preg_replace( '/\s*,\s*/', ',', $object->ids );
		}
		if ( property_exists( $object, 'query' ) ) {
			$query['query'] = $object->query;
		}

		// 'geomask' service is the same as inverted geoshape service
		// Kartotherian does not support it, request it as geoshape
		$service = $object->service === 'geomask' ? 'geoshape' : $object->service;
		$ret->url = "{$this->mapService}/{$service}?" . wfArrayToCgi( $query );
		if ( property_exists( $object, 'properties' ) ) {
			$ret->properties = $object->properties;
		}

		$object = $ret;

		return Status::newGood();
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
			$schema = (object)[ '$ref' => "$basePath/geojson.json" ];
		}
		return $schema;
	}
}
