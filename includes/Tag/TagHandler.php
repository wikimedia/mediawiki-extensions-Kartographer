<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer\Tag;

use Exception;
use FormatJson;
use Html;
use Kartographer\SimpleStyleSanitizer;
use Message;
use Parser;
use ParserOutput;
use PPFrame;
use Status;
use stdClass;

/**
 * Base class for all <map...> tags
 */
abstract class TagHandler {
	/** @var string */
	protected $tag;

	/** @var Status */
	protected $status;

	/** @var stdClass[] */
	protected $geometries = [];

	/** @var string[] */
	protected $args;

	/** @var float */
	protected $lat;

	/** @var float */
	protected $lon;

	/** @var int */
	protected $zoom;

	/** @var string */
	protected $style;

	/** @var string[] */
	protected $groups;

	/** @var string[] */
	protected $defaultAttributes;

	/** @var int|null */
	protected $counter = null;

	/** @var Parser */
	protected $parser;

	/** @var PPFrame */
	protected $frame;

	public function __construct() {
		$this->defaultAttributes = [ 'class' => 'mw-kartographer', 'mw-data' => 'interface' ];
	}

	/**
	 * Entry point for all tags
	 *
	 * @param $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function entryPoint( $input, array $args, Parser $parser, PPFrame $frame ) {
		$handler = new static();

		return $handler->handle( $input, $args, $parser, $frame );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	private final function handle( $input, array $args, Parser $parser, PPFrame $frame ) {
		$this->parser = $parser;
		$this->frame = $frame;
		$output = $parser->getOutput();
		$output->addModuleStyles( 'ext.kartographer.style' );

		$this->status = Status::newGood();
		$this->args = $args;

		$this->parseGeometries( $input, $parser, $frame );
		$this->parseGroups();
		$this->parseArgs();

		if ( !$this->status->isGood() ) {
			return $this->reportError();
		}

		$this->saveData( $output );

		$output->setExtensionData( 'kartographer_valid', true );

		return $this->render();
	}

	/**
	 * Parses and sanitizes GeoJSON+simplestyle contained inside of tags
	 *
	 * @param $input
	 * @param Parser $parser
	 * @param PPFrame $frame
	 */
	protected function parseGeometries( $input, Parser $parser, PPFrame $frame ) {
		$input = trim( $input );
		if ( $input !== '' && $input !== null ) {
			$status = FormatJson::parse( $input, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );
			if ( $status->isOK() ) {
				$json = $status->getValue();
				if ( !is_array( $json ) ) {
					$json = [ $json ];
				}
				$status = $this->validateContent( $json );
				$sanitizer = new SimpleStyleSanitizer( $parser, $frame );
				$sanitizer->sanitize( $json );
				$this->geometries = $json;
			}
		} else {
			$status = Status::newGood();
		}
		$this->status->merge( $status );
	}

	protected abstract function parseArgs();

	protected abstract function render();

	protected function parseMapArgs() {
		global $wgKartographerStyles, $wgKartographerDfltStyle;

		$this->lat = $this->getFloat( 'latitude' );
		$this->lon = $this->getFloat( 'longitude' );
		$this->zoom = $this->getInt( 'zoom' );
		$regexp = '/^(' . implode( '|', $wgKartographerStyles ) . ')$/';
		$this->style = $this->getText( 'style', $wgKartographerDfltStyle, $regexp );
	}

	private function parseGroups() {
		$text = $this->getText( 'group', '*', '/^[a-zA-Z0-9]+(\s*,\s*[a-zA-Z0-9]+)*$/' );
		$this->groups = array_map( 'trim', explode( ',', $text ) );
	}

	protected function getInt( $name, $default = false ) {
		$value = $this->getText( $name, $default, '/^-?[0-9]+$/' );
		if ( $value !== false ) {
			$value = intval( $value );
		}

		return $value;
	}

	protected function getFloat( $name, $default = false ) {
		$value = $this->getText( $name, $default, '/^-?[0-9]*\.?[0-9]+$/' );
		if ( $value !== false ) {
			$value = floatval( $value );
		}

		return $value;
	}


