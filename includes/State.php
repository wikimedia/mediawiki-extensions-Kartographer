<?php

namespace Kartographer;

use JsonSerializable;
use ParserOutput;
use stdClass;

/**
 * Stores information about map tags on page in ParserOutput
 */
class State implements JsonSerializable {

	public const DATA_KEY = 'kartographer';

	/** @var bool */
	private $valid = false;
	/** @var bool */
	private $broken = false;

	/**
	 * @var int
	 */
	private $maplinks = 0;

	/**
	 * @var int
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
	 * @var stdClass|null
	 */
	private $counters;

	/**
	 * @var array[]
	 */
	private $data = [];

	/**
	 * Retrieves an instance of self from ParserOutput, if present
	 *
	 * @param ParserOutput $output
	 * @return self|null
	 */
	public static function getState( ParserOutput $output ) {
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
	 * @return State
	 */
	public static function getOrCreate( ParserOutput $output ) {
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
	 * @param State $state
	 */
	public static function setState( ParserOutput $output, State $state ) {
		$output->setExtensionData( self::DATA_KEY, $state->jsonSerialize() );
	}

	/**
	 * @return bool
	 */
	public function hasValidTags() {
		return $this->valid;
	}

	public function setValidTags() {
		$this->valid = true;
	}

	/**
	 * @return bool
	 */
	public function hasBrokenTags() {
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
	public function getMaplinks() {
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
	public function getMapframes() {
		return $this->mapframes;
	}

	/**
	 * @param string[] $groups
	 */
	public function addInteractiveGroups( array $groups ) {
		$this->interactiveGroups += array_flip( $groups );
	}

	/**
	 * @return string[]
	 */
	public function getInteractiveGroups() {
		return array_keys( $this->interactiveGroups );
	}

	/**
	 * @param string[] $groups
	 */
	public function addRequestedGroups( array $groups ) {
		$this->requestedGroups += array_flip( $groups );
	}

	/**
	 * @return int[] Group name => original index map (flipped version of addRequestedGroups)
	 */
	public function getRequestedGroups() {
		return $this->requestedGroups;
	}

	/**
	 * @return stdClass
	 */
	public function getCounters() {
		return $this->counters ?: new stdClass();
	}

	/**
	 * @param stdClass $counters A JSON-serializable structure
	 */
	public function setCounters( stdClass $counters ) {
		$this->counters = $counters;
	}

	/**
	 * @param string $key
	 * @param array $data A JSON-serializable structure
	 */
	public function addData( $key, array $data ) {
		if ( array_key_exists( $key, $this->data ) ) {
			$this->data[$key] = array_merge( $this->data[$key], $data );
		} else {
			$this->data[$key] = $data;
		}
	}

	/**
	 * @return array[] Associative key-value array, build up by {@see addData}
	 */
	public function getData() {
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
			'counters' => $this->counters !== null ? (array)$this->counters : null,
			'data' => $this->data,
		];
	}

	/**
	 * @param array $data A JSON serializable associative array, as returned by jsonSerialize()
	 *
	 * @return State
	 */
	public static function newFromJson( array $data ) {
		$status = new self();
		$status->valid = $data['valid'];
		$status->broken = $data['broken'];
		$status->maplinks = $data['maplinks'];
		$status->mapframes = $data['mapframes'];
		$status->interactiveGroups = $data['interactiveGroups'];
		$status->requestedGroups = $data['requestedGroups'];
		$status->counters = $data['counters'] !== null ? (object)$data['counters'] : null;
		$status->data = $data['data'];

		return $status;
	}

}
