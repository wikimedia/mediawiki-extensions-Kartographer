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
use ExtensionRegistry;
use FormatJson;
use Html;
use Kartographer\ExternalDataLoader;
use Kartographer\SimpleStyleParser;
use Kartographer\State;
use Language;
use LogicException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\StubObject\StubUserLang;
use Message;
use Parser;
use ParserOutput;
use PPFrame;
use Status;
use stdClass;
use Title;
use Wikimedia\Parsoid\Core\ContentMetadataCollector;

/**
 * Base class for all <map...> tags
 *
 * @license MIT
 */
abstract class LegacyTagHandler {

	public const TAG = '';

	/** @var Status */
	private $status;

	/** @var stdClass[] */
	private $geometries = [];

	/** @var MapTagArgumentValidator */
	protected $args;

	/** @var int|null */
	protected $counter = null;

	/** @var Config */
	protected $config;

	/** @var Parser */
	protected $parser;

	/** @var PPFrame */
	protected $frame;

	/** @var Language|StubUserLang */
	private $targetLanguage;

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
		$mapServer = $this->config->get( 'KartographerMapServer' );
		if ( !$mapServer ) {
			throw new \ConfigException( '$wgKartographerMapServer doesn\'t have a default, please set your own' );
		}

		$this->parser = $parser;
		$this->frame = $frame;
		$this->targetLanguage = $parser->getTargetLanguage();
		$options = $parser->getOptions();
		$isPreview = $options->getIsPreview() || $options->getIsSectionPreview();
		$parserOutput = $parser->getOutput();

		$parserOutput->addModuleStyles( [ 'ext.kartographer.style' ] );
		$parserOutput->addExtraCSPDefaultSrc( $mapServer );
		$this->state = State::getOrCreate( $parserOutput );
		// FIXME: Improve the State class so we don't need to hard-code this here
		switch ( static::TAG ) {
			case LegacyMapLink::TAG:
				$this->state->useMaplink();
				break;
			case LegacyMapFrame::TAG:
				$this->state->useMapframe();
				break;
		}

		$this->args = new MapTagArgumentValidator( static::TAG, $args, $this->config, $this->getLanguage() );
		$this->status = $this->args->status;
		if ( $this->status->isOK() ) {
			$this->parseGeometries( $input, $parser, $frame, $isPreview );
		}

		if ( !$this->status->isGood() ) {
			$result = $this->reportError();
			State::setState( $parserOutput, $this->state );
			return $result;
		}

		$this->saveData();

		$this->state->setValidTags();

		$result = $this->render( $isPreview );

