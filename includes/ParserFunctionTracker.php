<?php

namespace Kartographer;

use InvalidArgumentException;
use MediaWiki\Parser\Parser;

/**
 * @license MIT
 */
class ParserFunctionTracker {

	private Parser $parser;

	public function __construct( Parser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * @param array<string,bool> $messages
	 */
	public function addTrackingCategories( array $messages ): void {
		foreach ( $messages as $msg => $enabled ) {
			if ( !is_bool( $enabled ) ) {
				throw new InvalidArgumentException( '$messages must be an array mapping message keys to booleans' );
			}

			// Messages used here:
			// * kartographer-broken-category
			// * kartographer-tracking-category
			if ( $enabled ) {
				$this->parser->addTrackingCategory( $msg );
			}
		}
	}

}
