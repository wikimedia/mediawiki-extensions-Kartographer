<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer;

use Kartographer\Tag\LegacyMapFrame;
use Kartographer\Tag\LegacyMapLink;
use Kartographer\Tag\LegacyTagHandler;
use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\ParserTestGlobalsHook;
use Parser;
use StripState;

/**
 * @license MIT
 */
class Hooks implements
	ParserFirstCallInitHook,
	ParserAfterParseHook,
	ParserTestGlobalsHook
{
	private LegacyMapLink $legacyMapLink;
	private LegacyMapFrame $legacyMapFrame;

	public function __construct() {
		$this->legacyMapLink = new LegacyMapLink();
		$this->legacyMapFrame = new LegacyMapFrame();
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

	/** @inheritDoc */
	public function onParserTestGlobals( &$globals ) {
		$globals['wgKartographerMapServer'] = 'https://maps.wikimedia.org';
	}
}