		State::setState( $parserOutput, $this->state );
		return $result;
	}

	/**
	 * Parses and sanitizes GeoJSON+simplestyle contained inside of tags
	 *
	 * @param string|null $input
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param bool $isPreview
	 */
	private function parseGeometries( $input, Parser $parser, PPFrame $frame, bool $isPreview ): void {
		$simpleStyle = SimpleStyleParser::newFromParser( $parser, $frame );

		$this->status = $simpleStyle->parse( $input );
		if ( $this->status->isOK() ) {
			$this->geometries = $this->status->getValue()['data'];

			if ( !$isPreview && $this->config->get( 'KartographerExternalDataParseTimeFetch' ) ) {
				$fetcher = new ExternalDataLoader(
					MediaWikiServices::getInstance()->getHttpRequestFactory(),
					new ParserFunctionTracker( $parser ),
					ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' )
				);
				$fetcher->parse( $this->geometries );
			}
		}
	}

	/**
	 * When overridden in a descendant class, returns tag HTML
	 * @param bool $isPreview
	 * @return string
	 */
	abstract protected function render( bool $isPreview ): string;

	private function saveData() {
		$this->state->addRequestedGroups( $this->args->showGroups );

		if ( !$this->geometries ) {
			return;
		}

		// Merge existing data with the new tag's data under the same group name

		// For all GeoJSON items whose marker-symbol value begins with '-counter' and '-letter',
		// recursively replace them with an automatically incremented marker icon.
		$counters = $this->state->getCounters();
		$marker = SimpleStyleParser::updateMarkerSymbolCounters( $this->geometries, $counters );
		if ( $marker ) {
			[ $this->counter, $this->markerProperties ] = $marker;
		}
		$this->state->setCounters( $counters );

		if ( $this->args->groupId === null ) {
			$groupId = '_' . sha1( FormatJson::encode( $this->geometries, false, FormatJson::ALL_OK ) );
			$this->args->groupId = $groupId;
			$this->args->showGroups[] = $groupId;
			// no need to array_unique() because it's impossible to manually add a private group
		} else {
			$groupId = (string)$this->args->groupId;
		}

		$this->state->addData( $groupId, $this->geometries );
	}

	/**
	 * Handles the last step of parse process
	 * @param State $state
	 * @param ParserOutput $parserOutput to exclusively write to; nothing is read from this object
	 * @param bool $isPreview
	 * @param ParserFunctionTracker $tracker
	 */
	public static function finalParseStep(
		State $state,
		ParserOutput $parserOutput,
		$isPreview,
		ParserFunctionTracker $tracker
	) {
		if ( $state->getMaplinks() ) {
			$parserOutput->setPageProperty( 'kartographer_links', (string)$state->getMaplinks() );
		}
		if ( $state->getMapframes() ) {
			$parserOutput->setPageProperty( 'kartographer_frames', (string)$state->getMapframes() );
		}

		$tracker->addTrackingCategories( [
			'kartographer-broken-category' => $state->hasBrokenTags(),
			'kartographer-tracking-category' => $state->hasValidTags(),
		] );

		// https://phabricator.wikimedia.org/T145615 - include all data in previews
		$data = $state->getData();
		if ( $data && $isPreview ) {
			$parserOutput->setJsConfigVar( 'wgKartographerLiveData', $data );
		} else {
			$interact = $state->getInteractiveGroups();
			$requested = $state->getRequestedGroups();
			if ( $interact || $requested ) {
				$liveData = array_intersect_key( $data, array_flip( $interact ) );
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
	 * @return string
	 */
	private function reportError(): string {
		$this->state->setBrokenTags();
		$errors = array_merge( $this->status->getErrorsByType( 'error' ),
			$this->status->getErrorsByType( 'warning' )
		);
		if ( !$errors ) {
			throw new LogicException( __METHOD__ . '(): attempt to report error when none took place' );
		}

		if ( count( $errors ) > 1 ) {
			$html = '';
			foreach ( $errors as $err ) {
				$html .= Html::rawElement( 'li', [], wfMessage( $err['message'], $err['params'] )
					->inLanguage( $this->getLanguage() )->parse() ) . "\n";
			}
			$msg = wfMessage( 'kartographer-error-context-multi', static::TAG )
				->rawParams( Html::rawElement( 'ul', [], $html ) );
		} else {
			$errorText = wfMessage( $errors[0]['message'], $errors[0]['params'] )
				->inLanguage( $this->getLanguage() )->parse();
			$msg = wfMessage( 'kartographer-error-context', static::TAG, Message::rawParam( $errorText ) );
		}
		return Html::rawElement( 'div', [ 'class' => 'mw-kartographer-error' ],
			$msg->inLanguage( $this->getLanguage() )->escaped() .
			$this->getJSONValidatorLog( $this->status->getValue()['schema-errors'] ?? [] )
		);
	}

	/**
	 * @param array[] $errors
	 *
	 * @return string HTML
	 */
	private function getJSONValidatorLog( array $errors ): string {
		if ( !$errors ) {
			return '';
		}

		$log = "\n";
		/** These errors come from {@see \JsonSchema\Constraints\BaseConstraint::addError} */
		foreach ( $errors as $error ) {
			$log .= Html::element( 'li', [],
				$error['pointer'] . wfMessage( 'colon-separator' )->text() . $error['message']
			) . "\n";
		}
		return Html::rawElement( 'ul', [ 'class' => [
			'mw-kartographer-error-log',
			'mw-collapsible',
			'mw-collapsed',
		] ], $log );
	}

	/**
	 * @return Language|StubUserLang
	 */
	private function getLanguage() {
		// Log if the user language is different from the page language (T311592)
		$page = $this->parser->getPage();
		if ( $page ) {
			$pageLang = Title::castFromPageReference( $page )->getPageLanguage();
			if ( $this->targetLanguage->getCode() !== $pageLang->getCode() ) {
				LoggerFactory::getInstance( 'Kartographer' )->notice( 'Target language (' .
					$this->targetLanguage->getCode() . ') is different than page language (' .
					$pageLang->getCode() . ') (T311592)' );
			}
		}

		return $this->targetLanguage;
	}

	/**
	 * @return string
	 */
	protected function getLanguageCode(): string {
		return $this->getLanguage()->getCode();
	}

	/**
	 * @return ContentMetadataCollector
	 */
	protected function getOutput(): ContentMetadataCollector {
		return $this->parser->getOutput();
	}
}
