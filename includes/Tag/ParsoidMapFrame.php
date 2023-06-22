<?php

namespace Kartographer\Tag;

use DOMException;
use Kartographer\ParsoidUtils;
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
		$this->parseTag( $extApi, $src, $extArgs );
		if ( !$this->args->status->isGood() ) {
			return $this->reportErrors( $extApi );
		}

		// TODO if fullwidth, we really should use interactive mode..
		// BUT not possible to use both modes at the same time right now. T248023
		// Should be fixed, especially considering VE in page editing etc...
		$staticMode = $this->config->get( 'KartographerStaticMapframe' );

		// TODO fix isPreview
		$isPreview = $extApi->isPreview();
		if ( $staticMode && $isPreview ) {
			$extApi->getMetadata()->setJsConfigVar( 'wgKartographerStaticMapframePreview', true );
		}

		$extApi->getMetadata()->addModules( [ $staticMode && !$isPreview
			? 'ext.kartographer.staticframe'
			: 'ext.kartographer.frame' ] );
		$extApi->getMetadata()->addModuleStyles( [ 'ext.kartographer.style' ] );

		$gen = new MapFrameAttributeGenerator( $this->args, $this->config );
		$attrs = $gen->prepareAttrs();

		$pageTitle = $extApi->getPageConfig()->getTitle();
		$revisionId = $extApi->getPageConfig()->getRevisionId();
		$imgAttrs = $gen->prepareImgAttrs( $isPreview, $pageTitle, $revisionId );

		$doc = $extApi->getTopLevelDoc();
		$dom = $doc->createDocumentFragment();

		$thumbnail = $doc->createElement( 'img' );

		ParsoidUtils::addAttributesToNode( $imgAttrs, $thumbnail );
		ParsoidUtils::createLangAttribute( $thumbnail, 'alt', 'kartographer-static-mapframe-alt', [], $extApi,
			null );

		if ( $isPreview ) {
			$noscript = $doc->createElement( 'noscript' );
			$noscript->appendChild( $thumbnail );
			$thumbnail = $noscript;
			if ( $this->args->usesAutoPosition() ) {
				// Impossible to render .png thumbnails that depend on unsaved ExternalData. Preview
				// will replace this with a dynamic map anyway when JavaScript is available.
				$thumbnail = $doc->createTextNode( '' );
			}
		}

		$a = $doc->createElement( 'a' );
		$dataKart = [
			'groupId' => $this->args->groupId,
			'showGroups' => $this->args->showGroups,
			'geometries' => $this->geometries
		];
		$extApi->setTempNodeData( $a, $dataKart );
		ParsoidUtils::addAttributesToNode( $attrs, $a );

		$a->appendChild( $thumbnail );

		if ( $this->args->frameless ) {
			$dom->appendChild( $a );
			return $dom;
		}
		$thumbinner = $doc->createElement( 'div' );

		$thumbinner->setAttribute( 'class', 'thumbinner' );
		$thumbinner->setAttribute( 'style', "width: $gen->cssWidth;" );
		$thumbinner->appendChild( $a );
		$caption = (string)$this->args->text;
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
