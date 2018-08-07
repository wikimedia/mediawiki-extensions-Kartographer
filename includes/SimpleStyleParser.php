<?php

namespace Kartographer;

use FormatJson;
use JsonConfig\JCMapDataContent;
use JsonConfig\JCSingleton;
use JsonSchema\Validator;
use LogicException;
use MediaWiki\MediaWikiServices;
use Parser;
use PPFrame;
use Status;
use stdClass;

/**
 * Parses and sanitizes text properties of GeoJSON/simplestyle by putting them through parser
 */
class SimpleStyleParser {

	/** @var string[] */
	private static $parsedProps = [ 'title', 'description' ];

	/** @var Parser */
	private $parser;

	/** @var PPFrame */
	private $frame;

	/** @var array */
	private $options;

	/** @var string */
	private $mapService;

	/**
	 * Constructor
	 *
	 * @param Parser $parser Parser used for wikitext processing
	 * @param PPFrame|null $frame
	 * @param array $options Set ['saveUnparsed' => true] to back up the original values of title
	 *                       and descrition in _origtitle and _origdescription
	 */
	public function __construct( Parser $parser, PPFrame $frame = null, array $options = [] ) {
		$this->parser = $parser;
		$this->frame = $frame;
		$this->options = $options;
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
				$status = $this->parseObject( $status->value );
			} else {
				$status = Status::newFatal( 'kartographer-error-json', $status->getMessage() );
			}
		}

		return $status;
	}

	/**
	 * Validate and sanitize a parsed GeoJSON data object
	 *
	 * @param array|object &$data
	 * @return Status
	 */
	public function parseObject( &$data ) {
		if ( !is_array( $data ) ) {
			$data = [ $data ];
		}
		$status = $this->validateContent( $data );
		if ( $status->isOK() ) {
			$status = $this->normalizeAndSanitize( $data );
		}
		return $status;
	}

	/**
	 * Normalize an object
	 *
	 * @param stdClass[] &$data
	 * @return Status
	 */
	public function normalizeAndSanitize( &$data ) {
		$status = $this->normalize( $data );
		$this->sanitize( $data );
		return $status;
	}

	/**
	 * @param stdClass[] &$values
	 * @param stdClass &$counters counter-name -> integer
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
	protected function validateContent( $json ) {
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
	 * @param object|array &$json
	 */
	protected function sanitize( &$json ) {
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$this->sanitize( $element );
			}
		} elseif ( is_object( $json ) ) {
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
	}

	/**
	 * Normalizes JSON
	 *
	 * @param array &$json
	 * @return Status
	 */
	protected function normalize( array &$json ) {
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
		$ret = (object)[
			'type' => 'ExternalData',
			'service' => $object->service,
		];

		switch ( $object->service ) {
			case 'geoshape':
			case 'geoline':
			case 'geomask':
				$query = [ 'getgeojson' => 1 ];
				if ( property_exists( $object, 'ids' ) ) {
					$query['ids'] =
						is_array( $object->ids ) ? implode( ',', $object->ids )
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
				break;

			case 'page':
				if ( !class_exists( 'JsonConfig\\JCSingleton' ) ) {
					return Status::newFatal( 'kartographer-error-service-name', $object->service );
				}
				$jct = JCSingleton::parseTitle( $object->title, NS_DATA );
				if ( !$jct || JCSingleton::getContentClass( $jct->getConfig()->model ) !==
							  JCMapDataContent::class
				) {
					return Status::newFatal( 'kartographer-error-title', $object->title );
				}
				$query = [
					'format' => 'json',
					'formatversion' => '2',
					'action' => 'jsondata',
					'title' => $jct->getText(),
				];
				$ret->url = wfScript( 'api' ) . '?' . wfArrayToCgi( $query );
				break;

			default:
				throw new LogicException( "Unexpected service name '{$object->service}'" );
		}

		$object = $ret;
		return Status::newGood();
	}

	/**
	 * Sanitizes properties
	 *
	 * HACK: this function supports JsonConfig-style localization that doesn't pass validation
	 *
	 * @param object &$properties
	 */
	private function sanitizeProperties( &$properties ) {
		$saveUnparsed = isset( $this->options['saveUnparsed'] ) && $this->options['saveUnparsed'];
		foreach ( self::$parsedProps as $prop ) {
			if ( property_exists( $properties, $prop ) ) {
				$property = &$properties->$prop;

				if ( is_string( $property ) ) {
					if ( $saveUnparsed ) {
						$properties->{"_orig$prop"} = $property;
					}
					$property = $this->parseText( $property );
				} elseif ( is_object( $property ) ) {
					// Delete empty localizations
					if ( !count( get_object_vars( $property ) ) ) {
						unset( $properties->$prop );
					} else {
						if ( $saveUnparsed ) {
							$properties->{"_orig$prop"} = $property;
						}
						foreach ( $property as $language => &$text ) {
							if ( !is_string( $text ) ) {
								unset( $property->$language );
							} else {
								$text = $this->parseText( $text );
							}
						}
					}
				} else {
					unset( $properties->$prop ); // Dunno what the hell it is, ditch
				}
			}
		}
	}

	/**
	 * Parses property wikitext into HTML
	 *
	 * @param string $text
	 * @return string
	 */
	private function parseText( $text ) {
		$text = $this->parser->recursiveTagParseFully( $text, $this->frame );
		return trim( Parser::stripOuterParagraph( $text ) );
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
