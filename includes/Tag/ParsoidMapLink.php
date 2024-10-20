<?php

namespace Kartographer\Tag;

use DOMException;
use Kartographer\CoordFormatter;
use Kartographer\ParsoidUtils;
use MediaWiki\Json\FormatJson;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * @license MIT
 */
class ParsoidMapLink extends ParsoidTagHandler {

	public const TAG = 'maplink';

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $src
	 * @param array $extArgs
	 * @return DocumentFragment
	 * @throws DOMException
	 */
	public function sourceToDom( ParsoidExtensionAPI $extApi, string $src, array $extArgs ) {
		$extApi->getMetadata()->addModules( [ 'ext.kartographer.link' ] );
		[ $status, $args, $geometries ] = $this->parseTag( $extApi, $src, $extArgs );
		if ( !$status->isGood() ) {
			return $this->reportErrors( $extApi, self::TAG, $status );
		}

		$gen = new MapLinkAttributeGenerator( $args );
		$attrs = $gen->prepareAttrs();

		$text = $args->getTextWithFallback();
		if ( $text === null ) {
			$formatter = new CoordFormatter( $args->lat, $args->lon );
			$text = $formatter->formatParsoidSpan( $extApi, null );
		} elseif ( $text !== '' && !ctype_alnum( $text ) ) {
			// Don't parse trivial alphanumeric-only strings, e.g. counters like "A" or "99".
			$text = $extApi->wikitextToDOM( $text, [
				'parseOpts' => [
					'extTag' => 'maplink',
					'context' => 'inline',
				],
				// the wikitext is embedded into a JSON attribute, processing in a new frame seems to be the right move
				// to avoid DSR failures
				'processInNewFrame' => true,
				'clearDSROffsets' => true,
			], false );
		}
		$doc = $extApi->getTopLevelDoc();
		if ( is_string( $text ) ) {
			$text = ( trim( $text ) !== '' ) ? $doc->createTextNode( $text ) : null;
		}

		$dom = $doc->createDocumentFragment();
		$a = $doc->createElement( 'a' );
		if ( $args->groupId === null && $geometries ) {
			$groupId = '_' . sha1( FormatJson::encode( $geometries, false,
					FormatJson::ALL_OK ) );
			$args->groupId = $groupId;
			$args->showGroups[] = $groupId;
		}

		if ( $args->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $args->showGroups, false,
				FormatJson::ALL_OK );
			$dataKart = [
				'groupId' => $args->groupId,
				'showGroups' => $args->showGroups,
				'geometries' => $geometries
			];
			$a->setAttribute( 'data-kart', json_encode( $dataKart ) );
		}

		ParsoidUtils::addAttributesToNode( $attrs, $a );
		if ( $text ) {
			$a->appendChild( $text );
		}

		$dom->appendChild( $a );
		return $dom;
	}

}
