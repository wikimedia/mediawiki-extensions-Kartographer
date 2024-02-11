<?php

namespace Kartographer\Tag;

use DOMException;
use Kartographer\ParsoidUtils;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * @license MIT
 */
class ParsoidMapFrame extends ParsoidTagHandler {

	public const TAG = 'mapframe';

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $src
	 * @param array $extArgs
	 * @return DocumentFragment
	 * @throws DOMException
	 */
	public function sourceToDom( ParsoidExtensionAPI $extApi, string $src, array $extArgs ) {
		$data = $this->parseTag( $extApi, $src, $extArgs );

		if ( !$data->args->status->isGood() ) {
			return $this->reportErrors( $extApi, self::TAG, $data->args->status );
		}

		// TODO if fullwidth, we really should use interactive mode..
		// BUT not possible to use both modes at the same time right now. T248023
		// Should be fixed, especially considering VE in page editing etc...
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$staticMode = $config->get( 'KartographerStaticMapframe' );

		$serverMayRenderOverlays = !$extApi->isPreview();
		if ( $staticMode && !$serverMayRenderOverlays ) {
			$extApi->getMetadata()->setJsConfigVar( 'wgKartographerStaticMapframePreview', 1 );
		}

		$extApi->getMetadata()->addModules( [ $staticMode && $serverMayRenderOverlays
			? 'ext.kartographer.staticframe'
			: 'ext.kartographer.frame' ] );

		$gen = new MapFrameAttributeGenerator( $data->args, $config );
		$attrs = $gen->prepareAttrs();

		$linkTarget = $extApi->getPageConfig()->getLinkTarget();
		$pageTitle = Title::newFromLinkTarget( $linkTarget )->getPrefixedText();
		$revisionId = $extApi->getPageConfig()->getRevisionId();
		$imgAttrs = $gen->prepareImgAttrs( $serverMayRenderOverlays, $pageTitle, $revisionId );

		$doc = $extApi->getTopLevelDoc();
		$dom = $doc->createDocumentFragment();

		$thumbnail = $doc->createElement( 'img' );

		ParsoidUtils::addAttributesToNode( $imgAttrs, $thumbnail );
		ParsoidUtils::createLangAttribute( $thumbnail, 'alt', 'kartographer-static-mapframe-alt', [], $extApi,
			null );

		if ( !$serverMayRenderOverlays ) {
			$noscript = $doc->createElement( 'noscript' );
			$noscript->appendChild( $thumbnail );
			$thumbnail = $noscript;
			if ( $data->args->usesAutoPosition() ) {
				// Impossible to render .png thumbnails that depend on unsaved ExternalData. Preview
				// will replace this with a dynamic map anyway when JavaScript is available.
				$thumbnail = $doc->createTextNode( '' );
			}
		}

		$a = $doc->createElement( 'a' );
		$dataKart = [
			'groupId' => $data->args->groupId,
			'showGroups' => $data->args->showGroups,
			'geometries' => $data->geometries
		];
		$a->setAttribute( 'data-kart', json_encode( $dataKart ) );
		ParsoidUtils::addAttributesToNode( $attrs, $a );

		$a->appendChild( $thumbnail );

		if ( $data->args->frameless ) {
			$dom->appendChild( $a );
			return $dom;
		}
		$thumbinner = $doc->createElement( 'div' );

		$thumbinner->setAttribute( 'class', 'thumbinner' );
		$thumbinner->setAttribute( 'style', "width: $gen->cssWidth;" );
		$thumbinner->appendChild( $a );
		$caption = (string)$data->args->text;
		if ( $caption !== '' ) {
			$parsedCaption = $extApi->wikitextToDOM( $caption, [
				'parseOpts' => [
					'extTag' => 'mapframe',
					'context' => 'inline',
				],
			], false );
			$div = $doc->createElement( 'div' );
			$div->setAttribute( 'class', 'thumbcaption' );
			$div->appendChild( $parsedCaption );
			$thumbinner->appendChild( $div );
		}
		$container = $doc->createElement( 'div' );
		$containerClasses = $gen->getThumbClasses();
		$container->setAttribute( 'class', implode( ' ', $containerClasses ) );
		$container->appendChild( $thumbinner );
		$dom->appendChild( $container );
		return $dom;
	}

}
