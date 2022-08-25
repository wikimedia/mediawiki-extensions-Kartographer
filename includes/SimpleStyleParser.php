<?php

namespace Kartographer;

use FormatJson;
use JsonConfig\JCMapDataContent;
use JsonConfig\JCSingleton;
use JsonSchema\Validator;
use LogicException;
use MediaWiki\MediaWikiServices;
use Parser;
use Status;
use stdClass;

/**
 * Parses and sanitizes text properties of GeoJSON/simplestyle by putting them through parser
 */
class SimpleStyleParser {

	private const PARSED_PROPS = [ 'title', 'description' ];

	/** @var MediaWikiWikitextParser */
	private $parser;

	/** @var array */
	private $options;

	/** @var string */
	private $mapService;

	/**
	 * @param MediaWikiWikitextParser|Parser $parser
	 * @param array $options Set ['saveUnparsed' => true] to back up the original values of title
	 *                       and description in _origtitle and _origdescription
	 */
	public function __construct( $parser, array $options = [] ) {
		// TODO: Temporary compatibility, remove when not needed any more
		$this->parser = $parser instanceof Parser ? new MediaWikiWikitextParser( $parser ) : $parser;
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
	public function parse( $input ): Status {
		$input = trim( $input );
		$status = Status::newGood( [] );
		if ( $input !== '' ) {
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
	 * @param array|stdClass &$data
	 * @return Status
	 */
	public function parseObject( &$data ): Status {
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
	 * @param stdClass[]|stdClass &$data
	 * @return Status
	 */
	public function normalizeAndSanitize( &$data ): Status {
		$status = $this->normalize( $data );
		$this->sanitize( $data );
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
	 * @param mixed $json
	 * @return Status
	 */
	private function validateContent( $json ): Status {
		$schema = self::loadSchema();
		$validator = new Validator();
		$validator->check( $json, $schema );

		if ( !$validator->isValid() ) {
			$status = Status::newFatal( 'kartographer-error-bad_data' );
			foreach ( $validator->getErrors() as $error ) {
				$status->fatal(
					'kartographer-error-json-schema-error',
					wfEscapeWikiText( $error['pointer'] ?? '' ),
					wfEscapeWikiText( $error['message'] ?? '' )
				);
			}
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
	protected function sanitize( &$json ) {
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$this->sanitize( $element );
			}
		} elseif ( is_object( $json ) ) {
			foreach ( array_keys( get_object_vars( $json ) ) as $prop ) {
				// https://phabricator.wikimedia.org/T134719
				if ( str_starts_with( $prop, '_' ) ) {
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
	 * @param stdClass[]|stdClass &$json
	 * @return Status
	 */
	protected function normalize( &$json ): Status {
		$status = Status::newGood();
		if ( is_array( $json ) ) {
			foreach ( $json as &$element ) {
				$this->normalize( $element );
			}
		} elseif ( is_object( $json ) && isset( $json->type ) && $json->type === 'ExternalData' ) {
			$status->merge( $this->normalizeExternalData( $json ) );
		}
		$status->value = $json;

		return $status;
	}

	/**
	 * Canonicalizes an ExternalData object
	 *
	 * @param stdClass &$object
	 * @return Status
	 */
	private function normalizeExternalData( &$object ): Status {
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
				$ret->url = $this->mapService . '/' .
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
	 * Sanitizes properties
	 *
	 * HACK: this function supports JsonConfig-style localization that doesn't pass validation
	 *
	 * @param stdClass $properties
	 */
	private function sanitizeProperties( $properties ) {
		$saveUnparsed = $this->options['saveUnparsed'] ?? false;

		foreach ( self::PARSED_PROPS as $prop ) {
			if ( !property_exists( $properties, $prop ) ) {
				continue;
			}

			$origProp = "_orig$prop";
			$property = &$properties->$prop;

			if ( is_string( $property ) ) {
				if ( $saveUnparsed ) {
					$properties->$origProp = $property;
				}
				$property = $this->parser->parseWikitext( $property );
			} elseif ( is_object( $property ) ) {
				if ( $saveUnparsed ) {
					$properties->$origProp = (object)[];
				}
				foreach ( $property as $language => &$text ) {
					if ( !is_string( $text ) ) {
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

	/**
	 * @return stdClass
	 */
	private static function loadSchema(): stdClass {
		static $schema;

		if ( !$schema ) {
			$schema = (object)[
				'$ref' => 'file://' . dirname( __DIR__ ) . '/schemas/geojson.json',
			];
		}

		return $schema;
	}
}
