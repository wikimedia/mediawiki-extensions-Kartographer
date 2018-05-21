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
	private $maplinks = 0;
	private $mapframes = 0;
	private $interactiveGroups = [];
	private $requestedGroups = [];
	private $counters;
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
	 * Retrieves an instance of self from ParserOutput, initializing it anew if not present
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

	public function useMaplink() {
		$this->maplinks++;
	}

	/**
	 * @return int
	 */
	public function getMaplinks() {
		return $this->maplinks;
	}

	public function useMapframe() {
		$this->mapframes++;
	}

	/**
	 * @return int
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
	 * @return int[]
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
		$this->data = $this->data ?: new stdClass();
		if ( property_exists( $this->data, $key ) ) {
			$this->data->$key = array_merge( $this->data->$key, $data );
		} else {
			$this->data->$key = $data;
		}
	}

	/**
	 * @return stdClass|array
	 */
	public function getData() {
		return $this->data;
	}
}
