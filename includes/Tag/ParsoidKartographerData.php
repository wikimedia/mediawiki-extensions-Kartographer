<?php

namespace Kartographer\Tag;

use stdClass;

/**
 * Data container class to avoid keeping a state in ParsoidTagHandler (which is incompatible with
 * SiteConfig::getExtTagImpl cache - this object can be passed around instead and be limited to a single
 * invocation of the extension.
 * @license MIT
 */
class ParsoidKartographerData {
	public MapTagArgumentValidator $args;
	public array $geometries = [];
	public ?stdClass $markerProperties = null;
}
