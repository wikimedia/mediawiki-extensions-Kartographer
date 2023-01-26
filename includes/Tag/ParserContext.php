<?php

namespace Kartographer\Tag;

use Language;
use MediaWiki\Page\PageReference;
use Parser;

/**
 * @license MIT
 */
class ParserContext {

	private Parser $parser;

	public function __construct( Parser $parser ) {
		$this->parser = $parser;
	}

	public function getPage(): ?PageReference {
		return $this->parser->getPage();
	}

	public function getRevisionId(): ?int {
		return $this->parser->getRevisionId();
	}

	public function getTargetLanguage(): Language {
		// Can only be StubUserLang on special pages, but these can't contain <map…> tags
		return $this->parser->getTargetLanguage();
	}

}
