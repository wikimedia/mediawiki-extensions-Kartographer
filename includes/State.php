<?php

namespace Kartographer;

use JsonSerializable;
use ParserOutput;

/**
 * Stores information about map tags on page in ParserOutput
 */
class State implements JsonSerializable {

	public const DATA_KEY = 'kartographer';

	/** @var bool If the page contains at least one valid <map…> tag */
	private $valid = false;
	/** @var bool If the page contains one or more invalid <map…> tags */
	private $broken = false;

	/**
	 * @var int Total number of <maplink> tags on the page, to be stored as a page property
	 */
	private $maplinks = 0;

	/**
	 * @var int Total number of <mapframe> tags on the page, to be stored as a page property
	 */
	private $mapframes = 0;

	/**
	 * @var int[]
	 */
	private $interactiveGroups = [];

	/**
	 * @var int[]
	 */
	private $requestedGroups = [];

	/**
	 * @var int[]|null
	 */
	private $counters;

	/**
	 * @var array[] Indexed per group identifier
	 */
	private $data = [];

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
		$result = self::getState( $output );
		if ( !$result ) {
			$result = new self;
		}

		return $result;
	}

	/**
	 * Stores an instance of self in the ParserOutput.
	 *
	 * @param ParserOutput $output
	 * @param self $state
	 */
	public static function setState( ParserOutput $output, self $state ) {
		$output->setExtensionData( self::DATA_KEY, $state->jsonSerialize() );
	}

	/**
	 * @return bool
	 */
	public function hasValidTags(): bool {
		return $this->valid;
	}

	public function setValidTags() {
		$this->valid = true;
	}

	/**
	 * @return bool
	 */
	public function hasBrokenTags(): bool {
		return $this->broken;
	}

	public function setBrokenTags() {
		$this->broken = true;
	}

	/**
	 * Increment the number of maplinks by one.
	 */
	public function useMaplink() {
		$this->maplinks++;
	}

	/**
	 * @return int Number of maplinks.
	 */
	public function getMaplinks(): int {
		return $this->maplinks;
	}

	/**
	 * Increment the number of mapframes by one.
	 */
	public function useMapframe() {
		$this->mapframes++;
	}

	/**
	 * @return int Number of mapframes.
	 */
	public function getMapframes(): int {
		return $this->mapframes;
	}

	/**
	 * @param string[] $groupIds
	 */
	public function addInteractiveGroups( array $groupIds ) {
		$this->interactiveGroups += array_flip( $groupIds );
	}

	/**
	 * @return string[]
	 */
	public function getInteractiveGroups(): array {
		return array_keys( $this->interactiveGroups );
	}

	/**
	 * @param string[] $groupIds
	 */
	public function addRequestedGroups( array $groupIds ) {
		$this->requestedGroups += array_flip( $groupIds );
	}

	/**
	 * @return int[] Group id => original index map (flipped version of addRequestedGroups)
	 */
	public function getRequestedGroups(): array {
		return $this->requestedGroups;
	}

	/**
	 * @return int[]
	 */
	public function getCounters(): array {
		return $this->counters ?: [];
	}

	/**
	 * @param int[] $counters A JSON-serializable structure
	 */
	public function setCounters( array $counters ) {
		$this->counters = $counters;
	}

	/**
	 * @param string $groupId
	 * @param array $data A JSON-serializable structure
	 */
	public function addData( $groupId, array $data ) {
		// There is no way to ever add anything to a private group starting with `_`
		if ( array_key_exists( $groupId, $this->data ) && $groupId[0] !== '_' ) {
			$this->data[$groupId] = array_merge( $this->data[$groupId], $data );
		} else {
			$this->data[$groupId] = $data;
		}
	}

	/**
	 * @return array[] Associative key-value array, build up by {@see addData}
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * @return array A JSON serializable associative array
	 */
	public function jsonSerialize() {
		return [
			'valid' => $this->valid,
			'broken' => $this->broken,
			'maplinks' => $this->maplinks,
			'mapframes' => $this->mapframes,
			'interactiveGroups' => $this->interactiveGroups,
			'requestedGroups' => $this->requestedGroups,
			'counters' => $this->counters,
			'data' => $this->data,
		];
	}

	/**
	 * @param array $data A JSON serializable associative array, as returned by jsonSerialize()
	 *
	 * @return self
	 */
	public static function newFromJson( array $data ): self {
		$status = new self();
		$status->valid = $data['valid'];
		$status->broken = $data['broken'];
		$status->maplinks = $data['maplinks'];
		$status->mapframes = $data['mapframes'];
		$status->interactiveGroups = $data['interactiveGroups'];
		$status->requestedGroups = $data['requestedGroups'];
		$status->counters = $data['counters'];
		$status->data = $data['data'];

		return $status;
	}

}
