<?php

namespace Kartographer\Tag;

use FormatJson;
use Kartographer\ParsoidUtils;
use Kartographer\SimpleStyleParser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutputStringSets;
use stdClass;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * @license MIT
 */
class ParsoidDomProcessor extends DOMProcessor {

	/**
	 * @inheritDoc
	 */
	public function wtPostprocess( ParsoidExtensionAPI $extApi, Node $root, array $options ): void {
		if ( !( $root instanceof Element ) ) {
			return;
		}

		// Assumption: no instance is embedded in an attribute - otherwise it won't be found by the querySelector.
		// If that can happen, then this should be modified to also search in the attributes, but this feels very
		// expensive for a case that probably doesn't happen in practice.
		$kartnodes = DOMCompat::querySelectorAll( $root, '*[data-mw-kartographer]' );

		// let's avoid adding data to the page if there's no kartographer nodes!
		if ( empty( $kartnodes ) ) {
			return;
		}

		$mapServer = MediaWikiServices::getInstance()->getMainConfig()->get( 'KartographerMapServer' );
		$extApi->getMetadata()->addModuleStyles( [ 'ext.kartographer.style' ] );
		$extApi->getMetadata()->appendOutputStrings( ParserOutputStringSets::EXTRA_CSP_DEFAULT_SRC, [ $mapServer ] );

		$kartData = [];
		$counters = [];
		$requested = [];
		$interactive = [];
		$maplink = 0;
		$mapframe = 0;
		$broken = 0;

		foreach ( $kartnodes as $kartnode ) {
			$tagName = $kartnode->getAttribute( 'data-mw-kartographer' ) ?? '';
			if ( $tagName !== '' ) {
				$$tagName++;
			}

			$marker = $extApi->getTempNodeData( $kartnode, $tagName );
			if ( $marker === 'error' ) {
				$broken++;
				continue;
			}
			$requested = array_merge( $requested, $marker['showGroups'] ?? [] );
			if ( $tagName === ParsoidMapFrame::TAG ) {
				$interactive = array_merge( $interactive, $marker['showGroups'] ?? [] );
			}

			if ( !$marker || !$marker['geometries'] || !$marker['geometries'][0] instanceof stdClass ) {
				continue;
			}
			[ $counter, $props ] = SimpleStyleParser::updateMarkerSymbolCounters( $marker['geometries'], $counters );
			if ( $tagName === ParsoidMapLink::TAG ) {
				$text = $extApi->getTopLevelDoc()->createTextNode( $counter ?? '' );
				if ( !isset( DOMDataUtils::getDataMw( $kartnode )->attrs->text ) ) {
					$kartnode->replaceChild( $text, $kartnode->firstChild );
				}
			}

			$data = $marker['geometries'];

			if ( empty( $data ) && !$counter ) {
				continue;
			}

			if ( $counter ) {
				// If we have a counter, we update the marker data prior to encoding the groupId, and we remove
				// the (previously comupted) groupId from showGroups
				if ( isset( $marker['groupId'][0] ) && $marker['groupId'][0] === '_' ) {
					// TODO unclear if this is necessary or if we could simply set it to [].
					$marker['showGroups'] = array_values( array_diff( $marker['showGroups'], [ $marker['groupId'] ] ) );
					$marker[ 'groupId' ] = null;
				}
			}

			$groupId = $marker['groupId'] ?? null;
			if ( $groupId === null ) {
				// This hash calculation MUST be the same as in LegacyTagHandler::saveData
				$groupId = '_' . sha1( FormatJson::encode( $marker['geometries'], false, FormatJson::ALL_OK ) );
				$marker['groupId'] = $groupId;
				$marker['showGroups'][] = $groupId;
				$kartnode->setAttribute( 'data-overlays', FormatJson::encode( $marker['showGroups'] ) );
				$img = $kartnode->firstChild;
				// this should always be the case, but let make phan aware of it
				if ( $img instanceof Element ) {
					$this->updateSrc( $img, $groupId, $extApi );
					$this->updateSrcSet( $img, $groupId, $extApi );
				}
			}

			// There is no way to ever add anything to a private group starting with `_`
			if ( isset( $kartData[$groupId] ) && !str_starts_with( $groupId, '_' ) ) {
				// phan is grumbling without the ?? [], although it shouldn't
				$kartData[$groupId] = array_merge( $kartData[$groupId], $data ?? [] );
			} else {
				$kartData[$groupId] = $data;
			}
		}

		if ( $broken > 0 ) {
			ParsoidUtils::addCategory( $extApi, 'kartographer-broken-category' );
		}
		if ( $maplink + $mapframe > $broken ) {
			ParsoidUtils::addCategory( $extApi, 'kartographer-tracking-category' );
		}

		$state = [
			'broken' => $broken,
			'interactiveGroups' => array_keys( $interactive ),
			'requestedGroups' => array_keys( $requested ),
			'counters' => $counters,
			'maplinks' => $maplink,
			'mapframes' => $mapframe,
			'data' => $kartData,
			'parsoidIntVersion' =>
				MediaWikiServices::getInstance()->getMainConfig()->get( 'KartographerParsoidVersion' ),
		];
		$extApi->getMetadata()->setExtensionData( 'kartographer', $state );

		foreach ( $interactive as $req ) {
			if ( !isset( $kartData[$req] ) ) {
				$kartData[$req] = [];
			}
		}
		$extApi->getMetadata()->setJsConfigVar( 'wgKartographerLiveData', $kartData );
	}

	/**
	 * @param Element $firstChild
	 * @param string $groupId
	 * @param ParsoidExtensionAPI $extApi
	 * @return void
	 */
	private function updateSrc( Element $firstChild, string $groupId, ParsoidExtensionAPI $extApi ) {
		$src = $firstChild->getAttribute( 'src' ) ?? '';
		if ( $src !== '' ) {
			$src = $this->updateUrl( $src, $extApi, $groupId );
			$firstChild->setAttribute( 'src', $src );
		}
	}

	/**
	 * @param Element $firstChild
	 * @param string $groupId
	 * @param ParsoidExtensionAPI $extApi
	 * @return void
	 */
	private function updateSrcSet( Element $firstChild, string $groupId, ParsoidExtensionAPI $extApi ) {
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

	/**
	 * @param string $src
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $groupId
	 * @return string
	 */
	private function updateUrl( string $src, ParsoidExtensionAPI $extApi, string $groupId ): string {
		$url = explode( '?', $src );
		$attrs = wfCgiToArray( $url[1] );

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$pagetitle = $extApi->getPageConfig()->getTitle();
		$revisionId = $extApi->getPageConfig()->getRevisionId();
		$attrs = array_merge( $attrs,
			MapFrameAttributeGenerator::getUrlAttrs( $config, $pagetitle, $revisionId, [ $groupId ] )
		);

		return $url[0] . '?' . wfArrayToCgi( $attrs );
	}

}
