<?php

namespace Kartographer;

use JsonSerializable;
use MediaWiki\Parser\ParserOutput;
use UnexpectedValueException;
use Wikimedia\Parsoid\Core\ContentMetadataCollector;

/**
 * Stores information about map tags on page in ParserOutput
 *
 * @license MIT
 */
class State implements JsonSerializable {

	public const DATA_KEY = 'kartographer';

	/** @var int Total number of invalid <map…> tags on the page */
	private int $broken = 0;

	/**
	 * @var array<string,int> Total number of <maplink> and <mapframe> tags on the page, to be
	 *  stored as a page property
	 */
	private array $usages = [];

	/** @var array<string,null> Flipped set, values are meaningless */
	private array $interactiveGroups = [];
	/** @var array<string,null> Flipped set, values are meaningless */
	private array $requestedGroups = [];
	/** @var array<string,int> */
	private array $counters = [];

	/**
	 * @var array<string,array> Indexed per group identifier
	 */
	private array $data = [];

	/**
	 * Retrieves an instance of self from ParserOutput, if present
	 *
	 * @param ParserOutput $output
	 * @return self|null
	 */
	public static function getState( ParserOutput $output ): ?self {
		// $state may be null or a JSON serializable array.
		// When reading old cache entries, it may for a while still be a State object (T266260).
		$state = $output->getExtensionData( self::DATA_KEY );

		if ( is_array( $state ) ) {
			$state = self::newFromJson( $state );
		}

		return $state;
	}

	/**
	 * Retrieves an instance of self from ParserOutput if possible,
	 * otherwise creates a new instance.
	 *
	 * @param ParserOutput $output
	 * @return self
	 */
	public static function getOrCreate( ParserOutput $output ): self {
		return self::getState( $output ) ?? new self();
	}

	/**
	 * Stores an instance of self in the ParserOutput.
	 *
	 * @param ContentMetadataCollector $output
	 * @param self $state
	 */
	public static function saveState( ContentMetadataCollector $output, self $state ): void {
		$output->setExtensionData( self::DATA_KEY, $state->jsonSerialize() );
	}

	public function hasValidTags(): bool {
		return ( array_sum( $this->usages ) - $this->broken ) > 0;
	}

	public function hasBrokenTags(): bool {
		return $this->broken > 0;
	}

	public function incrementBrokenTags(): void {
		$this->broken++;
	}

	public function incrementUsage( string $tag ): void {
		if ( !str_starts_with( $tag, 'map' ) ) {
			throw new UnexpectedValueException( 'Unsupported tag name' );
		}
		// Resulting keys will be "maplinks" and "mapframes"
		$key = "{$tag}s";
		$this->usages[$key] = ( $this->usages[$key] ?? 0 ) + 1;
	}

	/**
	 * @return array<string,int>
	 */
	public function getUsages(): array {
		return $this->usages;
	}

	/**
	 * @param string[] $groupIds
	 */
	public function addInteractiveGroups( array $groupIds ): void {
		$this->interactiveGroups += array_fill_keys( $groupIds, null );
	}

	/**
	 * @return string[] Group ids, guaranteed to be unique
	 */
	public function getInteractiveGroups(): array {
		return array_keys( $this->interactiveGroups );
	}

	/**
	 * @param string[] $groupIds
	 */
	public function addRequestedGroups( array $groupIds ): void {
		$this->requestedGroups += array_fill_keys( $groupIds, null );
	}

	/**
	 * @return string[] Group ids, guaranteed to be unique
	 */
	public function getRequestedGroups(): array {
		return array_keys( $this->requestedGroups );
	}

	/**
	 * @return array<string,int>
	 */
	public function getCounters(): array {
		return $this->counters;
	}

	/**
	 * @param array<string,int> $counters A JSON-serializable structure
	 */
	public function setCounters( array $counters ): void {
		$this->counters = $counters;
	}

	/**
	 * @param string $groupId
	 * @param array $data A JSON-serializable structure
	 */
	public function addData( $groupId, array $data ): void {
		// There is no way to ever add anything to a private group starting with `_`
		if ( isset( $this->data[$groupId] ) && !str_starts_with( $groupId, '_' ) ) {
			$this->data[$groupId] = array_merge( $this->data[$groupId], $data );
		} else {
			$this->data[$groupId] = $data;
		}
	}

	/**
	 * @return array<string,array> Associative key-value array, build up by {@see addData}
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * @return array A JSON serializable associative array
	 */
	public function jsonSerialize(): array {
		// TODO: Replace with the ...$this->usages syntax when we can use PHP 8.1
		return array_merge( [
			'broken' => $this->broken,
			'interactiveGroups' => $this->getInteractiveGroups(),
			'requestedGroups' => $this->getRequestedGroups(),
			'counters' => $this->counters ?: null,
			'data' => $this->data,
		], $this->usages );
	}

	/**
	 * @param array $data A JSON serializable associative array, as returned by jsonSerialize()
	 *
	 * @return self
	 */
	private static function newFromJson( array $data ): self {
		$status = new self();
		$status->broken = (int)( $data['broken'] ?? 0 );
		$status->usages = array_filter( $data, static function ( $count, $key ) {
			return is_int( $count ) && $count > 0 && str_starts_with( $key, 'map' );
		}, ARRAY_FILTER_USE_BOTH );

		// TODO: Backwards compatibility, can be removed 30 days later
		if ( !array_is_list( $data['interactiveGroups'] ?? [] ) ) {
			$data['interactiveGroups'] = array_keys( $data['interactiveGroups'] );
		}
		if ( !array_is_list( $data['requestedGroups'] ?? [] ) ) {
			$data['requestedGroups'] = array_keys( $data['requestedGroups'] );
		}

		$status->addInteractiveGroups( $data['interactiveGroups'] ?? [] );
		$status->addRequestedGroups( $data['requestedGroups'] ?? [] );
		$status->counters = $data['counters'] ?? [];
		$status->data = $data['data'] ?? [];

		return $status;
	}

}
