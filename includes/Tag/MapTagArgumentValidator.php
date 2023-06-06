<?php

namespace Kartographer\Tag;

use Config;
use Language;
use MediaWiki\Languages\LanguageNameUtils;
use StatusValue;

/**
 * Validator and preprocessor for all arguments that can be used in <mapframe> and <maplink> tags.
 * As of now this class intentionally knows everything about both tags.
 *
 * @license MIT
 */
class MapTagArgumentValidator {

	/** @var StatusValue */
	public StatusValue $status;
	/** @var Tag */
	private Tag $args;
	/** @var Config */
	private Config $config;
	/** @var Language */
	private Language $language;
	/** @var LanguageNameUtils|null */
	private ?LanguageNameUtils $languageNameUtils;

	/** @var float|null */
	public ?float $lat;
	/** @var float|null */
	public ?float $lon;
	/** @var int|null Typically a number from 0 to 19 */
	public ?int $zoom;
	/** @var string One of "osm-intl" or "osm" */
	public string $mapStyle;
	/** @var string|null Number of pixels (without a unit) or "full" */
	public ?string $width = null;
	/** @var int|null */
	public ?int $height;
	/** @var string One of "left", "center", "right", or "none" */
	public string $align;
	/** @var bool */
	public bool $frameless;
	/** @var string */
	public string $cssClass;
	/** @var string|null */
	public ?string $specifiedLangCode;
	/** @var string */
	public string $resolvedLangCode;
	/** @var string|null */
	public ?string $text;

	/**
	 * @var string|null Currently parsed group identifier from the group="…" attribute. Only allowed
	 *  in …WikivoyageMode. Otherwise a private, auto-generated identifier starting with "_".
	 */
	public ?string $groupId = null;
	/** @var string[] List of group identifiers to show */
	public array $showGroups = [];

	/**
	 * @param string $tag
	 * @param string[] $args
	 * @param Config $config
	 * @param Language $language
	 * @param LanguageNameUtils|null $languageNameUtils
	 */
	public function __construct(
		string $tag,
		array $args,
		Config $config,
		Language $language,
		LanguageNameUtils $languageNameUtils = null
	) {
		$this->status = StatusValue::newGood();
		$this->args = new Tag( $tag, $args, $this->status );
		$this->config = $config;
		$this->language = $language;
		$this->languageNameUtils = $languageNameUtils;

		$this->parseArgs();
		if ( $config->get( 'KartographerWikivoyageMode' ) ) {
			$this->parseGroups();
		}
	}

	private function parseArgs(): void {
		// Required arguments
		if ( $this->args->name === LegacyMapFrame::TAG ) {
			foreach ( [ 'width', 'height' ] as $required ) {
				if ( !$this->args->has( $required ) ) {
					$this->status->fatal( 'kartographer-error-missing-attr', $required );
				}
			}

			// @todo: should these have defaults?
			$this->width = $this->args->getString( 'width', '/^(\d+|([1-9]\d?|100)%|full)$/' );
			$this->height = $this->args->getInt( 'height' );

			// @todo: deprecate old syntax completely
			if ( $this->width && str_ends_with( $this->width, '%' ) ) {
				$this->width = $this->width === '100%' ? 'full' : '300';
			}
		}

		// Arguments valid for both <mapframe> and <maplink>
		$this->lat = $this->args->getFloat( 'latitude' );
		$this->lon = $this->args->getFloat( 'longitude' );
		if ( $this->status->isOK() && ( ( $this->lat === null ) xor ( $this->lon === null ) ) ) {
			$this->lat = null;
			$this->lon = null;
			$this->status->fatal( 'kartographer-error-latlon' );
		}

		$this->zoom = $this->args->getInt( 'zoom' );
		$regexp = '/^(' . implode( '|', $this->config->get( 'KartographerStyles' ) ) . ')$/';
		$defaultStyle = $this->config->get( 'KartographerDfltStyle' );
		$this->mapStyle = $this->args->getString( 'mapstyle', $regexp ) ?? $defaultStyle;
		$this->text = $this->args->getString( 'text' );

		$defaultLangCode = $this->config->get( 'KartographerUsePageLanguage' ) ?
			$this->language->getCode() :
			'local';
		// Language code specified by the user (null if none)
		$this->specifiedLangCode = $this->args->getString( 'lang' );
		// Language code we're going to use
		$this->resolvedLangCode = $this->specifiedLangCode ?? $defaultLangCode;
		// If the specified language code is invalid, behave as if no language was specified
		if ( $this->languageNameUtils &&
			$this->resolvedLangCode !== 'local' &&
			!$this->languageNameUtils->isKnownLanguageTag( $this->resolvedLangCode )
		) {
			$this->specifiedLangCode = null;
			$this->resolvedLangCode = $defaultLangCode;
		}

		// Arguments valid only for one of the two tags, but all optional anyway
		if ( $this->width === 'full' ) {
			$this->align = 'none';
		} elseif ( $this->width !== null ) {
			$defaultAlign = $this->language->alignEnd();
			$this->align = $this->args->getString( 'align', '/^(left|center|right)$/' ) ?? $defaultAlign;
		}
		$this->frameless = ( $this->text === null || $this->text === '' ) &&
			$this->args->getString( 'frameless' ) !== null;
		$this->cssClass = $this->args->getString( 'class', '/^(|[a-zA-Z][-_a-zA-Z0-9]*)$/' ) ?? '';
	}

	private function parseGroups(): void {
		$this->groupId = $this->args->getString( 'group', '/^(\w| )+$/u' );

		$show = $this->args->getString( 'show', '/^(|(\w| )+(\s*,\s*(\w| )+)*)$/u' );
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

	/**
	 * @return bool
	 */
	public function usesAutoPosition(): bool {
		return $this->zoom === null || $this->lat === null || $this->lon === null;
	}

}
