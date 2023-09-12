<?php

namespace Kartographer\Tag;

use DOMException;
use FormatJson;
use Kartographer\CoordFormatter;
use Kartographer\ParsoidUtils;
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

		$this->parseTag( $extApi, $src, $extArgs );
		if ( !$this->args->status->isGood() ) {
			return $this->reportErrors( $extApi, self::TAG );
		}

		$gen = new MapLinkAttributeGenerator( $this->args, $this->config, $this->markerProperties );
		$attrs = $gen->prepareAttrs();

		$text = $this->args->text;
		if ( $text === null ) {
			$text = $this->counter;
			if ( $text === null ) {
				$formatter = new CoordFormatter( $this->args->lat, $this->args->lon );
				// TODO: for now, we're using the old wfMessage method with English hardcoded. This should not
				// stay that way.
				// When l10n is added to Parsoid, replace this line with
				// ( new CoordFormatter( $this->args->lat, $this->args->lon ) )->formatParsoidSpan( $extApi, null );
				$text = $formatter->format( 'en' );
				if ( !$this->args->hasCoordinates() ) {
					$attrs['class'][] = 'error';
				}
			}
		} elseif ( $text !== '' ) {
			$text = $extApi->wikitextToDOM( $text, [
				'parseOpts' => [
					'extTag' => 'maplink',
					'context' => 'inline',
				] ], false );
		}
		$doc = $extApi->getTopLevelDoc();
		if ( is_string( $text ) ) {
			$text = ( trim( $text ) !== '' ) ? $doc->createTextNode( $text ) : null;
		}

		$dom = $doc->createDocumentFragment();
		$a = $doc->createElement( 'a' );
		if ( $this->args->groupId === null && $this->geometries ) {
			$groupId = '_' . sha1( FormatJson::encode( $this->geometries, false,
					FormatJson::ALL_OK ) );
			$this->args->groupId = $groupId;
			$this->args->showGroups[] = $groupId;
		}

		if ( $this->args->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $this->args->showGroups, false,
				FormatJson::ALL_OK );
			$dataKart = [
				'groupId' => $this->args->groupId,
				'showGroups' => $this->args->showGroups,
				'geometries' => $this->geometries
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
