<?php

namespace Kartographer\Tag;

use InvalidArgumentException;
use Parser;

/**
 * @license MIT
 */
class ParserFunctionTracker {

	/** @var Parser */
	private $parser;

	/**
	 * @param Parser $parser
	 */
	public function __construct( Parser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * @return bool False if the limit has been exceeded
	 */
	public function incrementExpensiveFunctionCount(): bool {
		return $this->parser->incrementExpensiveFunctionCount();
	}

	/**
	 * @param array<string,bool> $messages
	 */
	public function addTrackingCategories( array $messages ) {
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
