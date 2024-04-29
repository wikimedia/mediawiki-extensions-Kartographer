<?php

namespace Kartographer;

/**
 * @license MIT
 */
abstract class WikitextParser {

	/**
	 * @param string $wikiText
	 * @return string Fully parsed HTML
	 */
	final public function parseWikitext( string $wikiText ): string {
		return $this->needsParsing( $wikiText ) ? $this->parse( $wikiText ) : $wikiText;
	}

	abstract protected function parse( string $wikiText ): string;

	private function needsParsing( string $wikiText ): bool {
		// Skip expensive parser calls when there is no wikitext syntax to parse. This is not
		// uncommon in this context. wfEscapeWikiText() is ~400 times faster than the core parser,
		// which means 1 non-wikitext string in 400 is already worth the extra check. Still escaping
		// becomes expensive the longer the string is. Assume long strings are wikitext.
		return ( strlen( $wikiText ) >= 65536 || wfEscapeWikiText( $wikiText ) !== $wikiText );
	}
}
