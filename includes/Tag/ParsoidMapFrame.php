<?php

namespace Kartographer\Tag;

use DOMException;
use Kartographer\ParsoidUtils;
use MediaWiki\Config\Config;
use MediaWiki\Title\Title;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * @license MIT
 */
class ParsoidMapFrame extends ParsoidTagHandler {

	public const TAG = 'mapframe';

	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $src
	 * @param array $extArgs
	 * @return DocumentFragment
	 * @throws DOMException
	 */
	public function sourceToDom( ParsoidExtensionAPI $extApi, string $src, array $extArgs ) {
		[ $status, $args, $geometries ] = $this->parseTag( $extApi, $src, $extArgs );

		if ( !$status->isGood() ) {
			return $this->reportErrors( $extApi, self::TAG, $status );
		}

		// TODO if fullwidth, we really should use interactive mode..
		// BUT not possible to use both modes at the same time right now. T248023
		// Should be fixed, especially considering VE in page editing etc...
		$staticMode = $this->config->get( 'KartographerStaticMapframe' );

		$serverMayRenderOverlays = !$extApi->isPreview();
		if ( $staticMode && !$serverMayRenderOverlays ) {
			$extApi->getMetadata()->setJsConfigVar( 'wgKartographerStaticMapframePreview', 1 );
		}

		$extApi->getMetadata()->addModules( [ $staticMode && $serverMayRenderOverlays
			? 'ext.kartographer.staticframe'
			: 'ext.kartographer.frame' ] );

		$gen = new MapFrameAttributeGenerator( $args, $this->config );
		$attrs = $gen->prepareAttrs();

		$linkTarget = $extApi->getPageConfig()->getLinkTarget();
		$pageTitle = Title::newFromLinkTarget( $linkTarget )->getPrefixedDBkey();
		$revisionId = $extApi->getPageConfig()->getRevisionId();
		$imgAttrs = $gen->prepareImgAttrs( $serverMayRenderOverlays, $pageTitle, $revisionId );

		$doc = $extApi->getTopLevelDoc();
		$dom = $doc->createDocumentFragment();

		$thumbnail = $doc->createElement( 'img' );

		ParsoidUtils::addAttributesToNode( $imgAttrs, $thumbnail );
		if ( !isset( $imgAttrs['alt'] ) ) {
			ParsoidUtils::createLangAttribute( $thumbnail, 'alt',
				'kartographer-static-mapframe-alt', [], $extApi, null );
		}

		if ( !$serverMayRenderOverlays ) {
			$noscript = $doc->createElement( 'noscript' );
			$noscript->appendChild( $thumbnail );
			$thumbnail = $noscript;
			if ( $args->usesAutoPosition() ) {
				// Impossible to render .png thumbnails that depend on unsaved ExternalData. Preview
				// will replace this with a dynamic map anyway when JavaScript is available.
				$thumbnail = $doc->createTextNode( '' );
			}
		}

		$a = $doc->createElement( 'a' );
		$dataKart = [
			'groupId' => $args->groupId,
			'showGroups' => $args->showGroups,
			'geometries' => $geometries
		];
		$a->setAttribute( 'data-kart', json_encode( $dataKart ) );
		ParsoidUtils::addAttributesToNode( $attrs, $a );

		$a->appendChild( $thumbnail );

		if ( $args->frameless ) {
			$dom->appendChild( $a );
			return $dom;
		}
		$thumbinner = $doc->createElement( 'div' );

		$thumbinner->setAttribute( 'class', 'thumbinner' );
		$thumbinner->setAttribute( 'style', "width: $gen->cssWidth;" );
		$thumbinner->appendChild( $a );
		$caption = (string)$args->text;
		if ( $caption !== '' ) {
			$parsedCaption = $extApi->wikitextToDOM( $caption, [
				'parseOpts' => [
					'extTag' => 'mapframe',
					'context' => 'inline',
				],
				// the wikitext is embedded into a JSON attribute, processing in a new frame seems to be the right move
				// to avoid DSR failures
				'processInNewFrame' => true,
				'clearDSROffsets' => true,
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
