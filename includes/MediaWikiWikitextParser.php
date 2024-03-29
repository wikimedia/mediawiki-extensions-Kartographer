<?php

namespace Kartographer;

use Parser;
use PPFrame;

/**
 * @license MIT
 */
class MediaWikiWikitextParser extends WikitextParser {

	private Parser $parser;
	private ?PPFrame $frame;

	public function __construct( Parser $parser, PPFrame $frame = null ) {
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/** @inheritDoc */
	public function parseWikitext( string $wikiText ): string {
		if ( !$this->needsParsing( $wikiText ) ) {
			return $wikiText;
		}

		$wikiText = $this->parser->recursiveTagParseFully( $wikiText, $this->frame ?: false );
		return trim( Parser::stripOuterParagraph( $wikiText ) );
	}

}
