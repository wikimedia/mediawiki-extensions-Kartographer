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
use ExtensionRegistry;
use FormatJson;
use Html;
use Kartographer\SimpleStyleParser;
use Kartographer\State;
use Language;
use Parser;
use ParserOutput;
use PPFrame;
use Status;
use stdClass;
use Title;

/**
 * Base class for all <map...> tags
 */
abstract class TagHandler {

	/** @var string */
	protected $tag;

	/** @var Status */
	private $status;

	/** @var stdClass[] */
	private $geometries = [];

	/** @var string[] */
	private $args;

	/** @var float */
	protected $lat;

	/** @var float */
	protected $lon;

	/** @var int */
	protected $zoom;

	/** @var string */
	protected $mapStyle;

	/** @var string|null */
	protected $specifiedLangCode;

	/** @var string */
	protected $resolvedLangCode;

	/** @var string|null name of the group, or null for private */
	private $groupName;

	/** @var string[] list of groups to show */
	protected $showGroups = [];

	/** @var int|null */
	protected $counter = null;

	/** @var Parser */
	protected $parser;

	/** @var PPFrame */
	protected $frame;

	/** @var State */
	protected $state;

	/** @var stdClass */
	protected $markerProperties;

	/**
	 * Entry point for all tags
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function entryPoint( $input, array $args, Parser $parser, PPFrame $frame ) {
		/** @phan-suppress-next-line PhanTypeInstantiateAbstractStatic */
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
	private function handle( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgKartographerMapServer;

		$this->parser = $parser;
		$this->frame = $frame;
		$output = $parser->getOutput();
		$output->addModuleStyles( 'ext.kartographer.style' );
		$output->addExtraCSPDefaultSrc( $wgKartographerMapServer );
		$this->state = State::getOrCreate( $output );

		$this->status = Status::newGood();
		$this->args = $args;

		$this->parseGeometries( $input, $parser, $frame );
		$this->parseGroups();
		$this->parseArgs();

		if ( !$this->status->isGood() ) {
			$result = $this->reportError();
			State::setState( $output, $this->state );
			return $result;
		}

		$this->saveData();

		$this->state->setValidTags();

		$result = $this->render();

		State::setState( $output, $this->state );
		return $result;
	}

	/**
	 * Parses and sanitizes GeoJSON+simplestyle contained inside of tags
	 *
	 * @param string $input
	 * @param Parser $parser
	 * @param PPFrame $frame
	 */
	private function parseGeometries( $input, Parser $parser, PPFrame $frame ) {
		$simpleStyle = new SimpleStyleParser( $parser, $frame );

		$this->status = $simpleStyle->parse( $input );
		if ( $this->status->isOK() ) {
			$this->geometries = $this->status->getValue();
		}
	}

	/**
	 * Parses tag attributes in $this->args
	 * @return void
	 */
	protected function parseArgs() {
		global $wgKartographerStyles, $wgKartographerDfltStyle, $wgKartographerUsePageLanguage;

		$this->lat = $this->getFloat( 'latitude', null );
		$this->lon = $this->getFloat( 'longitude', null );
		if ( ( $this->lat === null ) xor ( $this->lon === null ) ) {
			$this->status->fatal( 'kartographer-error-latlon' );
		}

		$this->zoom = $this->getInt( 'zoom', null );
		$regexp = '/^(' . implode( '|', $wgKartographerStyles ) . ')$/';
		$this->mapStyle = $this->getText( 'mapstyle', $wgKartographerDfltStyle, $regexp );

		$defaultLangCode = $wgKartographerUsePageLanguage ? $this->getLanguage()->getCode() : 'local';
		// Language code specified by the user (null if none)
		$this->specifiedLangCode = $this->getText( 'lang', null );
		// Language code we're going to use
		$this->resolvedLangCode = $this->specifiedLangCode ?? $defaultLangCode;
		// If the specified language code is invalid, behave as if no language was specified
		if (
			!Language::isKnownLanguageTag( $this->resolvedLangCode ) &&
			$this->resolvedLangCode !== 'local'
		) {
			$this->specifiedLangCode = null;
			$this->resolvedLangCode = $defaultLangCode;
		}
	}

	/**
	 * When overridden in a descendant class, returns tag HTML
	 * @return string
	 */
	abstract protected function render();

	private function parseGroups() {
		global $wgKartographerWikivoyageMode;

		if ( !$wgKartographerWikivoyageMode ) {
			// if we ignore all the 'group' and 'show' parameters,
			// each tag stays private, and will be unable to share data
			return;
		}

		$this->groupName = $this->getText( 'group', null, '/^[a-zA-Z0-9]+$/' );

		$text = $this->getText( 'show', null, '/^(|[a-zA-Z0-9]+(\s*,\s*[a-zA-Z0-9]+)*)$/' );
		if ( $text ) {
			$this->showGroups = array_map( 'trim', explode( ',', $text ) );
		}

		// Make sure the current group is shown for this map, even if there is no geojson
		// Private group will be added during the save, as it requires hash calculation
		if ( $this->groupName !== null ) {
			$this->showGroups[] = $this->groupName;
		}

		// Make sure there are no group name duplicates
		$this->showGroups = array_unique( $this->showGroups );
	}

	/**
	 * @param string $name
	 * @param string|bool|null $default
	 *
	 * @return int|bool|null
	 */
	protected function getInt( $name, $default = false ) {
		$value = $this->getText( $name, $default, '/^-?[0-9]+$/' );
		if ( $value !== false && $value !== null ) {
			$value = intval( $value );
		}

		return $value;
	}

