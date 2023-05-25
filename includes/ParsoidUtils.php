<?php

namespace Kartographer;

use Wikimedia\Bcp47Code\Bcp47CodeValue;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * @license MIT
 */
class ParsoidUtils {

	/**
	 * Creates an internationalized Parsoid fragment. If $language is not provided, the returned
	 * fragment is generated in the page language.
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
			return $extApi->createPageContentI18nFragment( $msgKey, $params );
		}
		return $extApi->createLangI18nFragment( new Bcp47CodeValue( $language ), $msgKey, $params );
	}

	/**
	 * Creates an internationalized Parsoid attribute on the provided element. If $language is
	 * not provided, the created attribute is generated in the page language.
	 * @param Element $element
	 * @param string $name
	 * @param string $msgKey
	 * @param array $params
	 * @param ParsoidExtensionAPI $extApi
	 * @param string|null $language
	 * @return void
	 */
	public static function createLangAttribute(
		Element $element, string $name, string $msgKey, array $params, ParsoidExtensionAPI $extApi, ?string $language
	): void {
		if ( $language === null ) {
			$extApi->addPageContentI18nAttribute( $element, $name, $msgKey, $params );
		} else {
			$extApi->addLangI18nAttribute( $element, new Bcp47CodeValue( $language ), $name, $msgKey, $params );
		}
	}
}
