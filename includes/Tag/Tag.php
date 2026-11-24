<?php

namespace Kartographer\Tag;

use StatusValue;

/**
 * Generic handler to validate and preprocess the arguments of an XML-style parser tag. This class
 * doesn't know anything about the arguments and what they mean.
 *
 * @license MIT
 */
class Tag {

	/**
	 * @param string $tagName Tag name, e.g. "maplink" for the <maplink> tag
	 * @param array<string,string> $args Optional key="value" pairs
	 * @param StatusValue $status for the collection of validation errors
	 */
	public function __construct(
		public readonly string $tagName,
		private readonly array $args,
		private readonly StatusValue $status,
	) {
	}

	/**
	 * @param string $name
	 * @return bool True if an attribute exists, even if valueless
	 */
	public function has( string $name ): bool {
		return isset( $this->args[$name] );
	}

	/**
	 * @param string $name
	 * @param int|null $max
	 * @return int|null Null when missing or invalid
	 */
	public function getInt( string $name, ?int $max = null ): ?int {
		$value = $this->getString( $name, '/^-?\d+$/' );
		if ( $value !== null ) {
			$value = intval( $value );
			if ( $max && abs( $value ) > $max ) {
				$this->status->fatal( 'kartographer-error-bad_attr', $name );
				return null;
			}
		}

		return $value;
	}

	/**
	 * @param string $name
	 * @param int $max Defaults to +/-360°
	 * @return float|null Null when missing or invalid
	 */
	public function getFloat( string $name, int $max = 360 ): ?float {
		$value = $this->getString( $name, '/^-?\d*\.?\d+$/' );
		if ( $value !== null ) {
			$value = floatval( $value );
			if ( abs( $value ) > $max ) {
				$this->status->fatal( 'kartographer-error-bad_attr', $name );
				return null;
			}
		}

		return $value;
	}

	/**
	 * Returns value of a named tag attribute with optional validation
	 *
	 * @param string $name Attribute name
	 * @param string|null $regexp Optional regular expression to validate against
	 * @return string|null Null when missing or invalid
	 */
	public function getString( string $name, ?string $regexp = null ): ?string {
		if ( !isset( $this->args[$name] ) ) {
			return null;
		}

		$value = trim( $this->args[$name] );
		if ( $regexp && !preg_match( $regexp, $value ) ) {
			$this->status->fatal( 'kartographer-error-bad_attr', $name );
			return null;
		}

		return $value;
	}

}
