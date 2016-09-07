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

	public function hasValidTags() {
		return $this->valid;
	}

	public function setValidTags() {
		$this->valid = true;
	}

	public function hasBrokenTags() {
		return $this->broken;
	}

	public function setBrokenTags() {
		$this->broken = true;
	}

	public function addInteractiveGroups( array $groups ) {
		$this->interactiveGroups += array_flip( $groups );
	}

	public function getInteractiveGroups() {
		return array_keys( $this->interactiveGroups );
	}

	public function addRequestedGroups( array $groups ) {
		$this->requestedGroups += array_flip( $groups );
	}

	public function getRequestedGroups() {
		return $this->requestedGroups;
	}

	public function getCounters() {
		return $this->counters ?: new stdClass();
	}

	public function setCounters( stdClass $counters ) {
		$this->counters = $counters;
	}

	public function addData( $key, $data ) {
		$this->data = $this->data ?: new stdClass();
		if ( property_exists( $this->data, $key ) ) {
			$this->data->$key = array_merge( $this->data->$key, $data );
		} else {
			$this->data->$key = $data;
		}
	}

	public function getData() {
		return $this->data;
	}
}
