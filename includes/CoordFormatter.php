<?php

namespace Kartographer;

use Language;
use Wikimedia\Bcp47Code\Bcp47CodeValue;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * Formats coordinates into human-readable strings
 *
 * @license MIT
 */
class CoordFormatter {

	/** @var int */
	private int $degreesLat;
	/** @var int */
	private int $minutesLat;
	/** @var int */
	private int $secondsLat;
	/** @var int */
	private int $degreesLon;
	/** @var int */
	private int $minutesLon;
	/** @var int */
	private int $secondsLon;
	/** @var string */
	private string $msgKey;

	/**
	 * @param float $lat
	 * @param float $lon
	 */
	public function __construct( $lat, $lon ) {
		[ $signLat, $this->degreesLat, $this->minutesLat, $this->secondsLat ] = $this->convertCoord( $lat );
		[ $signLon, $this->degreesLon, $this->minutesLon, $this->secondsLon ] = $this->convertCoord( $lon );
		$plusMinusLat = $signLat < 0 ? 'neg' : 'pos';
		$plusMinusLon = $signLon < 0 ? 'neg' : 'pos';
		$this->msgKey = "kartographer-coord-lat-$plusMinusLat-lon-$plusMinusLon";
	}

	/**
	 * Convert coordinates to degrees, minutes, seconds
	 *
	 * @param ?float $coord
	 * @return int[]
	 */
	private function convertCoord( ?float $coord ): array {
		$val = $sign = round( $coord * 3600 );
		$val = abs( $val );
		$degrees = floor( $val / 3600 );
		$minutes = floor( ( $val - $degrees * 3600 ) / 60 );
		$seconds = $val - $degrees * 3600 - $minutes * 60;

		return [ (int)$sign, (int)$degrees, (int)$minutes, (int)$seconds ];
	}

	/**
	 * Formats coordinates
	 * @param Language|string $language
	 *
	 * @return string
	 */
	public function format( $language ): string {
		return wfMessage( $this->msgKey )
			->numParams( $this->degreesLat, $this->minutesLat, $this->secondsLat, $this->degreesLon,
				$this->minutesLon, $this->secondsLon )
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
		if ( $language === null ) {
			return $extAPI->createInterfaceI18nFragment( $this->msgKey,
				[
					$this->degreesLat, $this->minutesLat, $this->secondsLat,
					$this->degreesLon, $this->minutesLon, $this->secondsLon
				] );
		} else {
			return $extAPI->createLangI18nFragment( new Bcp47CodeValue( $language ), $this->msgKey,
				[
					$this->degreesLat, $this->minutesLat, $this->secondsLat,
					$this->degreesLon, $this->minutesLon, $this->secondsLon
				] );
		}
	}
}
