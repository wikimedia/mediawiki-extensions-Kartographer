<?php

namespace Kartographer\Tag;

use Status;

/**
 * Generic handler to validate and preprocess the arguments of an XML-style parser tag. This class
 * doesn't know anything about the arguments and what they mean.
 *
 * @license MIT
 */
class Tag {

	/** @var string */
	public string $name;
	/** @var string[] */
	private array $args;
	/** @var Status */
	public Status $status;

	/**
	 * @param string $name
	 * @param string[] $args
	 * @param Status $status
	 */
	public function __construct( string $name, array $args, Status $status ) {
		$this->name = $name;
		$this->args = $args;
		$this->status = $status;
	}

	/**
	 * @param string $name
	 * @param string|false|null $default
	 * @return int|null
	 */
	public function getInt( string $name, $default ): ?int {
		$value = $this->getString( $name, $default, '/^-?[0-9]+$/' );
		if ( $value !== null ) {
			$value = intval( $value );
		}

		return $value;
	}

	/**
	 * @param string $name
	 * @return float|null
	 */
	public function getFloat( string $name ): ?float {
		$value = $this->getString( $name, null, '/^-?[0-9]*\.?[0-9]+$/' );
		if ( $value !== null ) {
			$value = floatval( $value );
		}

		return $value;
	}

	/**
	 * Returns value of a named tag attribute with optional validation
	 *
	 * @param string $name Attribute name
	 * @param string|false|null $default Default value or false to trigger error if absent
	 * @param string|false $regexp Regular expression to validate against or false to not validate
	 * @return string|null
	 */
	public function getString( string $name, $default, $regexp = false ): ?string {
		if ( !isset( $this->args[$name] ) ) {
			if ( $default === false ) {
				$this->status->fatal( 'kartographer-error-missing-attr', $name );
			}
			return $default === false ? null : $default;
		}
		$value = trim( $this->args[$name] );
		if ( $regexp && !preg_match( $regexp, $value ) ) {
			$value = null;
			$this->status->fatal( 'kartographer-error-bad_attr', $name );
		}

		return $value;
	}

}
