<?php

namespace Kartographer;

use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * @license MIT
 */
class ParsoidWikitextParser extends WikitextParser {

	private ParsoidExtensionAPI $extApi;

	public function __construct( ParsoidExtensionAPI $extApi ) {
		$this->extApi = $extApi;
	}

	/** @inheritDoc */
	protected function parse( string $wikiText ): string {
		$dom = $this->extApi->wikitextToDOM( $wikiText, [
			'parseOpts' => [
				'extTag' => $this->extApi->extTag->getName(),
				'context' => 'inline',
			],
			// the wikitext is embedded into a JSON attribute, processing in a new frame seems to be the right move
			// to avoid DSR failures
			'processInNewFrame' => true,
			'clearDSROffsets' => true,
		], false );
		return $this->extApi->domToHtml( $dom, false, true );
	}
}
