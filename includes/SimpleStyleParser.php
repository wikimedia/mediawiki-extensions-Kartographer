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
 * Parses and sanitizes text properties of GeoJSON/simplestyle by putting them through the MediaWiki
 * wikitext parser.
 *
 * @license MIT
 */
class SimpleStyleParser {

	private const WIKITEXT_PROPERTIES = [ 'title', 'description' ];

	/** @var WikitextParser */
	private WikitextParser $parser;

	/** @var array */
	private array $options;

	/** @var string */
	private $mapServer;

	/**
	 * @param Parser $parser
	 * @param PPFrame|null $frame
	 * @return self
	 */
	public static function newFromParser( Parser $parser, PPFrame $frame = null ): self {
		return new self( new MediaWikiWikitextParser( $parser, $frame ) );
	}

	/**
	 * @param WikitextParser $parser
	 * @param array $options Set ['saveUnparsed' => true] to back up the original values of title
	 *                       and description in _origtitle and _origdescription
	 */
	public function __construct( WikitextParser $parser, array $options = [] ) {
		$this->parser = $parser;
		$this->options = $options;
		// @fixme: More precise config?
		$this->mapServer = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'KartographerMapServer' );
	}

	/**
	 * Parses string into JSON and performs validation/sanitization
	 *
	 * @param string|null $input
	 * @return Status with the value being [ 'data' => stdClass[], 'schema-errors' => array[] ]
	 */
	public function parse( ?string $input ): Status {
		if ( !$input || trim( $input ) === '' ) {
			return Status::newGood( [ 'data' => [] ] );
		}

		$status = FormatJson::parse( $input, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );
		if ( !$status->isOK() ) {
			return Status::newFatal( 'kartographer-error-json', $status->getMessage() );
		}

		return $this->parseObject( $status->value );
	}

	/**
	 * Validate and sanitize a parsed GeoJSON data object
	 *
	 * @param array|stdClass &$data
	 * @return Status
	 */
	public function parseObject( &$data ): Status {
		if ( !is_array( $data ) ) {
			$data = [ $data ];
		}
		$status = $this->validateGeoJSON( $data );
		if ( $status->isOK() ) {
			$status = $this->normalizeAndSanitize( $data );
		}
		return $status;
	}

	/**
	 * @param stdClass[]|stdClass &$data
	 * @return Status
	 */
	public function normalizeAndSanitize( &$data ): Status {
		$status = $this->recursivelyNormalizeExternalData( $data );
		$this->recursivelySanitizeAndParseWikitext( $data );
		return $status;
	}

	/**
	 * @param stdClass[] $values
	 * @param int[] &$counters
	 * @return array|false [ string $markerSymbol, stdClass $markerProperties ]
	 */
	public static function updateMarkerSymbolCounters( array $values, array &$counters = [] ) {
		$firstMarker = false;
		foreach ( $values as $item ) {
			// While the input should be validated, it's still arbitrary user input.
			if ( !( $item instanceof stdClass ) ) {
				continue;
			}

			if ( isset( $item->properties->{'marker-symbol'} ) ) {
				$marker = $item->properties->{'marker-symbol'};
				// all special markers begin with a dash
				// both 'number' and 'letter' have 6 symbols
				$type = substr( $marker, 0, 7 );
				$isNumber = $type === '-number';
				if ( $isNumber || $type === '-letter' ) {
					// numbers 1..99 or letters a..z
					$count = $counters[$marker] ?? 0;
					if ( $count < ( $isNumber ? 99 : 26 ) ) {
						$counters[$marker] = ++$count;
					}
					$marker = $isNumber ? strval( $count ) : chr( ord( 'a' ) + $count - 1 );
					$item->properties->{'marker-symbol'} = $marker;
					if ( $firstMarker === false ) {
						// GeoJSON is in lowercase, but the letter is shown as uppercase
						$firstMarker = [ mb_strtoupper( $marker ), $item->properties ];
					}
				}
			}
			if ( !isset( $item->type ) ) {
				continue;
			}
			$type = $item->type;
			if ( $type === 'FeatureCollection' && isset( $item->features ) ) {
				$tmp = self::updateMarkerSymbolCounters( $item->features, $counters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			} elseif ( $type === 'GeometryCollection' && isset( $item->geometries ) ) {
				$tmp = self::updateMarkerSymbolCounters( $item->geometries, $counters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			}
		}
		return $firstMarker;
	}

	/**
	 * @param stdClass[] $values
	 * @return array|null Same as {@see updateMarkerSymbolCounters}, but with the $markerSymbol
	 *  name not updated
	 */
	public static function findFirstMarkerSymbol( array $values ): ?array {
		foreach ( $values as $item ) {
			// While the input should be validated, it's still arbitrary user input.
			if ( !( $item instanceof stdClass ) ) {
				continue;
			}

			if ( isset( $item->properties->{'marker-symbol'} ) ) {
				$marker = $item->properties->{'marker-symbol'};
				if ( str_starts_with( $marker, '-number' ) || str_starts_with( $marker, '-letter' ) ) {
					return [ $marker, $item->properties ];
				}
			}

			if ( !isset( $item->type ) ) {
				continue;
			}

			$type = $item->type;
			if ( $type === 'FeatureCollection' && isset( $item->features ) ) {
				$found = self::findFirstMarkerSymbol( $item->features );
				if ( $found ) {
					return $found;
				}
			} elseif ( $item->type === 'GeometryCollection' && isset( $item->geometries ) ) {
				$found = self::findFirstMarkerSymbol( $item->geometries );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * @param stdClass[] $data
	 * @return Status
	 */
	private function validateGeoJSON( array $data ): Status {
		// Basic top-level validation. The JSON schema validation below does this again, but gives
		// terrible, very hard to understand error messages.
		foreach ( $data as $geoJSON ) {
			if ( !( $geoJSON instanceof stdClass ) ) {
				return Status::newFatal( 'kartographer-error-json-object' );
			}
			if ( !isset( $geoJSON->type ) || !is_string( $geoJSON->type ) || !$geoJSON->type ) {
				return Status::newFatal( 'kartographer-error-json-type' );
			}
		}

		$schema = (object)[ '$ref' => 'file://' . dirname( __DIR__ ) . '/schemas/geojson.json' ];
		$validator = new Validator();
		$validator->check( $data, $schema );

		if ( !$validator->isValid() ) {
			$errors = $validator->getErrors( Validator::ERROR_DOCUMENT_VALIDATION );
			$status = Status::newFatal( 'kartographer-error-bad_data' );
			$status->setResult( false, [ 'schema-errors' => $errors ] );
			return $status;
		}

		return Status::newGood();
	}

	/**
	 * Performs recursive sanitizaton.
	 * Does not attempt to be smart, just recurses through everything that can be dangerous even
	 * if not a valid GeoJSON.
	 *
	 * @param stdClass[]|stdClass &$json
	 */
	private function recursivelySanitizeAndParseWikitext( &$json ): void {
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$this->recursivelySanitizeAndParseWikitext( $element );
			}
		} elseif ( is_object( $json ) ) {
			foreach ( array_keys( get_object_vars( $json ) ) as $prop ) {
				// https://phabricator.wikimedia.org/T134719
				if ( str_starts_with( $prop, '_' ) ) {
					unset( $json->$prop );
				} else {
					$this->recursivelySanitizeAndParseWikitext( $json->$prop );
				}
			}

			if ( isset( $json->properties ) && is_object( $json->properties ) ) {
				$this->parseWikitextProperties( $json->properties );
			}
		}
	}

	/**
	 * @param stdClass[]|stdClass &$json
	 * @return Status
	 */
	private function recursivelyNormalizeExternalData( &$json ): Status {
		$status = Status::newGood();
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$status->merge( $this->recursivelyNormalizeExternalData( $element ) );
			}
		} elseif ( is_object( $json ) && isset( $json->type ) && $json->type === 'ExternalData' ) {
			$status->merge( $this->normalizeExternalDataServices( $json ) );
		}
		$status->value = [ 'data' => $json ];

		return $status;
	}

	/**
	 * Canonicalizes an ExternalData object
	 *
	 * @param stdClass &$object
	 * @return Status
	 */
	private function normalizeExternalDataServices( stdClass &$object ): Status {
		$service = $object->service ?? null;
		$ret = (object)[
			'type' => 'ExternalData',
			'service' => $service,
		];

		switch ( $service ) {
			case 'geoshape':
			case 'geopoint':
			case 'geoline':
			case 'geomask':
				$query = [ 'getgeojson' => 1 ];
				if ( isset( $object->ids ) ) {
					$query['ids'] =
						is_array( $object->ids ) ? implode( ',', $object->ids )
							: preg_replace( '/\s*,\s*/', ',', $object->ids );
				}
				if ( isset( $object->query ) ) {
					$query['query'] = $object->query;
				}
				$ret->url = $this->mapServer . '/' .
					// 'geomask' service is the same as inverted geoshape service
					// Kartotherian does not support it, request it as geoshape
					( $service === 'geomask' ? 'geoshape' : $service ) .
					'?' . wfArrayToCgi( $query );
				if ( isset( $object->properties ) ) {
					$ret->properties = $object->properties;
				}
				break;

			case 'page':
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
				throw new LogicException( "Unexpected service name '$service'" );
		}

		$object = $ret;
		return Status::newGood();
	}

	/**
	 * HACK: this function supports JsonConfig-style localization that doesn't pass validation
	 *
	 * @param stdClass $properties
	 */
	private function parseWikitextProperties( stdClass $properties ) {
		$saveUnparsed = $this->options['saveUnparsed'] ?? false;

		foreach ( self::WIKITEXT_PROPERTIES as $prop ) {
			if ( !property_exists( $properties, $prop ) ) {
				continue;
			}

			$origProp = "_orig$prop";
			$property = &$properties->$prop;

			if ( is_string( $property ) && $property !== '' ) {
				if ( $saveUnparsed ) {
					$properties->$origProp = $property;
				}
				$property = $this->parser->parseWikitext( $property );
			} elseif ( is_object( $property ) ) {
				if ( $saveUnparsed ) {
					$properties->$origProp = (object)[];
				}
				foreach ( $property as $language => &$text ) {
					if ( !is_string( $text ) || $text === '' ) {
						unset( $property->$language );
					} else {
						if ( $saveUnparsed ) {
							$properties->$origProp->$language = $text;
						}
						$text = $this->parser->parseWikitext( $text );
					}
				}

				// Delete empty localizations
				if ( !get_object_vars( $property ) ) {
					unset( $properties->$prop );
					unset( $properties->$origProp );
				}
			} else {
				// Dunno what the hell it is, ditch
				unset( $properties->$prop );
			}
		}
	}

}
