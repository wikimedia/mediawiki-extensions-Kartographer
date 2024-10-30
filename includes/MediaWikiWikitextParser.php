<?php

namespace Kartographer;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

/**
 * @license MIT
 */
class MediaWikiWikitextParser extends WikitextParser {

	private Parser $parser;
	private ?PPFrame $frame;

	public function __construct( Parser $parser, ?PPFrame $frame = null ) {
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/** @inheritDoc */
	protected function parse( string $wikiText ): string {
		$wikiText = $this->parser->recursiveTagParseFully( $wikiText, $this->frame ?: false );
		return trim( Parser::stripOuterParagraph( $wikiText ) );
	}

}
