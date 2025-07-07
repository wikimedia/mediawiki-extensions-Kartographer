<?php

namespace Kartographer;

use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

/**
 * @license MIT
 */
class MediaWikiWikitextParser extends WikitextParser {

	public function __construct(
		private readonly Parser $parser,
		private readonly ?PPFrame $frame = null,
	) {
	}

	/** @inheritDoc */
	protected function parse( string $wikiText ): string {
		$wikiText = $this->parser->recursiveTagParseFully( $wikiText, $this->frame ?: false );
		return trim( Parser::stripOuterParagraph( $wikiText ) );
	}

}
