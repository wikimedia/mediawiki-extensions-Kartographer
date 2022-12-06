<?php

namespace Kartographer;

use Parser;
use PPFrame;

/**
 * @license MIT
 */
class MediaWikiWikitextParser implements WikitextParser {

	/** @var Parser */
	private $parser;

	/** @var PPFrame|null */
	private $frame;

	/**
	 * @param Parser $parser
	 * @param PPFrame|null $frame
	 */
	public function __construct( Parser $parser, PPFrame $frame = null ) {
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/** @inheritDoc */
	public function parseWikitext( string $wikiText ): string {
		// Skip expensive parser calls when there is no wikitext syntax to parse. This is not
		// uncommon in this context. wfEscapeWikiText() is ~400 times faster than the parser, which
		// means 1 non-wikitext string in 400 is already worth the extra check. Still escaping
		// becomes expensive the longer the string is. Assume long strings are wikitext.
		if ( strlen( $wikiText ) < 65536 && wfEscapeWikiText( $wikiText ) === $wikiText ) {
			return $wikiText;
		}

		$wikiText = $this->parser->recursiveTagParseFully( $wikiText, $this->frame ?: false );
		return trim( Parser::stripOuterParagraph( $wikiText ) );
	}

}
