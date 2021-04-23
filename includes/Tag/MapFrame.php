<?php

namespace Kartographer\Tag;

use FormatJson;
use Html;
use Kartographer\SpecialMap;

/**
 * The <mapframe> tag inserts a map into wiki page
 */
class MapFrame extends TagHandler {

	private const ALIGN_CLASSES = [
		'left' => 'floatleft',
		'center' => 'center',
		'right' => 'floatright',
		'none' => '',
	];
	private const THUMB_ALIGN_CLASSES = [
		'left' => 'tleft',
		'center' => 'tnone center',
		'right' => 'tright',
		'none' => 'tnone',
	];

	/** @inheritDoc */
	protected $tag = 'mapframe';

	/** @var int|string either a number of pixels, a percentage (e.g. "100%"), or "full" */
	private $width;
	/** @var int */
	private $height;
	/** @var string One of "left", "center", "right", or "none" */
	private $align;

	/**
	 * @inheritDoc
	 */
	protected function parseArgs() {
		parent::parseArgs();
		$this->state->useMapframe();
		// @todo: should these have defaults?
		$this->width = $this->getText( 'width', false, '/^(\d+|([1-9]\d?|100)%|full)$/' );
		$this->height = $this->getInt( 'height' );
		$defaultAlign = $this->getLanguage()->isRTL() ? 'left' : 'right';
		$this->align = $this->getText( 'align', $defaultAlign, '/^(left|center|right)$/' );
	}

	/**
	 * @return string
	 */
	protected function render() {
		global $wgKartographerMapServer,
			$wgResponsiveImages,
			$wgServerName,
			$wgKartographerSrcsetScales,
			$wgKartographerStaticMapframe;

		$caption = $this->getText( 'text', null );
		$framed = $caption !== null || $this->getText( 'frameless', null ) === null;

		$output = $this->parser->getOutput();
		$options = $this->parser->getOptions();

		$width = is_numeric( $this->width ) ? "{$this->width}px" : $this->width;
		$fullWidth = false;
		if ( preg_match( '/^\d+%$/', $width ) ) {
			if ( $width === '100%' ) {
				$fullWidth = true;
				$staticWidth = 800;
				$this->align = 'none';
			} else {
				$width = '300px'; // @todo: deprecate old syntax completely
				$this->width = 300;
				$staticWidth = 300;
			}
		} elseif ( $width === 'full' ) {
			$width = '100%';
			$this->align = 'none';
			$fullWidth = true;
			$staticWidth = 800;
		} else {
			$staticWidth = $this->width;
		}
		// TODO if fullwidth, we really should use interactive mode..
		// BUT not possible to use both modes at the same time right now. T248023
		// Should be fixed, especially considering VE in page editing etc...

		$useSnapshot =
			$wgKartographerStaticMapframe && !$options->getIsPreview() &&
			!$options->getIsSectionPreview();

		$output->addModules( $useSnapshot
			? 'ext.kartographer.staticframe'
			: 'ext.kartographer.frame' );

		$attrs = [
			'class' => 'mw-kartographer-map',
			// We need dimensions for when there is no img (editpreview or no staticmap)
			// because an <img> element with permanent failing src has either:
			// - intrinsic dimensions of 0x0, when alt=''
			// - intrinsic dimensions of alt size
			'style' => "width: {$width}; height: {$this->height}px;",
			'data-mw' => 'interface',
			'data-style' => $this->mapStyle,
			'data-width' => $this->width,
			'data-height' => $this->height,
		];
		if ( $this->zoom !== null ) {
			$staticZoom = $this->zoom;
			$attrs['data-zoom'] = $this->zoom;
		} else {
			$staticZoom = 'a';
		}

		if ( $this->lat !== null && $this->lon !== null ) {
			$attrs['data-lat'] = $this->lat;
			$attrs['data-lon'] = $this->lon;
			$staticLat = $this->lat;
			$staticLon = $this->lon;
		} else {
			$staticLat = 'a';
			$staticLon = 'a';
		}

		if ( $this->specifiedLangCode !== null ) {
			$attrs['data-lang'] = $this->specifiedLangCode;
		}

		if ( $this->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $this->showGroups, false,
				FormatJson::ALL_OK );
			$this->state->addInteractiveGroups( $this->showGroups );
		}

		$containerClass = 'mw-kartographer-container';
		if ( $fullWidth ) {
			$containerClass .= ' mw-kartographer-full';
		}

		$attrs['href'] = SpecialMap::link( $staticLat, $staticLon, $staticZoom, $this->resolvedLangCode )
			->getLocalURL();
		$imgUrlParams = [
			'lang' => $this->resolvedLangCode,
		];
		if ( $this->showGroups ) {
			$imgUrlParams += [
				'domain' => $wgServerName,
				'title' => $this->parser->getTitle()->getPrefixedText(),
				'groups' => implode( ',', $this->showGroups ),
			];
		}
		$imgUrl = "{$wgKartographerMapServer}/img/{$this->mapStyle},{$staticZoom},{$staticLat}," .
		"{$staticLon},{$staticWidth}x{$this->height}.png";
		$imgUrl .= '?' . wfArrayToCgi( $imgUrlParams );
		$imgAttrs = [
			'src' => $imgUrl,
			'alt' => '',
			'width' => (int)$staticWidth,
			'height' => (int)$this->height,
			'decoding' => 'async'
		];

		if ( $wgResponsiveImages && $wgKartographerSrcsetScales ) {
			// For now only support 2x, not 1.5. Saves some bytes...
			$srcSetScales = array_intersect( $wgKartographerSrcsetScales, [ 2 ] );
			$srcSets = [];
			foreach ( $srcSetScales as $srcSetScale ) {
				$scaledImgUrl = "{$wgKartographerMapServer}/img/{$this->mapStyle},{$staticZoom},{$staticLat}," .
				"{$staticLon},{$staticWidth}x{$this->height}@{$srcSetScale}x.png";
				$scaledImgUrl .= '?' . wfArrayToCgi( $imgUrlParams );
				$srcSets[] = "{$scaledImgUrl} {$srcSetScale}x";
			}
			$imgAttrs[ 'srcset' ] = implode( ', ', $srcSets );
		}

		if ( !$framed ) {
			$attrs['class'] .= ' ' . $containerClass . ' ' . self::ALIGN_CLASSES[$this->align];
			return Html::rawElement( 'a', $attrs, Html::rawElement( 'img', $imgAttrs ) );
		}

		$containerClass .= ' thumb ' . self::THUMB_ALIGN_CLASSES[$this->align];

		$captionFrame = Html::rawElement( 'div', [ 'class' => 'thumbcaption' ],
			$caption ? $this->parser->recursiveTagParse( $caption ) : '' );

		$mapDiv = Html::rawElement( 'a', $attrs, Html::rawElement( 'img', $imgAttrs ) );

		return Html::rawElement( 'div', [ 'class' => $containerClass ],
			Html::rawElement( 'div', [
					'class' => 'thumbinner',
					'style' => "width: {$width};",
				], $mapDiv . $captionFrame ) );
	}
}
