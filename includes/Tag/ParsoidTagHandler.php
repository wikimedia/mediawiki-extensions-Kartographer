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
use Wikimedia\Parsoid\Ext\Utils;
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
	 * @return array{StatusValue,MapTagArgumentValidator,stdClass[]}
	 */
	protected function parseTag( ParsoidExtensionAPI $extApi, string $input, array $extArgs ): array {
		$args = $this->processArguments(
			$this->convertParsoidExtensionArguments( $extArgs ),
			$extApi
		);
		$status = $args->status;

		$geometries = [];
		if ( $status->isOK() ) {
			$wp = new ParsoidWikitextParser( $extApi );
			$status = ( new SimpleStyleParser( $wp ) )->parse( $input );
			if ( $status->isOk() ) {
				$geometries = $status->getValue()['data'];
			}
		}

		if ( $geometries ) {
			$marker = SimpleStyleParser::findFirstMarkerSymbol( $geometries );
			if ( $marker ) {
				$args->setFirstMarkerProperties( ...$marker );
			}
		}

		return [ $status, $args, $geometries ];
	}

	/**
	 * @param stdClass[] $extArgs
	 * @return array<string,string>
	 */
	private function convertParsoidExtensionArguments( array $extArgs ): array {
		$args = [];
		foreach ( $extArgs as $extArg ) {
			// Might be an array or Token object when wikitext like <maplink {{1x|text}}=… /> is
			// used. The old parser doesn't resolve this either.
			if ( is_string( $extArg->k ) ) {
				if ( $extArg->k === 'text' && $extArg->vsrc ) {
					// preserve newlines and spaces for 'text' attribute
					$args[$extArg->k] = Utils::decodeWtEntities( $extArg->vsrc );
				} else {
					$args[$extArg->k] = $extArg->v;
				}
			}
		}
		return $args;
	}

	/**
	 * @param array<string,string> $args
	 * @param ParsoidExtensionAPI $extApi
	 * @return MapTagArgumentValidator
	 */
	private function processArguments(
		array $args,
		ParsoidExtensionAPI $extApi
	): MapTagArgumentValidator {
		$services = MediaWikiServices::getInstance();

		return new MapTagArgumentValidator( static::TAG, $args,
			$services->getMainConfig(),
			// TODO Ideally, this wouldn't need the page language, and it would generate an href with an i18n'd
			// attribute that would then get localized. But, this would require rich attributes to do cleanly, so
			// let's punt that to later.
			$services->getLanguageFactory()->getLanguage( $extApi->getPageConfig()->getPageLanguageBcp47() ),
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
		$errors = $status->getErrors();
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
