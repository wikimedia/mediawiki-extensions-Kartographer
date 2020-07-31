<?php

namespace Kartographer;

use GeoData\Globe;
use Html;
use Kartographer\Projection\EPSG3857;
use SpecialPage;
use Title;

/**
 * Special page that works as a fallback destination for non-JS users
 * who click on map links. It displays a world map with a dot for the given location.
 * URL format: Special:Map/<zoom>/<lat>/<lon>
 * Zoom isn't used anywhere yet.
 */
class SpecialMap extends SpecialPage {

	/**
	 * @param string $name
	 */
	public function __construct( $name = 'Map' ) {
		parent::__construct( $name, /* $restriction */ '', /* $listed */ false );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addModuleStyles( 'ext.kartographer.specialMap' );
		$output->getCSP()->addDefaultSrc( $this->getConfig()->get( 'KartographerMapServer' ) );

		$coord = $this->parseSubpage( $par );
		if ( !$coord ) {
			$coordText = $this->msg( 'kartographer-specialmap-invalid-coordinates' )->text();
			$markerHtml = '';
		} else {
			[ 'lat' => $lat, 'lon' => $lon ] = $coord;
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
			$this->msg( 'kartographer-attribution' )->title( $this->getPageTitle() )->parse() );

		$this->getOutput()->addHTML(
			Html::openElement( 'div', [ 'id' => 'mw-specialMap-container', 'class' => 'thumb' ] )
				. Html::openElement( 'div', [ 'class' => 'thumbinner' ] )
					. Html::openElement( 'div', [ 'id' => 'mw-specialMap-inner' ] )
						. Html::element( 'div', [ 'id' => 'mw-specialMap-map' ] )
						. $markerHtml
						. $attributions
					. Html::closeElement( 'div' )
					. Html::openElement( 'div',
						[ 'id' => 'mw-specialMap-caption', 'class' => 'thumbcaption' ]
					)
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
	 * @param string $par
	 * @return array|false
	 */
	private function parseSubpage( $par ) {
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
	 * Returns a Title for a link to the coordinates provided
	 *
	 * @param float $lat
	 * @param float $lon
	 * @param int $zoom
	 * @param string $lang Optional language code. Defaults to 'local'
	 * @return Title
	 */
	public static function link( $lat, $lon, $zoom, $lang = 'local' ) {
		return SpecialPage::getTitleFor( 'Map', "$zoom/$lat/$lon/$lang" );
	}
}
