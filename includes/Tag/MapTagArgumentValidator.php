<?php

namespace Kartographer\Tag;

use Config;
use Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MediaWikiServices;
use Status;

/**
 * Validator and preprocessor for all arguments that can be used in <mapframe> and <maplink> tags.
 * As of now this class intentionally knows everything about both tags.
 *
 * @license MIT
 */
class MapTagArgumentValidator {

	/** @var Status */
	public $status;
	/** @var Tag */
	private $args;
	/** @var Config */
	private $config;
	/** @var Language */
	private Language $language;
	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var float|null */
	public $lat;
	/** @var float|null */
	public $lon;
	/** @var int|null */
	public $zoom;
	/** @var string|null One of "osm-intl" or "osm" */
	public $mapStyle;
	/** @var string|null either a number of pixels, a percentage (e.g. "100%"), or "full" */
	public $width;
	/** @var int|null */
	public $height;
	/** @var string|null One of "left", "center", "right", or "none" */
	public $align;
	/** @var string|null */
	public $frameless;
	/** @var string|null */
	public $cssClass;
	/** @var string|null */
	public $specifiedLangCode;
	/** @var string */
	public $resolvedLangCode;
	/** @var string|null */
	public $text;

	/**
	 * @var string|null Currently parsed group identifier from the group="…" attribute. Only allowed
	 *  in …WikivoyageMode. Otherwise a private, auto-generated identifier starting with "_".
	 */
	public $groupId;
	/** @var string[] List of group identifiers to show */
	public $showGroups = [];

	/**
	 * @param string $tag
	 * @param string[] $args
	 * @param Config $config
	 * @param Language $language
	 */
	public function __construct(
		string $tag,
		array $args,
		Config $config,
		Language $language
	) {
		$this->status = Status::newGood();
		$this->args = new Tag( $tag, $args, $this->status );
		$this->config = $config;
		$this->language = $language;
		// TODO: Inject
		$this->languageNameUtils = MediaWikiServices::getInstance()->getLanguageNameUtils();

		$this->parseArgs();
		if ( $config->get( 'KartographerWikivoyageMode' ) ) {
			$this->parseGroups();
		}
	}

	private function parseArgs(): void {
		// Required arguments
		if ( $this->args->name === LegacyMapFrame::TAG ) {
			// @todo: should these have defaults?
			$this->width = $this->args->getString( 'width', false, '/^(\d+|([1-9]\d?|100)%|full)$/' );
			$this->height = $this->args->getInt( 'height', false );
		}

		// Arguments valid for both <mapframe> and <maplink>
		$this->lat = $this->args->getFloat( 'latitude' );
		$this->lon = $this->args->getFloat( 'longitude' );
		if ( $this->status->isOK() && ( ( $this->lat === null ) xor ( $this->lon === null ) ) ) {
			$this->status->fatal( 'kartographer-error-latlon' );
		}

		$this->zoom = $this->args->getInt( 'zoom', null );
		$regexp = '/^(' . implode( '|', $this->config->get( 'KartographerStyles' ) ) . ')$/';
		$this->mapStyle = $this->args->getString( 'mapstyle', $this->config->get( 'KartographerDfltStyle' ), $regexp );
		$this->text = $this->args->getString( 'text', null );

		$defaultLangCode = $this->config->get( 'KartographerUsePageLanguage' ) ?
			$this->language->getCode() :
			'local';
		// Language code specified by the user (null if none)
		$this->specifiedLangCode = $this->args->getString( 'lang', null );
		// Language code we're going to use
		$this->resolvedLangCode = $this->specifiedLangCode ?? $defaultLangCode;
		// If the specified language code is invalid, behave as if no language was specified
		if (
			!$this->languageNameUtils->isKnownLanguageTag( $this->resolvedLangCode ) &&
			$this->resolvedLangCode !== 'local'
		) {
			$this->specifiedLangCode = null;
			$this->resolvedLangCode = $defaultLangCode;
		}

		// Arguments valid only for one of the two tags, but all optional anyway
		$defaultAlign = $this->language->alignEnd();
		$this->align = $this->args->getString( 'align', $defaultAlign, '/^(left|center|right)$/' );
		$this->frameless = $this->args->getString( 'frameless', null );
		$this->cssClass = $this->args->getString( 'class', '', '/^(|[a-zA-Z][-_a-zA-Z0-9]*)$/' );
	}

	private function parseGroups() {
		$this->groupId = $this->args->getString( 'group', null, '/^(\w| )+$/u' );

		$show = $this->args->getString( 'show', null, '/^(|(\w| )+(\s*,\s*(\w| )+)*)$/u' );
		if ( $show ) {
			$this->showGroups = array_map( 'trim', explode( ',', $show ) );
		}

		// Make sure the current group is shown for this map, even if there is no geojson
		// Private group will be added during the save, as it requires hash calculation
		if ( $this->groupId !== null ) {
			$this->showGroups[] = $this->groupId;
		}

		// Make sure there are no group name duplicates
		$this->showGroups = array_unique( $this->showGroups );
	}

}
