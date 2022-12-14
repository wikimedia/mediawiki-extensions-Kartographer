<?php

namespace Kartographer;

/**
 * @license MIT
 */
interface WikitextParser {

	/**
	 * @param string $wikiText
	 * @return string Fully parsed HTML
	 */
	public function parseWikitext( string $wikiText ): string;

}
