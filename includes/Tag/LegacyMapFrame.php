<?php

namespace Kartographer\Tag;

use Kartographer\PartialWikitextParser;
use Kartographer\State;
use MediaWiki\Html\Html;

/**
 * The <mapframe> tag inserts a map into wiki page
 *
 * @license MIT
 */
class LegacyMapFrame extends LegacyTagHandler {

	public const TAG = 'mapframe';

	protected function updateState( State $state, array $geometries ): void {
		parent::updateState( $state, $geometries );
		// Must be after the parent call because that possibly added a private group hash
		$state->addInteractiveGroups( $this->args->showGroups );
	}

	/** @inheritDoc */
	protected function render( PartialWikitextParser $parser, bool $serverMayRenderOverlays ): string {
		// TODO if fullwidth, we really should use interactive mode..
		// BUT not possible to use both modes at the same time right now. T248023
		// Should be fixed, especially considering VE in page editing etc...
		$staticMode = $this->config->get( 'KartographerStaticMapframe' );
		if ( $staticMode && !$serverMayRenderOverlays ) {
			$this->getOutput()->setJsConfigVar( 'wgKartographerStaticMapframePreview', 1 );
		}
		$this->getOutput()->addModules( [ $staticMode && $serverMayRenderOverlays
			? 'ext.kartographer.staticframe'
			: 'ext.kartographer.frame' ] );

		$gen = new MapFrameAttributeGenerator( $this->args, $this->config );
		$attrs = $gen->prepareAttrs();

		$pageTitle = $this->parserContext->getPrefixedDBkey();
		$revisionId = $this->parserContext->getRevisionId();
		$imgAttrs = $gen->prepareImgAttrs( $serverMayRenderOverlays, $pageTitle, $revisionId );
		$imgAttrs['alt'] ??= wfMessage( 'kartographer-static-mapframe-alt' )->text();

		$thumbnail = Html::element( 'img', $imgAttrs );
		if ( !$serverMayRenderOverlays ) {
			$thumbnail = Html::rawElement( 'noscript', [], $thumbnail );
			if ( $this->args->usesAutoPosition() ) {
				// Impossible to render .png thumbnails that depend on unsaved ExternalData. Preview
				// will replace this with a dynamic map anyway when JavaScript is available.
				$thumbnail = '';
			}
		}

		$html = Html::rawElement( 'a', $attrs, $thumbnail );
		if ( $this->args->frameless ) {
			return $html;
		}

		$caption = (string)$this->args->text;
		if ( $caption !== '' ) {
			$html .= Html::rawElement( 'div', [ 'class' => 'thumbcaption' ],
				$parser->halfParseWikitext( $caption ) );
		}

		return Html::rawElement( 'div', [ 'class' => $gen->getThumbClasses() ],
			Html::rawElement( 'div', [
					'class' => 'thumbinner',
					'style' => "width: $gen->cssWidth;",
				], $html ) );
	}

}
