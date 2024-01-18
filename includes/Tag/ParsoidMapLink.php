<?php

namespace Kartographer\Tag;

use DOMException;
use FormatJson;
use Kartographer\CoordFormatter;
use Kartographer\ParsoidUtils;
use MediaWiki\MediaWikiServices;
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

		$data = $this->parseTag( $extApi, $src, $extArgs );
		if ( !$data->args->status->isGood() ) {
			return $this->reportErrors( $extApi, self::TAG, $data->args->status );
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$gen = new MapLinkAttributeGenerator( $data->args, $config );
		$attrs = $gen->prepareAttrs();

		$text = $data->args->getTextWithFallback();
		if ( $text === null ) {
			$formatter = new CoordFormatter( $data->args->lat, $data->args->lon );
			// TODO: for now, we're using the old wfMessage method with English hardcoded. This should not
			// stay that way.
			// When l10n is added to Parsoid, replace this line with
			// ( new CoordFormatter( $this->args->lat, $this->args->lon ) )->formatParsoidSpan( $extApi, null );
			$text = $formatter->format( 'en' );
		} elseif ( $text !== '' && !ctype_alnum( $text ) ) {
			// Don't parse trivial alphanumeric-only strings, e.g. counters like "A" or "99".
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
		if ( $data->args->groupId === null && $data->geometries ) {
			$groupId = '_' . sha1( FormatJson::encode( $data->geometries, false,
					FormatJson::ALL_OK ) );
			$data->args->groupId = $groupId;
			$data->args->showGroups[] = $groupId;
		}

		if ( $data->args->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $data->args->showGroups, false,
				FormatJson::ALL_OK );
			$dataKart = [
				'groupId' => $data->args->groupId,
				'showGroups' => $data->args->showGroups,
				'geometries' => $data->geometries
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
