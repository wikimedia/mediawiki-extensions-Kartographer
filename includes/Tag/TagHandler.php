<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer\Tag;

use Config;
use Exception;
use ExtensionRegistry;
use FormatJson;
use Html;
use Kartographer\MediaWikiWikitextParser;
use Kartographer\SimpleStyleParser;
use Kartographer\State;
use Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Parser;
use ParserOutput;
use PPFrame;
use Status;
use stdClass;
use StubUserLang;

/**
 * Base class for all <map...> tags
 */
abstract class TagHandler {

	public const TAG = null;

	/** @var Status */
	private $status;

	/** @var stdClass[] */
	private $geometries = [];

	/** @var string[] */
	private $args;

	/** @var float|null */
	protected $lat;

	/** @var float|null */
	protected $lon;

	/** @var int|null */
	protected $zoom;

	/** @var string One of "osm-intl" or "osm" */
	protected $mapStyle;

	/** @var string|null */
	protected $specifiedLangCode;

	/** @var string */
	protected $resolvedLangCode;

	/**
	 * @var string|null Currently parsed group identifier from the group="…" attribute. Only allowed
	 *  in …WikivoyageMode. Otherwise a private, auto-generated identifier starting with "_".
	 */
	private $groupId;

	/** @var string[] List of group identifiers to show */
	protected $showGroups = [];

	/** @var int|null */
	protected $counter = null;

	/** @var Config */
	protected $config;

	/** @var Parser */
	protected $parser;

	/** @var PPFrame */
	protected $frame;

	/** @var State */
	protected $state;

	/** @var stdClass|null */
	protected $markerProperties;

	/**
	 * Entry point for all tags
	 *
	 * @param string|null $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function entryPoint( $input, array $args, Parser $parser, PPFrame $frame ): string {
		/** @phan-suppress-next-line PhanTypeInstantiateAbstractStatic */
		$handler = new static();

