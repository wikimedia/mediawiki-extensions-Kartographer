<?php

namespace Kartographer\Tag;

use Html;
use Kartographer\PartialWikitextParser;
use MediaWiki\MediaWikiServices;

/**
 * The <mapframe> tag inserts a map into wiki page
 *
 * @license MIT
 */
class LegacyMapFrame extends LegacyTagHandler {
	private const THUMB_ALIGN_CLASSES = [
		'left' => 'tleft',
		'center' => 'tnone center',
		'right' => 'tright',
		'none' => 'tnone',
	];

	public const TAG = 'mapframe';

	/**
	 * @inheritDoc
	 */
	protected function render( PartialWikitextParser $parser, bool $isPreview ): string {
		// TODO if fullwidth, we really should use interactive mode..
		// BUT not possible to use both modes at the same time right now. T248023
		// Should be fixed, especially considering VE in page editing etc...
		$staticMode = $this->config->get( 'KartographerStaticMapframe' );
		if ( $staticMode && $isPreview ) {
			$this->getOutput()->setJsConfigVar( 'wgKartographerStaticMapframePreview', true );
		}
		$this->getOutput()->addModules( [ $staticMode && !$isPreview
			? 'ext.kartographer.staticframe'
			: 'ext.kartographer.frame' ] );

		$gen = new MapFrameAttributeGenerator( $this->args, $this->config );
		$attrs = $gen->prepareAttrs();

		if ( $this->args->showGroups ) {
			$this->state->addInteractiveGroups( $this->args->showGroups );
		}

		$page = $this->parser->getPage();
		$pageTitle = $page ?
			MediaWikiServices::getInstance()->getTitleFormatter()->getPrefixedText( $page ) : '';
		$revisionId = $this->parser->getRevisionId();
		$imgAttrs = $gen->prepareImgAttrs( $isPreview, $pageTitle, $revisionId );
		$imgAttrs['alt'] = wfMessage( 'kartographer-static-mapframe-alt' )->text();

		$thumbnail = Html::element( 'img', $imgAttrs );
		if ( $isPreview ) {
			$thumbnail = Html::rawElement( 'noscript', [], $thumbnail );
			if ( $this->args->usesAutoPosition() ) {
				// Impossible to render .png thumbnails that depend on unsaved ExternalData. Preview
				// will replace this with a dynamic map anyway when JavaScript is available.
				$thumbnail = '';
			}
		}

		$containerClass = $attrs['containerClass'];
		unset( $attrs['containerClass'] );

		if ( $this->args->frameless ) {
			return Html::rawElement( 'a', $attrs, $thumbnail );
		}

		$html = Html::rawElement( 'a', $attrs, $thumbnail );
		$caption = (string)$this->args->text;
		if ( $caption !== '' ) {
			$html .= Html::rawElement( 'div', [ 'class' => 'thumbcaption' ],
				$parser->halfParseWikitext( $caption ) );
		}

		return Html::rawElement( 'div', [ 'class' => [
				...$containerClass,
				'thumb',
				self::THUMB_ALIGN_CLASSES[$this->args->align],
			] ],
			Html::rawElement( 'div', [
					'class' => 'thumbinner',
					'style' => "width: $gen->cssWidth;",
				], $html ) );
	}
}
