<?php

namespace Kartographer;

use MediaWiki\Language\Language;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * Formats coordinates into human-readable strings
 *
 * @license MIT
 */
class CoordFormatter {

	/** @var int[] */
	private array $lat;
	/** @var int[] */
	private array $lon;
	private string $msgKey;

	public function __construct( ?float $lat, ?float $lon ) {
		[ $plusMinusLat, $this->lat ] = $this->convertCoord( $lat );
		[ $plusMinusLon, $this->lon ] = $this->convertCoord( $lon );
		// Messages used here:
		// * kartographer-coord-lat-pos-lon-pos
		// * kartographer-coord-lat-pos-lon-neg
		// * kartographer-coord-lat-neg-lon-pos
		// * kartographer-coord-lat-neg-lon-neg
		$this->msgKey = "kartographer-coord-lat-$plusMinusLat-lon-$plusMinusLon";
	}

	/**
	 * Convert coordinates to degrees, minutes, seconds
	 *
	 * @param float|null $coord
	 * @return array{string,int[]}
	 */
	private function convertCoord( ?float $coord ): array {
		$val = round( (float)$coord * 3600 );
		$sign = $val < 0 ? 'neg' : 'pos';
		$val = abs( $val );
		$degrees = floor( $val / 3600 );
		$minutes = floor( ( $val - $degrees * 3600 ) / 60 );
		$seconds = $val - $degrees * 3600 - $minutes * 60;

		return [ $sign, [ (int)$degrees, (int)$minutes, (int)$seconds ] ];
	}

	/**
	 * Formats coordinates
	 * @param Language|string $language
	 *
	 * @return string
	 */
	public function format( $language ): string {
		return wfMessage( $this->msgKey )
			->numParams( ...$this->lat, ...$this->lon )
			->inLanguage( $language )
			->plain();
	}

	/**
	 * Formats coordinates as a Parsoid i18n span. This method should not be used to generate
	 * content that is added to a tag attribute.
	 * @param ParsoidExtensionAPI $extAPI
	 * @param string|null $language
	 * @return DocumentFragment
	 */
	public function formatParsoidSpan( ParsoidExtensionAPI $extAPI, ?string $language ): DocumentFragment {
		$params = [ ...$this->lat, ...$this->lon ];
		return ParsoidUtils::createLangFragment( $this->msgKey, $params, $extAPI, $language );
	}

}
