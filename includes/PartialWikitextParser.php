<?php

namespace Kartographer;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

/**
 * @license MIT
 * @codeCoverageIgnore
 */
class PartialWikitextParser {

	private Parser $parser;
	private PPFrame $frame;

	public function __construct( Parser $parser, PPFrame $frame ) {
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/**
	 * @param string $wikiText
	 * @return string Half-parsed HTML according to {@see Parser::recursiveTagParse}
	 */
	public function halfParseWikitext( string $wikiText ): string {
		// Don't parse trivial alphanumeric-only strings, e.g. counters like "A" or "99".
		if ( $wikiText === '' || ctype_alnum( $wikiText ) ) {
			return $wikiText;
		}

		return $this->parser->recursiveTagParse( $wikiText, $this->frame );
	}

}
