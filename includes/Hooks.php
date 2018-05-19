<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer;

use Kartographer\Tag\TagHandler;
use Parser;
use ParserOutput;

class Hooks {
	/**
	 * ParserFirstCallInit hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		global $wgKartographerEnableMapFrame;

		$parser->setHook( 'maplink', 'Kartographer\Tag\MapLink::entryPoint' );
		if ( $wgKartographerEnableMapFrame ) {
			$parser->setHook( 'mapframe', 'Kartographer\Tag\MapFrame::entryPoint' );
		}
	}

	/**
	 * ParserAfterParse hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 * @param Parser $parser
	 */
	public static function onParserAfterParse( Parser $parser ) {
		$output = $parser->getOutput();
		$state = State::getState( $output );

		if ( $state ) {
			$options = $parser->getOptions();
			$isPreview = $options->getIsPreview() || $options->getIsSectionPreview();
			TagHandler::finalParseStep( $state, $output, $isPreview, $parser->getTitle() );
		}
	}

	/**
	 * RejectParserCacheValue hook handler. Rejects output with old versions of map data
	 * structures. To be enabled at a later date.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RejectParserCacheValue
	 * @param ParserOutput $po
	 * @return bool
	 */
	/*public static function onRejectParserCacheValue( ParserOutput $po ) {
		// One of these should be present in any output with old version of data
		if ( $po->getExtensionData( 'kartographer_valid' )
			 || $po->getExtensionData( 'kartographer_broken' )
		) {
			return false;
		}
		return true;
	}*/
}
