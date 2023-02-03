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

	/** @var string|null */
	private $cssClass = '';

	/**
	 * @inheritDoc
	 */
	protected function parseArgs(): void {
		$this->state->useMaplink();
		parent::parseArgs();
		$this->cssClass = $this->getText( 'class', '', '/^(|[a-zA-Z][-_a-zA-Z0-9]*)$/' );
	}

	/**
	 * @inheritDoc
	 */
	protected function render( bool $isPreview ): string {
		$this->getOutput()->addModules( [ 'ext.kartographer.link' ] );

		// @todo: Mapbox markers don't support localized numbers yet
		$text = $this->getText( 'text', null );
		if ( $text === null ) {
			$text = $this->counter
				?: CoordFormatter::format( $this->lat, $this->lon, $this->getLanguageCode() );
		}
		$text = $this->parser->recursiveTagParse( $text, $this->frame );

		$attrs = $this->prepareAttrs( [
			'mapStyle' => $this->mapStyle,
			'zoom' => $this->zoom,
			'lat' => $this->lat,
			'lon' => $this->lon,
			'resolvedLangCode' => $this->resolvedLangCode,
			'specifiedLangCode' => $this->specifiedLangCode,
			'cssClass' => $this->cssClass,
			'showGroups' => $this->showGroups,
			'markerProperties' => $this->markerProperties
		], $this->config );

		return Html::rawElement( 'a', $attrs, $text );
	}
}
