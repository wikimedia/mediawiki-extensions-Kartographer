<?php

namespace Kartographer\Tag;

use Config;
use FormatJson;
use Kartographer\Special\SpecialMap;
use MediaWiki\MainConfigNames;

/**
 * @license MIT
 */
class MapFrameAttributeGenerator {
	private const ALIGN_CLASSES = [
		'left' => 'floatleft',
		'center' => 'center',
		'right' => 'floatright',
	];
	public const THUMB_ALIGN_CLASSES = [
		'left' => 'tleft',
		'center' => 'tnone center',
		'right' => 'tright',
		'none' => 'tnone',
	];

	/** @var MapTagArgumentValidator */
	private MapTagArgumentValidator $args;
	/** @var Config */
	private Config $config;
	/** @var string */
	public string $cssWidth;

	/**
	 * @param MapTagArgumentValidator $args
	 * @param Config $config
	 */
	public function __construct( MapTagArgumentValidator $args, Config $config ) {
		$this->args = $args;
		$this->config = $config;

		if ( $this->args->width === 'full' ) {
			$this->cssWidth = '100%';
		} else {
			$this->cssWidth = $this->args->width . 'px';
		}
	}

	/**
	 * @return string[]
	 */
	public function getContainerClasses(): array {
		$classes = [ 'mw-kartographer-container' ];
		if ( $this->args->width === 'full' ) {
			$classes[] = 'mw-kartographer-full';
		}
		return $classes;
	}

	/**
	 * @return array
	 */
	public function prepareAttrs(): array {
		$attrs = [
			'class' => [ 'mw-kartographer-map' ],
			// We need dimensions for when there is no img (editpreview or no staticmap)
			// because an <img> element with permanent failing src has either:
			// - intrinsic dimensions of 0x0, when alt=''
			// - intrinsic dimensions of alt size
			'style' => "width: $this->cssWidth; height: {$this->args->height}px;",
			// Attributes starting with "data-mw" are banned from user content in Sanitizer;
			// we add such an attribute here (by default empty) so that its presence can be
			// checked later to guarantee that they were generated by Kartographer
			// Warning: This attribute is also checked in Wikibase and should be modified there as
			// well if necessary!
			'data-mw-kartographer' => 'mapframe',
			'data-style' => $this->args->mapStyle,
			'data-width' => $this->args->width,
			'data-height' => $this->args->height,
		];

		if ( $this->args->zoom !== null ) {
			$attrs['data-zoom'] = $this->args->zoom;
		}

		if ( $this->args->lat !== null && $this->args->lon !== null ) {
			$attrs['data-lat'] = $this->args->lat;
			$attrs['data-lon'] = $this->args->lon;
		}

		if ( $this->args->specifiedLangCode !== null ) {
			$attrs['data-lang'] = $this->args->specifiedLangCode;
		}

		if ( $this->args->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $this->args->showGroups, false,
				FormatJson::ALL_OK );
		}

		$attrs['href'] = SpecialMap::link( $this->args->lat, $this->args->lon, $this->args->zoom,
			$this->args->resolvedLangCode );

		if ( $this->args->frameless ) {
			array_push( $attrs['class'], ...$this->getContainerClasses() );
			if ( isset( self::ALIGN_CLASSES[$this->args->align] ) ) {
				$attrs['class'][] = self::ALIGN_CLASSES[$this->args->align];
			}
		}
		return $attrs;
	}

	/**
	 * @param bool $isPreview
	 * @param string $pagetitle
	 * @param ?int $revisionId
	 * @return array
	 */
	public function prepareImgAttrs( bool $isPreview, string $pagetitle, ?int $revisionId ): array {
		$mapServer = $this->config->get( 'KartographerMapServer' );
		$staticWidth = $this->args->width === 'full' ? 800 : (int)$this->args->width;

		$imgUrlParams = [
			'lang' => $this->args->resolvedLangCode,
		];
		if ( $this->args->showGroups && !$isPreview ) {
			// Groups are not available to the static map renderer
			// before the page was saved, can only be applied via JS
			$imgUrlParams += [
				'domain' => $this->config->get( 'KartographerMediaWikiInternalUrl' ) ??
					$this->config->get( MainConfigNames::ServerName ),
				'title' => $pagetitle,
				'revid' => $revisionId,
				'groups' => implode( ',', $this->args->showGroups ),
			];
		}

		$imgUrl = "$mapServer/img/{$this->args->mapStyle}," .
			( $this->args->zoom ?? 'a' ) . ',' .
			( $this->args->lat ?? 'a' ) . ',' .
			( $this->args->lon ?? 'a' ) .
			",{$staticWidth}x{$this->args->height}";

		$imgAttrs = [
			'src' => "$imgUrl.png?" . wfArrayToCgi( $imgUrlParams ),
			'width' => $staticWidth,
			'height' => $this->args->height,
			'decoding' => 'async',
		];

		$srcSet = $this->getSrcSet( $imgUrl, $imgUrlParams );
		if ( $srcSet ) {
			$imgAttrs['srcset'] = $srcSet;
		}

		return $imgAttrs;
	}

	private function getSrcSet( string $imgUrl, array $imgUrlParams = [] ): ?string {
		$scales = $this->config->get( 'KartographerSrcsetScales' );
		if ( !$scales || !$this->config->get( MainConfigNames::ResponsiveImages ) ) {
			return null;
		}

		// For now only support 2x, not 1.5. Saves some bytes...
		$scales = array_intersect( $scales, [ 2 ] );
		$srcSets = [];
		foreach ( $scales as $scale ) {
			$scaledImgUrl = "$imgUrl@{$scale}x.png?" . wfArrayToCgi( $imgUrlParams );
			$srcSets[] = "$scaledImgUrl {$scale}x";
		}
		return implode( ', ', $srcSets ) ?: null;
	}
}
