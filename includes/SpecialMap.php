<?php

namespace Kartographer;

use GeoData\Globe;
use Html;
use SpecialPage;
use Title;
use Kartographer\Projection\EPSG3857;

/**
 * Special page that works as a fallback destination for non-JS users
 * who click on map links. It displays a world map with a dot for the given location.
 * URL format: Special:Map/<zoom>/<lat>/<lon>
 * Zoom isn't used anywhere yet.
 */
class SpecialMap extends SpecialPage {
	public function __construct( $name = 'Map' ) {
		parent::__construct( $name, /* $restriction */ '', /* $listed */ false );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.kartographer.specialMap' );

		$coord = self::parseSubpage( $par );
		if ( !$coord ) {
			$coordText = wfMessage( 'kartographer-specialmap-invalid-coordinates' )->text();
			$markerHtml = '';
		} else {
			list( , $lat, $lon ) = $coord;
			$coordText = CoordFormatter::format( $lat, $lon, $this->getLanguage() );
			list( $x, $y ) = EPSG3857::latLonToPoint( [ $lat, $lon ], 0 );
			$markerHtml = Html::element( 'div',
				[
					'id' => 'mw-specialMap-marker',
					'style' => "left:{$x}px; top:{$y}px;"
				]
			);
		}

		$attributions = Html::rawElement( 'div', [ 'id' => 'mw-specialMap-attributions' ],
			wfMessage( 'kartographer-attribution' )->title( $this->getTitle() )->parse() );

		$this->getOutput()->addHTML(
			Html::openElement( 'div', [ 'id' => 'mw-specialMap-container', 'class' => 'thumb' ] )
				. Html::openElement( 'div', [ 'class' => 'thumbinner' ] )
					. Html::openElement( 'div', [ 'id' => 'mw-specialMap-inner' ] )
						. Html::element( 'div', [ 'id' => 'mw-specialMap-map' ] )
						. $markerHtml
						. $attributions
					. Html::closeElement( 'div' )
					. Html::openElement( 'div', [ 'id' => 'mw-specialMap-caption', 'class' => 'thumbcaption' ] )
						. Html::element( 'span', [ 'id' => 'mw-specialMap-icon' ] )
						. Html::element( 'span', [ 'id' => 'mw-specialMap-coords' ], $coordText )
					. Html::closeElement( 'div' )
				. Html::closeElement( 'div' )
			. Html::closeElement( 'div' )
		);
	}

	/**
	 * Parses subpage parameter to this special page into zoom / lat /lon
	 *
	 * @param $par
	 * @return array|bool
	 */
	public static function parseSubpage( $par ) {
		if ( !preg_match( '#^(?<zoom>\d+)/(?<lat>-?\d+(\.\d+)?)/(?<lon>-?\d+(\.\d+)?)$#', $par, $matches ) ) {
			return false;
		}

		if ( class_exists( Globe::class ) ) {
			$globe = new Globe( 'earth' );

			if ( !$globe->coordinatesAreValid( $matches['lat'], $matches['lon'] ) ) {
				return false;
			}
		}

		return [ (int)$matches['zoom'], (float)$matches['lat'], (float)$matches['lon'] ];
	}

	/**
	 * Returns a Title for a link to the coordinates provided
	 *
	 * @param float $lat
	 * @param float $lon
	 * @param int $zoom
	 * @return Title
	 */
	public static function link( $lat, $lon, $zoom ) {
		return SpecialPage::getTitleFor( 'Map', "$zoom/$lat/$lon" );
	}
}
