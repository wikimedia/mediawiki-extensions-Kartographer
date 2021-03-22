<?php

namespace Kartographer\Tests\Mock;

use Kartographer\SimpleStyleParser;
use Status;

class MockSimpleStyleParser extends SimpleStyleParser {

	/** @inheritDoc */
	protected function sanitize( &$json ) {
	}

	/** @inheritDoc */
	protected function normalize( &$json ) {
		return Status::newGood( $json );
	}
}
