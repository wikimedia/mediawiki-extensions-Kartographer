<?php

namespace Kartographer\Tag;

use Kartographer\ParsoidUtils;
use Kartographer\SimpleStyleParser;
use MediaWiki\Config\Config;
use MediaWiki\Json\FormatJson;
use MediaWiki\Parser\ParserOutputStringSets;
use MediaWiki\Title\Title;
use stdClass;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMTraverser;

/**
 * @license MIT
 */
class ParsoidDomProcessor extends DOMProcessor {

	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/** @inheritDoc */
	public function wtPostprocess( ParsoidExtensionAPI $extApi, Node $root, array $options ): void {
		if ( !( $root instanceof Element ) ) {
			return;
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

		// FIXME This only selects data-mw-kartographer nodes without exploring HTML that may be stored in
		// attributes. We need to expand the traversal to find these as well.
		$kartnodes = DOMCompat::querySelectorAll( $root, '*[data-mw-kartographer]' );

		// let's avoid adding data to the page if there's no kartographer nodes!
		if ( !$kartnodes ) {
			return;
		}

		$mapServer = $this->config->get( 'KartographerMapServer' );
		$extApi->getMetadata()->addModuleStyles( [ 'ext.kartographer.style' ] );
		$extApi->getMetadata()->appendOutputStrings( ParserOutputStringSets::EXTRA_CSP_DEFAULT_SRC, [ $mapServer ] );

		$traverser = new DOMTraverser( false, true );
		$traverser->addHandler( null, function ( $node ) use ( &$state, $extApi ) {
			if ( $node instanceof Element && $node->hasAttribute( 'data-mw-kartographer' ) ) {
				$this->processKartographerNode( $node, $extApi, $state );
			}
			return true;
		} );
		$traverser->traverse( $extApi, $root );

		if ( $state['broken'] > 0 ) {
			ParsoidUtils::addCategory( $extApi, 'kartographer-broken-category' );
		}
		if ( $state['maplinks'] + $state['mapframes'] > $state['broken'] ) {
			ParsoidUtils::addCategory( $extApi, 'kartographer-tracking-category' );
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
			if ( !isset( DOMDataUtils::getDataMw( $kartnode )->attrs->text ) ) {
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
			MapFrameAttributeGenerator::getUrlAttrs( $this->config, $pagetitle, $revisionId, [ $groupId ] )
		);

		return $url[0] . '?' . wfArrayToCgi( $attrs );
	}

}
