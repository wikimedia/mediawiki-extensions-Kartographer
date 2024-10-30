<?php

namespace Kartographer;

use InvalidArgumentException;
use JsonConfig\JCMapDataContent;
use JsonConfig\JCSingleton;
use JsonSchema\Validator;
use MediaWiki\Json\FormatJson;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use StatusValue;
use stdClass;

/**
 * Parses and sanitizes text properties of GeoJSON/simplestyle by putting them through the MediaWiki
 * wikitext parser.
 *
 * @license MIT
 */
class SimpleStyleParser {

	/**
	 * Maximum for marker-symbol="-number…" counters. See T141335 for discussion to possibly
	 * increase this to 199 or even 999.
	 */
	private const MAX_NUMERIC_COUNTER = 99;
	public const WIKITEXT_PROPERTIES = [ 'title', 'description' ];

	private WikitextParser $parser;
	private array $options;
	private string $mapServer;

	public static function newFromParser( Parser $parser, ?PPFrame $frame = null ): self {
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
	 * @return StatusValue with the value being [ 'data' => stdClass[], 'schema-errors' => array[] ]
	 */
	public function parse( ?string $input ): StatusValue {
		if ( !$input || trim( $input ) === '' ) {
			return StatusValue::newGood( [ 'data' => [] ] );
		}

		$status = FormatJson::parse( $input, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );
		if ( !$status->isOK() ) {
			return StatusValue::newFatal( 'kartographer-error-json', $status->getMessage() );
		}

		return $this->parseObject( $status->value );
	}

	/**
	 * Validate and sanitize a parsed GeoJSON data object
	 *
	 * @param array|stdClass &$data
	 * @return StatusValue
	 */
	public function parseObject( &$data ): StatusValue {
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
	 * @return StatusValue
	 */
	public function normalizeAndSanitize( &$data ): StatusValue {
		$status = $this->recursivelyNormalizeExternalData( $data );
		$this->recursivelySanitizeAndParseWikitext( $data );
		return $status;
	}

	/**
	 * @param stdClass[] $values
	 * @param array<string,int> &$counters
	 * @return array{string,stdClass}|null [ string $firstMarkerSymbol, stdClass $firstMarkerProperties ]
	 */
	public static function updateMarkerSymbolCounters( array $values, array &$counters = [] ): ?array {
		$firstMarker = null;
		foreach ( $values as $item ) {
			// While the input should be validated, it's still arbitrary user input.
			if ( !( $item instanceof stdClass ) ) {
				continue;
			}

			$marker = $item->properties->{'marker-symbol'} ?? '';
			$isNumber = str_starts_with( $marker, '-number' );
			if ( $isNumber || str_starts_with( $marker, '-letter' ) ) {
				// numbers 1..99 or letters a..z
				$count = $counters[$marker] ?? 0;
				if ( $count < ( $isNumber ? self::MAX_NUMERIC_COUNTER : 26 ) ) {
					$counters[$marker] = ++$count;
				}
				$marker = $isNumber ? strval( $count ) : chr( ord( 'a' ) + $count - 1 );
				$item->properties->{'marker-symbol'} = $marker;
				// GeoJSON is in lowercase, but the letter is shown as uppercase
				$firstMarker ??= [ mb_strtoupper( $marker ), $item->properties ];
			}

			// Recurse into FeatureCollection and GeometryCollection
			$features = $item->features ?? $item->geometries ?? null;
			if ( $features ) {
				$firstMarker ??= self::updateMarkerSymbolCounters( $features, $counters );
			}
		}
		return $firstMarker;
	}

	/**
	 * @param stdClass[] $values
	 * @return array{string,stdClass}|null Same as {@see updateMarkerSymbolCounters}, but with the
	 *  $firstMarkerSymbol name not updated
	 */
	public static function findFirstMarkerSymbol( array $values ): ?array {
		foreach ( $values as $item ) {
			// While the input should be validated, it's still arbitrary user input.
			if ( !( $item instanceof stdClass ) ) {
				continue;
			}

			$marker = $item->properties->{'marker-symbol'} ?? '';
			if ( str_starts_with( $marker, '-number' ) || str_starts_with( $marker, '-letter' ) ) {
				return [ $marker, $item->properties ];
			}

			// Recurse into FeatureCollection and GeometryCollection
			$features = $item->features ?? $item->geometries ?? null;
			if ( $features ) {
				$found = self::findFirstMarkerSymbol( $features );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * @param stdClass[] $data
	 * @return StatusValue
	 */
	private function validateGeoJSON( array $data ): StatusValue {
		// Basic top-level validation. The JSON schema validation below does this again, but gives
		// terrible, very hard to understand error messages.
		foreach ( $data as $geoJSON ) {
			if ( !( $geoJSON instanceof stdClass ) ) {
				return StatusValue::newFatal( 'kartographer-error-json-object' );
			}
			if ( !isset( $geoJSON->type ) || !is_string( $geoJSON->type ) || !$geoJSON->type ) {
				return StatusValue::newFatal( 'kartographer-error-json-type' );
			}
		}

		$schema = (object)[ '$ref' => 'file://' . dirname( __DIR__ ) . '/schemas/geojson.json' ];
		$validator = new Validator();
		$validator->check( $data, $schema );

		if ( !$validator->isValid() ) {
			$errors = $validator->getErrors( Validator::ERROR_DOCUMENT_VALIDATION );
			$status = StatusValue::newFatal( 'kartographer-error-bad_data' );
			$status->setResult( false, [ 'schema-errors' => $errors ] );
			return $status;
		}

		return StatusValue::newGood();
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
	 * @return StatusValue
	 */
	private function recursivelyNormalizeExternalData( &$json ): StatusValue {
		$status = StatusValue::newGood();
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$status->merge( $this->recursivelyNormalizeExternalData( $element ) );
			}
			unset( $element );
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
	 * @return StatusValue
	 */
	private function normalizeExternalDataServices( stdClass &$object ): StatusValue {
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
					return StatusValue::newFatal( 'kartographer-error-title', $object->title );
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
				throw new InvalidArgumentException( "Unexpected service name '$service'" );
		}

		$object = $ret;
		return StatusValue::newGood();
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
				unset( $text );

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
