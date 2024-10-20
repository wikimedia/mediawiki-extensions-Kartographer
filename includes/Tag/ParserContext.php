<?php

namespace Kartographer\Tag;

use MediaWiki\Language\Language;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\TitleFormatter;

/**
 * Meant to encapsulate all relevant incoming (!) context that's historically attached to the legacy
 * {@see Parser} class. This is very similar to Parsoid's "view of {@see ParserOptions}", see
 * {@see \MediaWiki\Parser\Parsoid\Config\PageConfig}.
 *
 * @license MIT
 */
class ParserContext {

	private Parser $parser;
	private TitleFormatter $titleFormatter;

	public function __construct( Parser $parser, TitleFormatter $titleFormatter ) {
		$this->parser = $parser;
		$this->titleFormatter = $titleFormatter;
	}

	/**
	 * @see \MediaWiki\Parser\Parsoid\Config\PageConfig::getLinkTarget
	 */
	public function getPrefixedDBkey(): string {
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable That's hard deprecated anyway
		return $this->titleFormatter->getPrefixedDBkey( $this->parser->getPage() );
	}

	/**
	 * @see \MediaWiki\Parser\Parsoid\Config\PageConfig::getRevisionId
	 * @return int|null Can be null during preview
	 */
	public function getRevisionId(): ?int {
		return $this->parser->getRevisionId();
	}

	public function getTargetLanguage(): Language {
		// Can only be StubUserLang on special pages, but these can't contain <map…> tags
		return $this->parser->getTargetLanguage();
	}

}
