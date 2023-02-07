<?php

namespace Kartographer\Tag;

use Html;
use Kartographer\CoordFormatter;

/**
 * The <maplink> tag creates a link that, when clicked, will open a dynamic map in Special:Map page
 *
 * @license MIT
 */
class LegacyMapLink extends LegacyTagHandler {
	use MapLinkTrait;

	public const TAG = 'maplink';

	/**
	 * @inheritDoc
	 */
	protected function render( bool $isPreview ): string {
		$this->getOutput()->addModules( [ 'ext.kartographer.link' ] );

		// @todo: Mapbox markers don't support localized numbers yet
		$text = $this->args->text;
		if ( $text === null ) {
			$text = $this->counter
				?: CoordFormatter::format( $this->args->lat, $this->args->lon, $this->getLanguageCode() );
		}
		$text = $this->parser->recursiveTagParse( $text, $this->frame );

		$attrs = $this->prepareAttrs( [
			'mapStyle' => $this->args->mapStyle,
			'zoom' => $this->args->zoom,
			'lat' => $this->args->lat,
			'lon' => $this->args->lon,
			'resolvedLangCode' => $this->args->resolvedLangCode,
			'specifiedLangCode' => $this->args->specifiedLangCode,
			'cssClass' => $this->args->cssClass,
			'showGroups' => $this->args->showGroups,
			'markerProperties' => $this->markerProperties
		], $this->config );

		return Html::rawElement( 'a', $attrs, $text );
	}
}
