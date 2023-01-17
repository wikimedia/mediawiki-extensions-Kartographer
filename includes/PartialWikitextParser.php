<?php

namespace Kartographer;

use Parser;
use PPFrame;

/**
 * @license MIT
 */
class PartialWikitextParser {

	/** @var Parser */
	private Parser $parser;
	/** @var PPFrame */
	private PPFrame $frame;

	/**
	 * @param Parser $parser
	 * @param PPFrame $frame
	 */
	public function __construct( Parser $parser, PPFrame $frame ) {
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/**
	 * @param string $wikiText
	 * @return string Half-parsed HTML according to {@see Parser::recursiveTagParse}
	 */
	public function halfParseWikitext( string $wikiText ): string {
		return $this->parser->recursiveTagParse( $wikiText, $this->frame );
	}

}
