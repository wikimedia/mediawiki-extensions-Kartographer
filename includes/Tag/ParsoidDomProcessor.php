<?php

namespace Kartographer\Tag;

use Kartographer\SimpleStyleParser;
use MediaWiki\Category\TrackingCategories;
use MediaWiki\Config\Config;
use MediaWiki\Json\FormatJson;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputStringSets;
use MediaWiki\Title\Title;
use stdClass;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMTraverser;

/**
 * @license MIT
 */
class ParsoidDomProcessor extends DOMProcessor {

	public function __construct(
		private readonly Config $config,
		private readonly TrackingCategories $trackingCategories,
	) {
	}

	/** @inheritDoc */
	public function wtPostprocess( ParsoidExtensionAPI $extApi, Node $root, array $options ): void {
		if ( !( $root instanceof Element ) ) {
			return;
		}

		// Optimization: skip postprocessing if there are no kartographer
		// nodes.  Use the presence of the kartographer modules as a hint
		// that there are kartographer nodes on this page.
		$metadata = $extApi->getMetadata();
		if ( $metadata instanceof ParserOutput ) {
			$kartMods = [
				'ext.kartographer.link',
				'ext.kartographer.staticframe',
				'ext.kartographer.frame'
			];
			if ( array_intersect( $kartMods, $metadata->getModules() ) === [] ) {
				return;
			}
		}

		$state = [
			'broken' => 0,
			'interactiveGroups' => [],
			'requestedGroups' => [],
			'counters' => [],
			'maplinks' => 0,
			'mapframes' => 0,
			'data' => [],
		];

		$mapServer = $this->config->get( 'KartographerMapServer' );
		$extApi->getMetadata()->addModuleStyles( [ 'ext.kartographer.style' ] );
		$extApi->getMetadata()->appendOutputStrings(
			ParserOutputStringSets::EXTRA_CSP_DEFAULT_SRC->value,
			[ $mapServer ]
		);

		$traverser = new DOMTraverser( false, true );
		$traverser->addHandler( null, function ( $node ) use ( &$state, $extApi ) {
			if ( $node instanceof Element && $node->hasAttribute( 'data-mw-kartographer' ) ) {
				$this->processKartographerNode( $node, $extApi, $state );
			}
			return true;
		} );
		$traverser->traverse( $extApi->getSiteConfig(), $root );

		if ( $state['broken'] > 0 ) {
			$this->addCategory( $extApi, 'kartographer-broken-category' );
		}
		if ( $state['maplinks'] + $state['mapframes'] > $state['broken'] ) {
			$this->addCategory( $extApi, 'kartographer-tracking-category' );
		}

		$interactive = $state['interactiveGroups'];
		$state['interactiveGroups'] = array_keys( $state['interactiveGroups'] );
		$state['requestedGroups'] = array_keys( $state['requestedGroups'] );
		$state['parsoidIntVersion'] = $this->config->get( 'KartographerParsoidVersion' );
		$extApi->getMetadata()->setExtensionData( 'kartographer', $state );

		foreach ( $interactive as $req ) {
			if ( !isset( $state['data'][$req] ) ) {
				$state['data'][$req] = [];
			}
		}
		$extApi->getMetadata()->setJsConfigVar( 'wgKartographerLiveData', $state['data'] ?? [] );
	}

