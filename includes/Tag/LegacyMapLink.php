<?php

namespace Kartographer\Tag;

use Html;
use Kartographer\CoordFormatter;
use Kartographer\PartialWikitextParser;

/**
 * The <maplink> tag creates a link that, when clicked, will open a dynamic map in Special:Map page
 *
 * @license MIT
 */
class LegacyMapLink extends LegacyTagHandler {

	public const TAG = 'maplink';

	/**
	 * @inheritDoc
	 */
	protected function render( PartialWikitextParser $parser, bool $isPreview ): string {
		$this->getOutput()->addModules( [ 'ext.kartographer.link' ] );

		// @todo: Mapbox markers don't support localized numbers yet
		$text = $this->args->text;
		if ( $text === null ) {
			$text = $this->counter ?: ( new CoordFormatter( $this->args->lat, $this->args->lon ) )
				->format( $this->getLanguageCode() );
		} elseif ( $text !== '' ) {
			$text = $parser->halfParseWikitext( $text );
		}

		$gen = new MapLinkAttributeGenerator( $this->args, $this->config, $this->markerProperties );
		$attrs = $gen->prepareAttrs();

		return Html::rawElement( 'a', $attrs, $text );
	}
}