	protected function getText( $name, $default, $regexp = false ) {
		if ( !isset( $this->args[$name] ) ) {
			if ( $default === false ) {
				$this->status->fatal( 'kartographer-error-missing-attr', $name );
			}
			return $default;
		}
		$value = $this->args[$name];
		if ( $regexp && !preg_match( $regexp, $value ) ) {
			$value = false;
			$this->status->fatal( 'kartographer-error-bad_attr', $name );
		}

		return $value;
	}


	protected function saveData( ParserOutput $output ) {
		if ( !$this->geometries ) {
			return;
		}

		// Merge existing data with the new tag's data under the same group name

		// For all GeoJSON items whose marker-symbol value begins with '-counter' and '-letter',
		// recursively replace them with an automatically incremented marker icon.
		$counters = $output->getExtensionData( 'kartographer_counters' ) ?: new stdClass();
		$this->counter = $this->doCountersRecursive( $this->geometries, $counters );
		$output->setExtensionData( 'kartographer_counters', $counters );


		//$group = '_' . sha1( FormatJson::encode( $this->geometries, false, FormatJson::ALL_OK ) );
		$group = '*';
		$data = $output->getExtensionData( 'kartographer_data' ) ?: new stdClass();
		if ( isset( $data->$group ) ) {
			$data->$group = array_merge( $data->$group, $this->geometries );
		} else {
			$data->$group = $this->geometries;
		}
		$output->setExtensionData( 'kartographer_data', $data );
	}

	/**
	 * Handles the last step of parse process
	 * @param Parser $parser
	 */
	public static function finalParseStep( Parser $parser ) {
		$output = $parser->getOutput();

		if ( $output->getExtensionData( 'kartographer_broken' ) ) {
			$output->addTrackingCategory( 'kartographer-broken-category', $parser->getTitle() );
		}
		if ( $output->getExtensionData( 'kartographer_valid' ) ) {
			$output->addTrackingCategory( 'kartographer-tracking-category', $parser->getTitle() );
		}
		if ( $output->getExtensionData( 'kartographer_interact' ) ) {
			$output->addJsConfigVars( 'wgKartographerLiveData', $output->getExtensionData( 'kartographer_data' ) );
		}
	}

	/**
	 * @param mixed $json
	 * @return mixed
	 */
	private function validateContent( $json ) {
		// The content must be a non-associative array of values or an object
		if ( !is_array( $json ) ) {
			return Status::newFatal( 'kartographer-error-bad_data' );
		}

		return Status::newGood();
	}

	/**
	 * @param $values
	 * @param stdClass $counters counter-name -> integer
	 * @return bool|string returns the very first counter value that has been used
	 */
	private function doCountersRecursive( $values, &$counters ) {
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
						$firstMarker = $marker;
					}
				}
			}
			if ( !property_exists( $item, 'type' ) ) {
				continue;
			}
			$type = $item->type;
			if ( $type === 'FeatureCollection' && property_exists( $item, 'features' ) ) {
				$tmp = $this->doCountersRecursive( $item->features, $counters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			} elseif ( $type === 'GeometryCollection' && property_exists( $item, 'geometries' ) ) {
				$tmp = $this->doCountersRecursive( $item->geometries, $counters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			}
		}
		return $firstMarker;
	}

	/**
	 * @return string
	 */
	private function reportError() {
		$this->parser->getOutput()->setExtensionData( 'kartographer_broken', true );
		$errors = array_merge( $this->status->getErrorsByType( 'error' ),
			$this->status->getErrorsByType( 'warning' )
		);
		if ( !count( $errors ) ) {
			throw new Exception( __METHOD__ , '(): attempt to report error when none took place' );
		}
		$lang = $this->parser->getTitle()->getPageLanguage();
		$message = count( $errors ) > 1 ? 'kartographer-error-context-multi'
			: 'kartographer-error-context';
		// Status sucks, redoing a bunch of its code here
		$errorText = implode( "\n* ", array_map( function( array $err ) use ( $lang ) {
				return wfMessage( $err['message'] )
					->params( $err['params'] )
					->inLanguage( $lang )
					->plain();
			}, $errors ) );
		if ( count( $errors ) > 1 ) {
			$errorText = '* ' . $errorText;
		}
		return Html::rawElement( 'div', array( 'class' => 'mw-kartographer mw-kartographer-error' ),
			wfMessage( $message, $this->tag, $errorText )->inLanguage( $lang )->parse() );
	}
}