	private function processKartographerNode( Element $kartnode, ParsoidExtensionAPI $extApi, array &$state ): void {
		$tagName = $kartnode->getAttribute( 'data-mw-kartographer' ) ?? '';
		if ( $tagName !== '' ) {
			$state[$tagName . 's' ]++;
		}

		$markerStr = $kartnode->getAttribute( 'data-kart' );
		$kartnode->removeAttribute( 'data-kart' );
		if ( $markerStr === 'error' ) {
			$state['broken']++;
			return;
		}
		$marker = json_decode( $markerStr ?? '' );

		$state['requestedGroups'] = array_merge( $state['requestedGroups'], $marker->showGroups ?? [] );
		if ( $tagName === ParsoidMapFrame::TAG ) {
			$state['interactiveGroups'] = array_merge( $state['interactiveGroups'], $marker->showGroups ?? [] );
		}

		if ( !$marker || !$marker->geometries || !$marker->geometries[0] instanceof stdClass ) {
			return;
		}
		[ $counter, $props ] = SimpleStyleParser::updateMarkerSymbolCounters( $marker->geometries,
			$state['counters'] );
		if ( $tagName === ParsoidMapLink::TAG && $counter ) {
			if ( DOMDataUtils::getDataMw( $kartnode )->getExtAttrib( 'text' ) === null ) {
				$text = $extApi->getTopLevelDoc()->createTextNode( $counter );
				$kartnode->replaceChild( $text, $kartnode->firstChild );
			}
		}

		$data = $marker->geometries;

		if ( $counter ) {
			// If we have a counter, we update the marker data prior to encoding the groupId, and we remove
			// the (previously computed) groupId from showGroups
			if ( ( $marker->groupId[0] ?? '' ) === '_' ) {
				// TODO unclear if this is necessary or if we could simply set it to [].
				$marker->showGroups = array_values( array_diff( $marker->showGroups, [ $marker->groupId ] ) );
				$marker->groupId = null;
			}
		}

		$groupId = $marker->groupId ?? null;
		if ( $groupId === null ) {
			// This hash calculation MUST be the same as in LegacyTagHandler::updateState
			$groupId = '_' . sha1( FormatJson::encode( $marker->geometries, false, FormatJson::ALL_OK ) );
			$marker->groupId = $groupId;
			$marker->showGroups[] = $groupId;
			$kartnode->setAttribute( 'data-overlays', FormatJson::encode( $marker->showGroups ) );
			$img = $kartnode->firstChild;
			// this should always be the case, but let make phan aware of it
			if ( $img instanceof Element ) {
				$this->updateSrc( $img, $groupId, $extApi );
				$this->updateSrcSet( $img, $groupId, $extApi );
			}
		}

		// There is no way to ever add anything to a private group starting with `_`
		if ( isset( $state['data'][$groupId] ) && !str_starts_with( $groupId, '_' ) ) {
			$state['data'][$groupId] = array_merge( $state['data'][$groupId], $data );
		} else {
			$state['data'][$groupId] = $data;
		}
	}

	private function updateSrc( Element $firstChild, string $groupId, ParsoidExtensionAPI $extApi ): void {
		$src = $firstChild->getAttribute( 'src' ) ?? '';
		if ( $src !== '' ) {
			$src = $this->updateUrl( $src, $extApi, $groupId );
			$firstChild->setAttribute( 'src', $src );
		}
	}

	private function updateSrcSet( Element $firstChild, string $groupId, ParsoidExtensionAPI $extApi ): void {
		$srcset = $firstChild->getAttribute( 'srcset' ) ?? '';
		if ( $srcset !== '' ) {
			$arr = explode( ', ', $srcset );
			$srcsets = [];
			foreach ( $arr as $plop ) {
				$toks = explode( ' ', $plop );
				$toks[0] = $this->updateUrl( $toks[0], $extApi, $groupId );
				$srcsets[] = implode( ' ', $toks );
			}
			$firstChild->setAttribute( 'srcset', implode( ', ', $srcsets ) );
		}
	}

	private function updateUrl( string $src, ParsoidExtensionAPI $extApi, string $groupId ): string {
		$url = explode( '?', $src );
		$attrs = wfCgiToArray( $url[1] );

		$linkTarget = $extApi->getPageConfig()->getLinkTarget();
		$pagetitle = Title::newFromLinkTarget( $linkTarget )->getPrefixedDBkey();
		$revisionId = $extApi->getPageConfig()->getRevisionId();
		$attrs = array_merge( $attrs,
			MapFrameAttributeGenerator::getUrlAttrs( $this->config, $pagetitle, $revisionId, [ $groupId ], 'parsoid' )
		);

		return $url[0] . '?' . wfArrayToCgi( $attrs );
	}

	/**
	 * Add category to the page, from its key
	 */
	private function addCategory( ParsoidExtensionAPI $extApi, string $category ): void {
		$linkTarget = $extApi->getPageConfig()->getLinkTarget();
		$pageRef = PageReferenceValue::localReference(
			$linkTarget->getNamespace(), $linkTarget->getDBkey()
		);
		$cat = $this->trackingCategories->resolveTrackingCategory( $category, $pageRef );
		if ( $cat ) {
			$extApi->getMetadata()->addCategory( $cat );
		}
	}

}
