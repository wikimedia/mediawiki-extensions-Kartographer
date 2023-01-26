<?php

namespace Kartographer\Tag;

use Kartographer\CoordFormatter;
use Kartographer\PartialWikitextParser;
use MediaWiki\Html\Html;

/**
 * The <maplink> tag creates a link that, when clicked, will open a dynamic map in Special:Map page
 *
 * @license MIT
 */
class LegacyMapLink extends LegacyTagHandler {

	public const TAG = 'maplink';

	/** @inheritDoc */
	protected function render( PartialWikitextParser $parser, bool $serverMayRenderOverlays ): string {
		$this->getOutput()->addModules( [ 'ext.kartographer.link' ] );

		$gen = new MapLinkAttributeGenerator( $this->args );
		$attrs = $gen->prepareAttrs();

		// @todo: Mapbox markers don't support localized numbers yet
		$text = $this->args->getTextWithFallback();
		if ( $text === null ) {
			$formatter = new CoordFormatter( $this->args->lat, $this->args->lon );
			$text = $formatter->format( $this->parserContext->getTargetLanguage() );
		} else {
			$text = $parser->halfParseWikitext( $text );
		}

		return Html::rawElement( 'a', $attrs, $text );
	}
}
