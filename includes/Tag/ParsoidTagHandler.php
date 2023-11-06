<?php

namespace Kartographer\Tag;

use Closure;
use Kartographer\ParsoidWikitextParser;
use Kartographer\SimpleStyleParser;
use LogicException;
use MediaWiki\MediaWikiServices;
use StatusValue;
use stdClass;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * @license MIT
 */
class ParsoidTagHandler extends ExtensionTagHandler {
	public const TAG = '';

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $input
	 * @param stdClass[] $extArgs
	 * @return ParsoidKartographerData
	 */
	protected function parseTag( ParsoidExtensionAPI $extApi, string $input, array $extArgs ): ParsoidKartographerData {
		$data = new ParsoidKartographerData();
		$data->args = $this->processParsoidExtensionArguments( $extArgs );

		if ( $data->args->status->isOK() ) {
			$wp = new ParsoidWikitextParser( $extApi );
			$sspStatus = ( new SimpleStyleParser( $wp ) )->parse( $input );
			if ( $sspStatus->isOk() ) {
				$data->geometries = $sspStatus->getValue()['data'];
			}
			// FIXME: This is a hack but necessary to communicate to the outside world
			// it's necessary to overwrite value to also pass JSON error status
			$data->args->status->merge( $sspStatus, true );
		}

		if ( $data->geometries ) {
			$marker = SimpleStyleParser::findFirstMarkerSymbol( $data->geometries );
			if ( $marker ) {
				[ $counter, $data->markerProperties ] = $marker;
				$data->args->setFallbackText( $counter );
			}
		}

		return $data;
	}

	/**
	 * @param stdClass[] $extArgs
	 * @return MapTagArgumentValidator
	 */
	private function processParsoidExtensionArguments( array $extArgs ): MapTagArgumentValidator {
		$services = MediaWikiServices::getInstance();

		$args = [];
		foreach ( $extArgs as $extArg ) {
			$args[$extArg->k] = $extArg->v;
		}

		return new MapTagArgumentValidator( static::TAG, $args,
			$services->getMainConfig(),
			// FIXME setting the display language to English for the first version, needs to be fixed when we
			// have a localization solution for Parsoid
			$services->getLanguageFactory()->getLanguage( 'en' ),
			$services->getLanguageNameUtils()
		);
	}

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $tag
	 * @param StatusValue $status
	 * @return DocumentFragment
	 */
	public function reportErrors(
		ParsoidExtensionAPI $extApi, string $tag, StatusValue $status
	): DocumentFragment {
		$errors = array_merge( $status->getErrorsByType( 'error' ),
			$status->getErrorsByType( 'warning' )
		);
		if ( !$errors ) {
			throw new LogicException( __METHOD__ . '(): attempt to report error when none took place' );
		}
		$dom = $extApi->getTopLevelDoc()->createDocumentFragment();
		$div = $extApi->getTopLevelDoc()->createElement( 'div' );
		$div->setAttribute( 'class', 'mw-kartographer-error' );
		$div->setAttribute( 'data-mw-kartographer', $tag );
		$div->setAttribute( 'data-kart', 'error' );
		$dom->appendChild( $div );
		if ( count( $errors ) > 1 ) {
			// kartographer-error-context-multi takes two parameters: the tag name and the
			// (formatted, localized) list of errors. We pass '' as second parameter and add the
			// individual errors to the fragment instead.
			$errContainer = $extApi->createInterfaceI18nFragment( 'kartographer-error-context-multi',
				[ static::TAG, '' ] );
			$div->appendChild( $errContainer );
			$ul = $extApi->getTopLevelDoc()->createElement( 'ul' );
			$div->appendChild( $ul );
			foreach ( $errors as $err ) {
				$li = $extApi->getTopLevelDoc()->createElement( 'li' );
				$ul->appendChild( $li );
				$err = $extApi->createInterfaceI18nFragment( $err['message'], $err['params'] );
				$li->appendChild( $err );
			}
		} else {
			// kartographer-error-context takes two parameters: the tag name and the
			// localized error. We pass '' as second parameter and add the
			// individual errors to the fragment instead.
			$errContainer = $extApi->createInterfaceI18nFragment( 'kartographer-error-context', [ static::TAG, '' ] );
			$div->appendChild( $errContainer );
			$err = $extApi->createInterfaceI18nFragment( $errors[0]['message'], $errors[0]['params'] );
			$div->appendChild( $err );
		}

		$jsonErrors = $this->getJSONValidatorLog( $extApi, $status );

		if ( $jsonErrors ) {
			$div->appendChild( $jsonErrors );
		}
		return $dom;
	}

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param StatusValue $status
	 * @return ?DocumentFragment
	 */
	private function getJSONValidatorLog( ParsoidExtensionAPI $extApi, StatusValue $status ): ?DocumentFragment {
		$errors = $status->getValue()['schema-errors'] ?? [];
		if ( !$errors ) {
			return null;
		}

		$dom = $extApi->getTopLevelDoc()->createDocumentFragment();
		$ul = $extApi->getTopLevelDoc()->createElement( 'ul' );
		$ul->setAttribute( 'class', 'mw-kartographer-error-log mw-collapsible mw-collapsed' );
		$dom->appendChild( $ul );
		/** These errors come from {@see \JsonSchema\Constraints\BaseConstraint::addError} */
		foreach ( $errors as $error ) {
			$li = $extApi->getTopLevelDoc()->createElement( 'li' );
			$pointer = $extApi->getTopLevelDoc()->createTextNode( $error['pointer'] );
			$sep = $extApi->createInterfaceI18nFragment( 'colon-separator', [] );
			$msg = $extApi->getTopLevelDoc()->createTextNode( $error['message'] );
			$li->appendChild( $pointer );
			$li->appendChild( $sep );
			$li->appendChild( $msg );
			$ul->appendChild( $li );
		}
		return $dom;
	}

	public function processAttributeEmbeddedHTML( ParsoidExtensionAPI $extApi, Element $elt, Closure $proc ): void {
		if ( $elt->hasAttribute( 'data-mw-kartographer' ) ) {
			$node = $elt;
		} else {
			$node = DOMCompat::querySelector( $elt, '*[data-mw-kartographer]' );
		}
		if ( !$node ) {
			return;
		}
		$exttagname = $node->getAttribute( 'data-mw-kartographer' );
		if ( !$exttagname ) {
			return;
		}
		$marker = json_decode( $node->getAttribute( 'data-kart' ) ?? '', false );
		if ( $marker instanceof stdClass && $marker->geometries ) {
			foreach ( $marker->geometries as $geom ) {
				if ( !isset( $geom->properties ) ) {
					continue;
				}
				foreach ( $geom->properties as $key => $prop ) {
					if ( in_array( $key, SimpleStyleParser::WIKITEXT_PROPERTIES ) ) {
						$geom->properties->{$key} = $proc( $prop );
					}
				}
			}
			$node->setAttribute( 'data-kart', json_encode( $marker ) );
		}
	}
}