	/**
	 * @param string $name
	 * @param string|bool|null $default
	 * @return float|bool|null
	 */
	private function getFloat( $name, $default = false ) {
		$value = $this->getText( $name, $default, '/^-?[0-9]*\.?[0-9]+$/' );
		if ( $value !== false && $value !== null ) {
			$value = floatval( $value );
		}

		return $value;
	}

	/**
	 * Returns value of a named tag attribute with optional validation
	 *
	 * @param string $name Attribute name
	 * @param string|bool|null $default Default value or false to trigger error if absent
	 * @param string|false $regexp Regular expression to validate against or false to not validate
	 * @return string|bool|null
	 */
	protected function getText( $name, $default, $regexp = false ) {
		if ( !isset( $this->args[$name] ) ) {
			if ( $default === false ) {
				$this->status->fatal( 'kartographer-error-missing-attr', $name );
			}
			return $default;
		}
		$value = trim( $this->args[$name] );
		if ( $regexp && !preg_match( $regexp, $value ) ) {
			$value = false;
			$this->status->fatal( 'kartographer-error-bad_attr', $name );
		}

		return $value;
	}

	private function saveData() {
		$this->state->addRequestedGroups( $this->showGroups );

		if ( !$this->geometries ) {
			return;
		}

		// Merge existing data with the new tag's data under the same group name

		// For all GeoJSON items whose marker-symbol value begins with '-counter' and '-letter',
		// recursively replace them with an automatically incremented marker icon.
		$counters = $this->state->getCounters();
		$marker = SimpleStyleParser::doCountersRecursive( $this->geometries, $counters );
		if ( $marker ) {
			list( $this->counter, $this->markerProperties ) = $marker;
		}
		$this->state->setCounters( $counters );

		if ( $this->groupName === null ) {
			$group = '_' . sha1( FormatJson::encode( $this->geometries, false, FormatJson::ALL_OK ) );
			$this->groupName = $group;
			$this->showGroups[] = $group;
			// no need to array_unique() because it's impossible to manually add a private group
		} else {
			$group = $this->groupName;
		}

		$this->state->addData( $group, $this->geometries );
	}

	/**
	 * Handles the last step of parse process
	 * @param State $state
	 * @param ParserOutput $output to exclusively write to; nothing is read from this object
	 * @param bool $isPreview
	 * @param Title $title required to properly add tracking categories
	 */
	public static function finalParseStep(
		State $state,
		ParserOutput $output,
		$isPreview,
		Title $title
	) {
		global $wgKartographerStaticMapframe;

		if ( $state->getMaplinks() ) {
			$output->setProperty( 'kartographer_links', $state->getMaplinks() );
		}
		if ( $state->getMapframes() ) {
			$output->setProperty( 'kartographer_frames', $state->getMapframes() );
		}

		if ( $state->hasBrokenTags() ) {
			self::addTrackingCategory( $output, 'kartographer-broken-category', $title );
		}
		if ( $state->hasValidTags() ) {
			self::addTrackingCategory( $output, 'kartographer-tracking-category', $title );
		}

		// https://phabricator.wikimedia.org/T145615 - include all data in previews
		$data = $state->getData();
		if ( $data && $isPreview ) {
			$output->addJsConfigVars( 'wgKartographerLiveData', $data );
			if ( $wgKartographerStaticMapframe ) {
				// Preview generates HTML that is different from normal
				$output->updateCacheExpiry( 0 );
			}
		} else {
			$interact = $state->getInteractiveGroups();
			$requested = array_keys( $state->getRequestedGroups() );
			if ( $interact || $requested ) {
				$interact = array_flip( $interact );
				$liveData = array_intersect_key( $data, $interact );
				$requested = array_unique( $requested );
				// Prevent pointless API requests for missing groups
				foreach ( $requested as $group ) {
					if ( !isset( $data[$group] ) ) {
						$liveData[$group] = [];
					}
				}
				$output->addJsConfigVars( 'wgKartographerLiveData', (object)$liveData );
			}
		}
	}

	/**
	 * Adds tracking category with extra checks
	 *
	 * @param ParserOutput $output
	 * @param string $categoryMsg
	 * @param Title $title
	 */
	private static function addTrackingCategory( ParserOutput $output, $categoryMsg, Title $title ) {
		static $hasParserFunctions;

		// Our tracking categories rely on ParserFunctions to differentiate per namespace,
		// avoid log noise if it's not installed
		if ( $hasParserFunctions === null ) {
			$hasParserFunctions = ExtensionRegistry::getInstance()->isLoaded( 'ParserFunctions' );
		}

		if ( $hasParserFunctions ) {
			$output->addTrackingCategory( $categoryMsg, $title );
		}
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function reportError() {
		$this->state->setBrokenTags();
		$errors = array_merge( $this->status->getErrorsByType( 'error' ),
			$this->status->getErrorsByType( 'warning' )
		);
		if ( !count( $errors ) ) {
			throw new Exception( __METHOD__ . '(): attempt to report error when none took place' );
		}
		$message = count( $errors ) > 1 ? 'kartographer-error-context-multi'
			: 'kartographer-error-context';
		// Status sucks, redoing a bunch of its code here
		$errorText = implode( "\n* ",
			array_map(
				function ( array $err ) {
					return wfMessage( $err['message'] )
						->params( $err['params'] )
						->inLanguage( $this->getLanguage() )
						->plain();
				},
				$errors
			)
		);
		if ( count( $errors ) > 1 ) {
			$errorText = '* ' . $errorText;
		}
		return Html::rawElement( 'div', [ 'class' => 'mw-kartographer-error' ],
			wfMessage( $message, $this->tag, $errorText )->inLanguage( $this->getLanguage() )->parse() );
	}

	/**
	 * @return Language
	 */
	protected function getLanguage() {
		return $this->parser->getTargetLanguage();
	}
}
