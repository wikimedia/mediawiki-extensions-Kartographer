<?php

namespace Kartographer\Tag;

use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use StatusValue;
use stdClass;

/**
 * Validator and preprocessor for all arguments that can be used in <mapframe> and <maplink> tags.
 * As of now this class intentionally knows everything about both tags.
 *
 * @license MIT
 */
class MapTagArgumentValidator {

	private Config $config;
	private Language $defaultLanguage;
	private LanguageNameUtils $languageCodeValidator;

	public StatusValue $status;
	private Tag $args;

	public ?float $lat;
	public ?float $lon;
	/** @var int|null Typically a number from 0 to 19 */
	public ?int $zoom;
	/** @var string One of "osm-intl" or "osm" */
	public string $mapStyle;
	/** @var string|null Number of pixels (without a unit) or "full" */
	public ?string $width = null;
	public ?int $height;
	/** @var string One of "left", "center", "right", or "none" */
	public string $align;
	public bool $frameless;
	public string $cssClass;
	/** @var string|null Language code as specified by the user, null if none or invalid */
	public ?string $specifiedLangCode = null;
	/** Empty string when text="" was given, null when the attribute was missing */
	public ?string $text;
	public string $alt;
	private ?string $fallbackText = null;
	public string $firstMarkerColor = '';

	/**
	 * @var string|null Currently parsed group identifier from the group="…" attribute. Only allowed
	 *  in …WikivoyageMode. Otherwise a private, auto-generated identifier starting with "_".
	 */
	public ?string $groupId = null;
	/** @var string[] List of group identifiers to show */
	public array $showGroups = [];

	/**
	 * @param string $tag Tag name, e.g. "maplink"
	 * @param array<string,string> $args
	 * @param Config $config
	 * @param Language $defaultLanguage
	 * @param LanguageNameUtils $languageCodeValidator
	 */
	public function __construct(
		string $tag,
		array $args,
		Config $config,
		Language $defaultLanguage,
		LanguageNameUtils $languageCodeValidator
	) {
		$this->config = $config;
		$this->defaultLanguage = $defaultLanguage;
		$this->languageCodeValidator = $languageCodeValidator;

		$this->status = StatusValue::newGood();
		$this->args = new Tag( $tag, $args, $this->status );

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
		$this->alt = $this->args->getString( 'alt' ) ?? '';

		$lang = $this->args->getString( 'lang' );
		// If the specified language code is invalid, behave as if no language was specified
		if ( $lang && $this->isValidLanguageCode( $lang ) ) {
			$this->specifiedLangCode = $lang;
		}

		// Arguments valid only for one of the two tags, but all optional anyway
		if ( $this->width === 'full' ) {
			$this->align = 'none';
		} elseif ( $this->width !== null ) {
			$defaultAlign = $this->defaultLanguage->alignEnd();
			$this->align = $this->args->getString( 'align', '/^(left|center|right)$/' ) ?? $defaultAlign;
		}
		$this->frameless = $this->args->getString( 'frameless' ) !== null &&
			// Can only suppress empty frames that don't contain a caption
			(string)$this->text === '';
		$this->cssClass = $this->args->getString( 'class', '/^([a-z][\w-]*)?$/i' ) ?? '';
	}

	private function parseGroups(): void {
		$this->groupId = $this->args->getString( 'group', '/^[\w ]+$/u' );

		$show = $this->args->getString( 'show', '/^([\w ]+(\s*,\s*+[\w ]+)*)?$/u' );
		if ( $show ) {
			$this->showGroups = array_map( 'trim', explode( ',', $show ) );
		}

		// Make sure the current group is shown for this map, even if there is no geojson
		// Private group will be added during the save, as it requires hash calculation
		if ( $this->groupId !== null ) {
			$this->showGroups[] = $this->groupId;
		}

		// Make sure there are no group name duplicates
		$this->showGroups = array_values( array_unique( $this->showGroups ) );
	}

	/**
	 * @return bool If a complete pair of coordinates is given, e.g. to render a <maplink> label
	 */
	public function hasCoordinates(): bool {
		return $this->lat !== null && $this->lon !== null;
	}

	/**
	 * @return bool If the map relies on Kartotherian's auto-position feature (extracting a bounding
	 *  box from the GeoJSON) instead of the arguments alone (coordinates and zoom)
	 */
	public function usesAutoPosition(): bool {
		return $this->zoom === null || !$this->hasCoordinates();
	}

	private function isValidLanguageCode( string $code ): bool {
		return $code === 'local' ||
			$this->languageCodeValidator->isKnownLanguageTag( $code );
	}

	public function getLanguageCodeWithDefaultFallback(): string {
		if ( $this->specifiedLangCode ) {
			return $this->specifiedLangCode;
		}

		if ( $this->config->get( 'KartographerUsePageLanguage' ) &&
			// T288150: Pedantic validation of the target language we just got from the parser
			$this->isValidLanguageCode( $this->defaultLanguage->getCode() )
		) {
			return $this->defaultLanguage->getCode();
		}

		return 'local';
	}

	public function setFirstMarkerProperties( ?string $fallbackText, stdClass $properties ): void {
		$this->fallbackText = $fallbackText;

		if ( $this->config->get( 'KartographerUseMarkerStyle' ) &&
			isset( $properties->{'marker-color'} ) &&
			// JsonSchema already validates this value for us, however this regex will also fail
			// if the color is invalid
			preg_match( '/^#?((?:[\da-f]{3}){1,2})$/i', $properties->{'marker-color'}, $m )
		) {
			// Simplestyle allows colors "with or without the # prefix". Enforce it here.
			$this->firstMarkerColor = '#' . $m[1];
		}
	}

	public function getTextWithFallback(): ?string {
		return $this->text ?? $this->fallbackText;
	}

}
