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
	private static $tags = [
		'mapframe' => 'Kartographer\Tag\MapFrame::entryPoint',
		'maplink' => 'Kartographer\Tag\MapLink::entryPoint',
	];

	/**
	 * ParserFirstCallInit hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		global $wgKartographerEnableTags;

		foreach ( $wgKartographerEnableTags as $tag ) {
			if ( isset( self::$tags[$tag] ) ) {
				$parser->setHook( $tag, self::$tags[$tag] );
			}
		}

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

	/**
	 * RejectParserCacheValue hook handler. Rejects output with old versions of map data
	 * structures. To be enabled at a later date.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RejectParserCacheValue
	 * @param ParserOutput $po
	 * @return bool
	 */
	/*public static function onRejectParserCacheValue( ParserOutput $po ) {
		// One of these should be prsent in any output with old version of data
		if ( $po->getExtensionData( 'kartographer_valid' )
			 || $po->getExtensionData( 'kartographer_broken' )
		) {
			return false;
		}
		return true;
	}*/
}
