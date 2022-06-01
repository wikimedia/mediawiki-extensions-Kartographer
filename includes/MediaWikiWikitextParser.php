<?php

namespace Kartographer;

use Parser;
use PPFrame;

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
		$wikiText = $this->parser->recursiveTagParseFully( $wikiText, $this->frame ?: false );
		return trim( Parser::stripOuterParagraph( $wikiText ) );
	}

}
