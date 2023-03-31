<?php

namespace Kartographer;

use Parser;
use PPFrame;

/**
 * @license MIT
 */
class MediaWikiWikitextParser extends WikitextParser {

	/** @var Parser */
	private Parser $parser;

	/** @var PPFrame|null */
	private ?PPFrame $frame;

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
		if ( !$this->needsParsing( $wikiText ) ) {
			return $wikiText;
		}

		$wikiText = $this->parser->recursiveTagParseFully( $wikiText, $this->frame ?: false );
		return trim( Parser::stripOuterParagraph( $wikiText ) );
	}

}
