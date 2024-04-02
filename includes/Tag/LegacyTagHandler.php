<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer\Tag;

use FormatJson;
use Kartographer\ParserFunctionTracker;
use Kartographer\PartialWikitextParser;
use Kartographer\SimpleStyleParser;
use Kartographer\State;
use Language;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Title\Title;
use Parser;
use PPFrame;
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

	protected MapTagArgumentValidator $args;
	protected Config $config;
	protected Parser $parser;
	private Language $targetLanguage;
	private LanguageNameUtils $languageCodeValidator;

	public function __construct(
		Config $config,
		LanguageNameUtils $languageCodeValidator
	) {
		$this->config = $config;
		$this->languageCodeValidator = $languageCodeValidator;
	}

	/**
	 * Entry point for all tags
	 *
	 * @param string|null $input
	 * @param array<string,string> $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function handle( ?string $input, array $args, Parser $parser, PPFrame $frame ): string {
		$mapServer = $this->config->get( 'KartographerMapServer' );
		if ( !$mapServer ) {
			throw new ConfigException( '$wgKartographerMapServer doesn\'t have a default, please set your own' );
		}

		$this->parser = $parser;
		// Can only be StubUserLang on special pages, but these can't contain <map…> tags
		$this->targetLanguage = $parser->getTargetLanguage();
		$options = $parser->getOptions();
		$isPreview = $options->getIsPreview() || $options->getIsSectionPreview();
		$parserOutput = $parser->getOutput();

		$parserOutput->addModuleStyles( [ 'ext.kartographer.style' ] );
		$parserOutput->addExtraCSPDefaultSrc( $mapServer );
		$state = State::getOrCreate( $parserOutput );
		$state->incrementUsage( static::TAG );

		$this->args = new MapTagArgumentValidator(
			static::TAG,
			$args,
			$this->config,
			$this->getTargetLanguage(),
			$this->languageCodeValidator
		);
		$status = $this->args->status;
		$geometries = [];
		if ( $status->isOK() ) {
			$status = SimpleStyleParser::newFromParser( $parser, $frame )->parse( $input );
			if ( $status->isOK() ) {
				$geometries = $status->getValue()['data'];
			}
		}

		if ( !$status->isGood() ) {
			$state->incrementBrokenTags();
			State::saveState( $parserOutput, $state );

			$errorReporter = new ErrorReporter( $this->getTargetLanguageCode() );
			return $errorReporter->getHtml( $status, static::TAG );
		}

		$this->updateState( $state, $geometries );

		$result = $this->render( new PartialWikitextParser( $parser, $frame ), !$isPreview );

		State::saveState( $parserOutput, $state );
		return $result;
	}

	/**
	 * When overridden in a descendant class, returns tag HTML
	 *
	 * @param PartialWikitextParser $parser
	 * @param bool $serverMayRenderOverlays If the map server should attempt to render GeoJSON
	 *  overlays via their group id
	 * @return string
	 */
	abstract protected function render( PartialWikitextParser $parser, bool $serverMayRenderOverlays ): string;

	protected function updateState( State $state, array $geometries ): void {
		$state->addRequestedGroups( $this->args->showGroups );

		if ( !$geometries ) {
			return;
		}

		// Merge existing data with the new tag's data under the same group name

		// For all GeoJSON items whose marker-symbol value begins with '-counter' and '-letter',
		// recursively replace them with an automatically incremented marker icon.
		$counters = $state->getCounters();
		$marker = SimpleStyleParser::updateMarkerSymbolCounters( $geometries, $counters );
		if ( $marker ) {
			$this->args->setFirstMarkerProperties( ...$marker );
		}
		$state->setCounters( $counters );

		if ( $this->args->groupId === null ) {
			// This hash calculation MUST be the same as in ParsoidDomProcessor::wtPostprocess
			$groupId = '_' . sha1( FormatJson::encode( $geometries, false, FormatJson::ALL_OK ) );
			$this->args->groupId = $groupId;
			$this->args->showGroups[] = $groupId;
			// no need to array_unique() because it's impossible to manually add a private group
		} else {
			$groupId = (string)$this->args->groupId;
		}

		$state->addData( $groupId, $geometries );
	}

	/**
	 * Handles the last step of parse process
	 *
	 * @param State $state
	 * @param ContentMetadataCollector $parserOutput
	 * @param bool $outputAllLiveData
	 * @param ParserFunctionTracker $tracker
	 */
	public static function finalParseStep(
		State $state,
		ContentMetadataCollector $parserOutput,
		bool $outputAllLiveData,
		ParserFunctionTracker $tracker
	): void {
		foreach ( $state->getUsages() as $key => $count ) {
			// Resulting page property names are "kartographer_links" and "kartographer_frames"
			$name = 'kartographer_' . preg_replace( '/^map/', '', $key );
			$parserOutput->setNumericPageProperty( $name, $count );
		}

		$tracker->addTrackingCategories( [
			'kartographer-broken-category' => $state->hasBrokenTags(),
			'kartographer-tracking-category' => $state->hasValidTags(),
		] );

		// https://phabricator.wikimedia.org/T145615 - include all data in previews
		$data = $state->getData();
		if ( $data && $outputAllLiveData ) {
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

	private function getTargetLanguage(): Language {
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

	protected function getTargetLanguageCode(): string {
		return $this->getTargetLanguage()->getCode();
	}

	protected function getOutput(): ContentMetadataCollector {
		return $this->parser->getOutput();
	}

}
