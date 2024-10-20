<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer\Tag;

use Kartographer\ParserFunctionTracker;
use Kartographer\PartialWikitextParser;
use Kartographer\SimpleStyleParser;
use Kartographer\State;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Json\FormatJson;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Title\TitleFormatter;
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
	protected ParserContext $parserContext;
	private ContentMetadataCollector $metadataCollector;
	private LanguageNameUtils $languageCodeValidator;
	private TitleFormatter $titleFormatter;

	public function __construct(
		Config $config,
		LanguageNameUtils $languageCodeValidator,
		TitleFormatter $titleFormatter
	) {
		$this->config = $config;
		$this->languageCodeValidator = $languageCodeValidator;
		$this->titleFormatter = $titleFormatter;
	}

	/**
	 * Entry point for all tags
	 *
	 * @param string|null $input
	 * @param array<string,string> $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML
	 */
	public function handle( ?string $input, array $args, Parser $parser, PPFrame $frame ): string {
		$mapServer = $this->config->get( 'KartographerMapServer' );
		if ( !$mapServer ) {
			throw new ConfigException( '$wgKartographerMapServer doesn\'t have a default, please set your own' );
		}

		$this->parserContext = new ParserContext( $parser, $this->titleFormatter );
		$this->metadataCollector = $parser->getOutput();
		$options = $parser->getOptions();
		$isPreview = $options->getIsPreview() || $options->getIsSectionPreview();

		$this->metadataCollector->addModuleStyles( [ 'ext.kartographer.style' ] );
		$parser->getOutput()->addExtraCSPDefaultSrc( $mapServer );

		$this->args = new MapTagArgumentValidator(
			static::TAG,
			$args,
			$this->config,
			$this->parserContext->getTargetLanguage(),
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

		$state = State::getOrCreate( $parser->getOutput() );
		$state->incrementUsage( static::TAG );

		if ( $status->isGood() ) {
			$this->updateState( $state, $geometries );
			$html = $this->render( new PartialWikitextParser( $parser, $frame ), !$isPreview );
		} else {
			$state->incrementBrokenTags();
			$errorReporter = new ErrorReporter( $this->parserContext->getTargetLanguage() );
			$html = $errorReporter->getHtml( $status, static::TAG );
		}

		State::saveState( $this->metadataCollector, $state );
		return $html;
	}

	/**
	 * When overridden in a descendant class, returns tag HTML
	 *
	 * @param PartialWikitextParser $parser
	 * @param bool $serverMayRenderOverlays If the map server should attempt to render GeoJSON
	 *  overlays via their group id
	 * @return string HTML
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

	protected function getOutput(): ContentMetadataCollector {
		return $this->metadataCollector;
	}

}
