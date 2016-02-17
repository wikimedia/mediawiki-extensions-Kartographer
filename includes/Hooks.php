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

class Hooks {

	/**
	 * ParserFirstCallInit hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'mapframe', 'Kartographer\Tag\MapFrame::entryPoint' );
		$parser->setHook( 'maplink', 'Kartographer\Tag\MapLink::entryPoint' );
		$parser->setHook( 'mapdata', 'Kartographer\Tag\MapData::entryPoint' );
		return true;
	}

	/**
	 * Register our unit tests
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 * @param string[] $files
	 * @return bool
	 */
	public static function onUnitTestsList( array &$files ) {
		global $IP;

		$files[] = "$IP/extensions/Kartographer/tests/phpunit";
		return true;
	}

	/**
	 * ParserAfterParse hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserAfterParse
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserAfterParse( Parser $parser ) {
		TagHandler::finalParseStep( $parser );
		return true;
	}
}