		return $handler->handle( $input, $args, $parser, $frame );
	}

	/**
	 * @param string|null $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	private function handle( $input, array $args, Parser $parser, PPFrame $frame ): string {
		$this->config = MediaWikiServices::getInstance()->getMainConfig();
		$this->parser = $parser;
		$this->frame = $frame;
		$parserOutput = $parser->getOutput();

		$parserOutput->addModuleStyles( [ 'ext.kartographer.style' ] );
		$parserOutput->addExtraCSPDefaultSrc(
			$this->config->get( 'KartographerMapServer' )
		);
		$this->state = State::getOrCreate( $parserOutput );

		$this->status = Status::newGood();
		$this->args = $args;

		$this->parseGeometries( $input, $parser, $frame );
		$this->parseGroups();
		$this->parseArgs();

		if ( !$this->status->isGood() ) {
			$result = $this->reportError();
			State::setState( $parserOutput, $this->state );
			return $result;
		}

		$this->saveData();

		$this->state->setValidTags();

		$result = $this->render();

		State::setState( $parserOutput, $this->state );
		return $result;
	}

	/**
	 * Parses and sanitizes GeoJSON+simplestyle contained inside of tags
	 *
	 * @param string|null $input
	 * @param Parser $parser
	 * @param PPFrame $frame
	 */
	private function parseGeometries( $input, Parser $parser, PPFrame $frame ) {
		$simpleStyle = new SimpleStyleParser( new MediaWikiWikitextParser( $parser, $frame ) );

		$this->status = $simpleStyle->parse( $input );
		if ( $this->status->isOK() ) {
			$this->geometries = $this->status->getValue();
		}
	}

	/**
	 * Parses tag attributes in $this->args
	 */
	protected function parseArgs(): void {
		$services = MediaWikiServices::getInstance();

		$this->lat = $this->getFloat( 'latitude', null );
		$this->lon = $this->getFloat( 'longitude', null );
		if ( ( $this->lat === null ) xor ( $this->lon === null ) ) {
			$this->status->fatal( 'kartographer-error-latlon' );
		}

		$this->zoom = $this->getInt( 'zoom', null );
		$regexp = '/^(' . implode( '|', $this->config->get( 'KartographerStyles' ) ) . ')$/';
		$this->mapStyle = $this->getText( 'mapstyle', $this->config->get( 'KartographerDfltStyle' ), $regexp );

		$defaultLangCode = $this->config->get( 'KartographerUsePageLanguage' ) ?
			$this->getLanguage()->getCode() :
			'local';
		// Language code specified by the user (null if none)
		$this->specifiedLangCode = $this->getText( 'lang', null );
		// Language code we're going to use
		$this->resolvedLangCode = $this->specifiedLangCode ?? $defaultLangCode;
		// If the specified language code is invalid, behave as if no language was specified
		if (
			!$services->getLanguageNameUtils()->isKnownLanguageTag( $this->resolvedLangCode ) &&
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
	abstract protected function render(): string;

	private function parseGroups() {
		if ( !$this->config->get( 'KartographerWikivoyageMode' ) ) {
			// if we ignore all the 'group' and 'show' parameters,
			// each tag stays private, and will be unable to share data
			return;
		}

		$this->groupId = $this->getText( 'group', null, '/^(\w| )+$/u' );

		$text = $this->getText( 'show', null, '/^(|(\w| )+(\s*,\s*(\w| )+)*)$/u' );
		if ( $text ) {
			$this->showGroups = array_map( 'trim', explode( ',', $text ) );
		}

		// Make sure the current group is shown for this map, even if there is no geojson
		// Private group will be added during the save, as it requires hash calculation
		if ( $this->groupId !== null ) {
			$this->showGroups[] = $this->groupId;
		}

		// Make sure there are no group name duplicates
		$this->showGroups = array_unique( $this->showGroups );
	}

	/**
	 * @param string $name
	 * @param string|false|null $default
	 * @return int|false|null
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
	 * @param string|false|null $default
	 * @return float|false|null
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
	 * @param string|false|null $default Default value or false to trigger error if absent
	 * @param string|false $regexp Regular expression to validate against or false to not validate
	 * @return string|false|null
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
		$marker = SimpleStyleParser::updateMarkerSymbolCounters( $this->geometries, $counters );
		if ( $marker ) {
			list( $this->counter, $this->markerProperties ) = $marker;
		}
		$this->state->setCounters( $counters );

		if ( $this->groupId === null ) {
			$groupId = '_' . sha1( FormatJson::encode( $this->geometries, false, FormatJson::ALL_OK ) );
			$this->groupId = $groupId;
			$this->showGroups[] = $groupId;
			// no need to array_unique() because it's impossible to manually add a private group
		} else {
			$groupId = $this->groupId;
		}

		$this->state->addData( $groupId, $this->geometries );
	}

	/**
	 * Handles the last step of parse process
	 * @param State $state
	 * @param ParserOutput $parserOutput to exclusively write to; nothing is read from this object
	 * @param bool $isPreview
	 * @param Parser $parser required to properly add tracking categories
	 */
	public static function finalParseStep(
		State $state,
		ParserOutput $parserOutput,
		$isPreview,
		Parser $parser
	) {
		if ( $state->getMaplinks() ) {
			$parserOutput->setPageProperty( 'kartographer_links', (string)$state->getMaplinks() );
		}
		if ( $state->getMapframes() ) {
			$parserOutput->setPageProperty( 'kartographer_frames', (string)$state->getMapframes() );
		}

		if ( $state->hasBrokenTags() ) {
			self::addTrackingCategory( $parser, 'kartographer-broken-category' );
		}
		if ( $state->hasValidTags() ) {
			self::addTrackingCategory( $parser, 'kartographer-tracking-category' );
		}

		// https://phabricator.wikimedia.org/T145615 - include all data in previews
		$data = $state->getData();
		if ( $data && $isPreview ) {
			$parserOutput->setJsConfigVar( 'wgKartographerLiveData', $data );
			if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'KartographerStaticMapframe' ) ) {
				// Preview generates HTML that is different from normal
				$parserOutput->updateCacheExpiry( 0 );
			}
		} else {
			$interact = $state->getInteractiveGroups();
			$requested = array_keys( $state->getRequestedGroups() );
			if ( $interact || $requested ) {
				$interact = array_flip( $interact );
				$liveData = array_intersect_key( $data, $interact );
				$requested = array_unique( $requested );
				// Prevent pointless API requests for missing groups
				foreach ( $requested as $groupId ) {
					if ( !isset( $data[$groupId] ) ) {
						$liveData[$groupId] = [];
					}
				}
				$parserOutput->setJsConfigVar( 'wgKartographerLiveData', (object)$liveData );
			}
		}
	}

	/**
	 * Adds tracking category with extra checks
	 *
	 * @param Parser $parser
	 * @param string $categoryMsg
	 */
	private static function addTrackingCategory( Parser $parser, $categoryMsg ) {
		static $hasParserFunctions;

		// Our tracking categories rely on ParserFunctions to differentiate per namespace,
		// avoid log noise if it's not installed
		if ( $hasParserFunctions === null ) {
			$hasParserFunctions = ExtensionRegistry::getInstance()->isLoaded( 'ParserFunctions' );
		}

		if ( $hasParserFunctions ) {
			$parser->addTrackingCategory( $categoryMsg );
		}
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function reportError(): string {
		$this->state->setBrokenTags();
		$errors = array_merge( $this->status->getErrorsByType( 'error' ),
			$this->status->getErrorsByType( 'warning' )
		);
		if ( !$errors ) {
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
			wfMessage( $message, static::TAG, $errorText )->inLanguage( $this->getLanguage() )->parse() );
	}

	/**
	 * @return Language|StubUserLang
	 */
	protected function getLanguage() {
		// Log if the user language is different from the page language (T311592)
		$pageLang = $this->parser->getTitle()->getPageLanguage();
		$targetLanguage = $this->parser->getTargetLanguage();
		if ( $targetLanguage->getCode() !== $pageLang->getCode() ) {
			LoggerFactory::getInstance( 'Kartographer' )->notice( 'Target language (' .
				$targetLanguage->getCode() . ') is different than page language (' .
				$pageLang->getCode() . ') (T311592)' );
		}
		return $targetLanguage;
	}
}
