<?php

namespace Kartographer;

use Wikimedia\Bcp47Code\Bcp47CodeValue;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * @license MIT
 */
class ParsoidUtils {

	/**
	 * Creates an internationalized Parsoid fragment. If $language is not provided, the returned
	 * fragment is generated in the user interface language.
	 * @param string $msgKey
	 * @param array $params
	 * @param ParsoidExtensionAPI $extApi
	 * @param string|null $language
	 * @return DocumentFragment
	 */
	public static function createLangFragment(
		string $msgKey, array $params, ParsoidExtensionAPI $extApi, ?string $language
	): DocumentFragment {
		if ( $language === null ) {
			return $extApi->createInterfaceI18nFragment( $msgKey, $params );
		}
		return $extApi->createLangI18nFragment( new Bcp47CodeValue( $language ), $msgKey, $params );
	}
}
