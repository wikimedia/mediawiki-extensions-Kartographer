<?php

namespace Kartographer;

use ParserOutput;
use stdClass;

/**
 * Stores information about map tags on page in ParserOutput
 */
class State {
	const DATA_KEY = 'kartographer';
	const VERSION = 1;

	/** @var int Version of this class, for checking after deserialization */
	private /** @noinspection PhpUnusedPrivateFieldInspection */ $version = self::VERSION;

	private $valid = false;
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
		return $output->getExtensionData( self::DATA_KEY );
	}

	/**
	 * Retrieves an instance of self from ParserOutput.
	 * Creates a new instances and saves it into the ParserOutput, if needed.
	 *
	 * @param ParserOutput $output
	 * @return State
	 */
	public static function getOrCreate( ParserOutput $output ) {
		$result = self::getState( $output );
		if ( !$result ) {
			$result = new self;
			$output->setExtensionData( self::DATA_KEY, $result );
		}

		return $result;
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
	 * @param stdClass $counters
	 */
	public function setCounters( stdClass $counters ) {
		$this->counters = $counters;
	}

	/**
	 * @param string $key
	 * @param array $data
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
}
