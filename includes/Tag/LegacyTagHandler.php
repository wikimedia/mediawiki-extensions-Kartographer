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
use FormatJson;
use Html;
use Kartographer\PartialWikitextParser;
use Kartographer\SimpleStyleParser;
use Kartographer\State;
use Language;
use LogicException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
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

	/**
	 * Lower case name of the XML-style parser tag, e.g. "mapframe". Currently expected to start
	 * with "map…" by the {@see State} class.
	 */
	public const TAG = '';

	/** @var Status */
	private Status $status;
	/** @var stdClass[] */
	private array $geometries = [];
	/** @var MapTagArgumentValidator */
	protected MapTagArgumentValidator $args;
	/** @var string|null */
	protected ?string $counter = null;
	/** @var Config */
	protected Config $config;
	/** @var Parser */
	protected Parser $parser;
	/** @var Language */
	private Language $targetLanguage;
	/** @var State */
	protected State $state;
	/** @var stdClass|null */
	protected ?stdClass $markerProperties = null;

	/**
	 * Entry point for all tags
	 *
	 * @param string|null $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function entryPoint( ?string $input, array $args, Parser $parser, PPFrame $frame ): string {
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
	private function handle( ?string $input, array $args, Parser $parser, PPFrame $frame ): string {
		$this->config = MediaWikiServices::getInstance()->getMainConfig();
		$mapServer = $this->config->get( 'KartographerMapServer' );
		if ( !$mapServer ) {
			throw new \ConfigException( '$wgKartographerMapServer doesn\'t have a default, please set your own' );
		}

		$this->parser = $parser;
		// Can only be StubUserLang on special pages, but these can't contain <map…> tags
		$this->targetLanguage = $parser->getTargetLanguage();
		$options = $parser->getOptions();
		$isPreview = $options->getIsPreview() || $options->getIsSectionPreview();
		$parserOutput = $parser->getOutput();

		$parserOutput->addModuleStyles( [ 'ext.kartographer.style' ] );
		$parserOutput->addExtraCSPDefaultSrc( $mapServer );
		$this->state = State::getOrCreate( $parserOutput );
		$this->state->incrementUsage( static::TAG );

		$this->args = new MapTagArgumentValidator( static::TAG, $args, $this->config, $this->getLanguage() );
		$this->status = $this->args->status;
		if ( $this->status->isOK() ) {
			$this->parseGeometries( $input, $parser, $frame );
		}

		if ( !$this->status->isGood() ) {
			$result = $this->reportError();
			State::setState( $parserOutput, $this->state );
			return $result;
		}

		$this->saveData();

		$this->state->setValidTags();

		$result = $this->render( new PartialWikitextParser( $parser, $frame ), $isPreview );

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
	private function parseGeometries( ?string $input, Parser $parser, PPFrame $frame ): void {
		$simpleStyle = SimpleStyleParser::newFromParser( $parser, $frame );

		$this->status = $simpleStyle->parse( $input );
		if ( $this->status->isOK() ) {
			$this->geometries = $this->status->getValue()['data'];
		}
	}

	/**
	 * When overridden in a descendant class, returns tag HTML
	 * @param PartialWikitextParser $parser
	 * @param bool $isPreview
	 * @return string
	 */
	abstract protected function render( PartialWikitextParser $parser, bool $isPreview ): string;

	private function saveData(): void {
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
		bool $isPreview,
		ParserFunctionTracker $tracker
	): void {
		foreach ( $state->getUsages() as $key => $count ) {
			if ( $count ) {
				// Resulting page property names are "kartographer_links" and "kartographer_frames"
				$name = 'kartographer_' . preg_replace( '/^map/', '', $key );
				$parserOutput->setPageProperty( $name, (string)$count );
			}
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
	 * @return Language
	 */
	private function getLanguage(): Language {
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
