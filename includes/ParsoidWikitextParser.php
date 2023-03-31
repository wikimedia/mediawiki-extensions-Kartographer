<?php

namespace Kartographer;

use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * @license MIT
 */
class ParsoidWikitextParser extends WikitextParser {
	/** @var ParsoidExtensionAPI */
	private ParsoidExtensionAPI $extApi;

	/**
	 * @param ParsoidExtensionAPI $extApi
	 */
	public function __construct( ParsoidExtensionAPI $extApi ) {
		$this->extApi = $extApi;
	}

	/** @inheritDoc */
	public function parseWikitext( string $wikiText ): string {
		if ( !$this->needsParsing( $wikiText ) ) {
			return $wikiText;
		}
		$dom = $this->extApi->wikitextToDOM( $wikiText, [
			'parseOpts' => [
				'extTag' => $this->extApi->extTag,
				'context' => 'inline',
			], ], false );
		return $this->extApi->domToHtml( $dom, false, true );
	}
}
