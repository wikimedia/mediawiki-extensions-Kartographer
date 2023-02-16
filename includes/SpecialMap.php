<?php

namespace Kartographer;

use GeoData\Globe;
use Html;
use Kartographer\Projection\EPSG3857;
use SpecialPage;
use Title;
use UnlistedSpecialPage;

/**
 * Special page that works as a fallback destination for non-JS users
 * who click on map links. It displays a world map with a dot for the given location.
 * URL format: Special:Map/<zoom>/<lat>/<lon>
 * Zoom isn't used anywhere yet.
 *
 * @license MIT
 */
class SpecialMap extends UnlistedSpecialPage {

	/**
	 * @param string $name
	 */
	public function __construct( $name = 'Map' ) {
		parent::__construct( $name );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addModuleStyles( 'ext.kartographer.specialMap' );
		$mapServer = $this->getConfig()->get( 'KartographerMapServer' );
		if ( $mapServer !== null ) {
			$output->getCSP()->addDefaultSrc( $mapServer );
		}

		$coord = $this->parseSubpage( $par );
		if ( !$coord ) {
			$coordText = $this->msg( 'kartographer-specialmap-invalid-coordinates' )->text();
			$markerHtml = '';
		} else {
			[ 'lat' => $lat, 'lon' => $lon ] = $coord;
			$coordText = CoordFormatter::format( $lat, $lon, $this->getLanguage() );
			[ $x, $y ] = EPSG3857::latLonToPoint( [ $lat, $lon ] );
			$markerHtml = Html::element( 'div',
				[
					'id' => 'mw-specialMap-marker',
					'style' => "left:{$x}px; top:{$y}px;"
				]
			);
		}

		$attributions = Html::rawElement( 'div', [ 'id' => 'mw-specialMap-attributions' ],
			$this->msg( 'kartographer-attribution' )->parse() );

		$this->getOutput()->addHTML(
			Html::rawElement( 'div', [ 'id' => 'mw-specialMap-container', 'class' => 'thumb' ],
				Html::rawElement( 'div', [ 'class' => 'thumbinner' ],
					Html::rawElement( 'div', [ 'id' => 'mw-specialMap-inner' ],
						Html::element( 'img', [
							'alt' => $this->msg( 'kartographer-specialmap-world' ),
							'height' => 256,
							'width' => 256,
							'src' => $this->getWorldMapUrl(),
							'srcset' => $this->getWorldMapSrcset()
						] ) .
						$markerHtml .
						$attributions
					) .
					Html::rawElement( 'div',
						[ 'id' => 'mw-specialMap-caption', 'class' => 'thumbcaption' ],
						Html::element( 'span', [ 'id' => 'mw-specialMap-icon' ] ) .
						Html::element( 'span', [ 'id' => 'mw-specialMap-coords' ], $coordText )
					)
				)
			)
		);
	}

	/**
	 * Parses subpage parameter to this special page into zoom / lat /lon
	 *
	 * @param string|null $par
	 * @return array|false
	 */
	private function parseSubpage( ?string $par ) {
		if ( !preg_match(
				'#^(?<zoom>\d+)/(?<lat>-?\d+(\.\d+)?)/(?<lon>-?\d+(\.\d+)?)(/(?<lang>[a-zA-Z0-9-]+))?$#',
				$par,
				$matches
			)
		) {
			return false;
		}

		if ( class_exists( Globe::class ) ) {
			$globe = new Globe( 'earth' );

			if ( !$globe->coordinatesAreValid( $matches['lat'], $matches['lon'] ) ) {
				return false;
			}
		}

		return [
			'zoom' => (int)$matches['zoom'],
			'lat' => (float)$matches['lat'],
			'lon' => (float)$matches['lon'],
			'lang' => $matches['lang'] ?? 'local',
		];
	}

	/**
	 * Return the image url for a world map
	 * @param string $factor HiDPI image factor (example: @2x)
	 * @return string
	 */
	private function getWorldMapUrl( string $factor = '' ): string {
		return $this->getConfig()->get( 'KartographerMapServer' ) . '/' .
			$this->getConfig()->get( 'KartographerDfltStyle' ) .
			'/0/0/0' . $factor . '.png';
	}

	/**
	 * Return srcset attribute value for world map image url
	 * @return string|null
	 */
	private function getWorldMapSrcset(): ?string {
		$srcSetScalesConfig = $this->getConfig()->get( 'KartographerSrcsetScales' );
		if ( $this->getConfig()->get( 'ResponsiveImages' ) && $srcSetScalesConfig ) {
			// For now only support 2x, not 1.5. Saves some bytes...
			$srcSetScales = array_intersect( $srcSetScalesConfig, [ 2 ] );
			$srcSets = [];
			foreach ( $srcSetScales as $srcSetScale ) {
				$scaledImgUrl = $this->getWorldMapUrl( "@{$srcSetScale}x" );
				$srcSets[] = "{$scaledImgUrl} {$srcSetScale}x";
			}
			return implode( ', ', $srcSets );
		}
		return null;
	}

	/**
	 * Returns a Title for a link to the coordinates provided
	 *
	 * @param float|null $lat
	 * @param float|null $lon
	 * @param int|null $zoom
	 * @param string $lang Optional language code. Defaults to 'local'
	 * @return Title
	 */
	public static function link( $lat, $lon, $zoom, $lang = 'local' ): Title {
		$subpage = (int)$zoom . '/' . (float)$lat . '/' . (float)$lon;
		if ( $lang && $lang !== 'local' ) {
			$subpage .= '/' . $lang;
		}
		return SpecialPage::getTitleFor( 'Map', $subpage );
	}
}
