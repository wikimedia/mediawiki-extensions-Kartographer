<?php
/**
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer;

use Kartographer\Tag\LegacyMapFrame;
use Kartographer\Tag\LegacyMapLink;
use Kartographer\Tag\LegacyTagHandler;
use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\ParserTestGlobalsHook;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\StripState;
use MediaWiki\Settings\SettingsBuilder;
use MediaWiki\Title\TitleFormatter;

/**
 * @license MIT
 */
class Hooks implements
	ParserFirstCallInitHook,
	ParserAfterParseHook,
	ParserTestGlobalsHook
{
	private readonly LegacyMapLink $legacyMapLink;
	private readonly LegacyMapFrame $legacyMapFrame;

	public function __construct(
		Config $config,
		LanguageNameUtils $languageCodeValidator,
		TitleFormatter $titleFormatter
	) {
		$this->legacyMapLink = new LegacyMapLink(
			$config,
			$languageCodeValidator,
			$titleFormatter
		);
		$this->legacyMapFrame = new LegacyMapFrame(
			$config,
			$languageCodeValidator,
			$titleFormatter
		);
	}

	/**
	 * ParserFirstCallInit hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( LegacyMapLink::TAG, [ $this->legacyMapLink, 'handle' ] );
		$parser->setHook( LegacyMapFrame::TAG, [ $this->legacyMapFrame, 'handle' ] );
	}

	/**
	 * ParserAfterParse hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 * @param Parser $parser
	 * @param string &$text Text being parsed
	 * @param StripState $stripState StripState used
	 */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		$output = $parser->getOutput();
		$state = State::getState( $output );

		if ( $state ) {
			$options = $parser->getOptions();
			$isPreview = $options->getIsPreview() || $options->getIsSectionPreview();
			$tracker = new ParserFunctionTracker( $parser );
			LegacyTagHandler::finalParseStep( $state, $output, $isPreview, $tracker );
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserTestGlobals
	 * @param array &$globals Array with all the globals which should be set for parser tests.
	 *   The arrays keys serve as the globals' names, its values are the globals' values.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onParserTestGlobals( &$globals ) {
		$globals['wgKartographerMapServer'] = 'https://maps.wikimedia.org';
	}

	/**
	 * Sets $wgKartographerMapServer in integration test/CI setup
	 * This is needed by parserTests that define articles containing Kartographer content - parsing them when
	 * inserting them in the test DB requires $wgKartographerMapServer to be defined early.
	 */
	public static function onRegistration( array $extInfo, SettingsBuilder $settings ) {
		if ( defined( 'MW_PHPUNIT_TEST' ) || defined( 'MW_QUIBBLE_CI' ) ) {
			$settings->overrideConfigValue( 'KartographerMapServer', 'https://maps.wikimedia.org' );
		}
	}
}
