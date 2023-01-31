<?php

namespace Kartographer\Tag;

use FormatJson;
use Html;
use Kartographer\SpecialMap;
use MediaWiki\MediaWikiServices;

/**
 * The <mapframe> tag inserts a map into wiki page
 *
 * @license MIT
 */
class LegacyMapFrame extends LegacyTagHandler {

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

	public const TAG = 'mapframe';

	/** @var string|null either a number of pixels, a percentage (e.g. "100%"), or "full" */
	private $width;
	/** @var int|null */
	private $height;
	/** @var string|null One of "left", "center", "right", or "none" */
	private $align;

	/**
	 * @inheritDoc
	 */
	protected function parseArgs(): void {
		parent::parseArgs();
		$this->state->useMapframe();
		// @todo: should these have defaults?
		$this->width = $this->getText( 'width', false, '/^(\d+|([1-9]\d?|100)%|full)$/' );
		$this->height = $this->getInt( 'height', false );
		$defaultAlign = $this->alignEnd();
		$this->align = $this->getText( 'align', $defaultAlign, '/^(left|center|right)$/' );
	}

	/**
	 * @inheritDoc
	 */
	protected function render( bool $isPreview ): string {
		$mapServer = $this->config->get( 'KartographerMapServer' );

		$caption = (string)$this->getText( 'text', '' );
		$framed = $caption !== '' || $this->getText( 'frameless', null ) === null;

		$cssWidth = is_numeric( $this->width ) ? "{$this->width}px" : $this->width;
		if ( preg_match( '/^\d+%$/', $cssWidth ) ) {
			if ( $cssWidth === '100%' ) {
				$staticWidth = 800;
				$this->align = 'none';
			} else {
				// @todo: deprecate old syntax completely
				$cssWidth = '300px';
				$this->width = '300';
				$staticWidth = 300;
			}
		} elseif ( $cssWidth === 'full' ) {
			$cssWidth = '100%';
			$this->align = 'none';
			$staticWidth = 800;
		} else {
			$staticWidth = $this->width;
		}

		// TODO if fullwidth, we really should use interactive mode..
		// BUT not possible to use both modes at the same time right now. T248023
		// Should be fixed, especially considering VE in page editing etc...
		$staticMode = $this->config->get( 'KartographerStaticMapframe' );
		if ( $staticMode && $isPreview ) {
			$this->getOutput()->setJsConfigVar( 'wgKartographerStaticMapframePreview', true );
		}
		$this->getOutput()->addModules( [ $staticMode && !$isPreview
			? 'ext.kartographer.staticframe'
			: 'ext.kartographer.frame' ] );

		$attrs = [
			'class' => 'mw-kartographer-map',
			// We need dimensions for when there is no img (editpreview or no staticmap)
			// because an <img> element with permanent failing src has either:
			// - intrinsic dimensions of 0x0, when alt=''
			// - intrinsic dimensions of alt size
			'style' => "width: $cssWidth; height: {$this->height}px;",
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
		if ( $cssWidth === '100%' ) {
			$containerClass .= ' mw-kartographer-full';
		}

		$attrs['href'] = SpecialMap::link( $staticLat, $staticLon, $staticZoom, $this->resolvedLangCode )
			->getLocalURL();
		$imgUrlParams = [
			'lang' => $this->resolvedLangCode,
		];
		if ( $this->showGroups && !$isPreview ) {
			$page = $this->parser->getPage();
			// Groups are not available to the static map renderer
			// before the page was saved, can only be applied via JS
			$imgUrlParams += [
				'domain' => $this->config->get( 'KartographerMediaWikiInternalUrl' ) ??
					$this->config->get( 'ServerName' ),
				'title' => $page ? MediaWikiServices::getInstance()->getTitleFormatter()->getPrefixedText( $page ) : '',
				'revid' => $this->parser->getRevisionId(),
				'groups' => implode( ',', $this->showGroups ),
			];
		}
		$imgUrl = "{$mapServer}/img/{$this->mapStyle},{$staticZoom},{$staticLat}," .
		"{$staticLon},{$staticWidth}x{$this->height}.png";
		$imgUrl .= '?' . wfArrayToCgi( $imgUrlParams );
		$imgAttrs = [
			'src' => $imgUrl,
			'alt' => '',
			'width' => (int)$staticWidth,
			'height' => (int)$this->height,
			'decoding' => 'async'
		];

		$srcSetScalesConfig = $this->config->get( 'KartographerSrcsetScales' );
		if ( $this->config->get( 'ResponsiveImages' ) && $srcSetScalesConfig ) {
			// For now only support 2x, not 1.5. Saves some bytes...
			$srcSetScales = array_intersect( $srcSetScalesConfig, [ 2 ] );
			$srcSets = [];
			foreach ( $srcSetScales as $srcSetScale ) {
				$scaledImgUrl = "{$mapServer}/img/{$this->mapStyle},{$staticZoom},{$staticLat}," .
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

		$html = Html::rawElement( 'a', $attrs, Html::rawElement( 'img', $imgAttrs ) );

		if ( $caption !== '' ) {
			$html .= Html::rawElement( 'div', [ 'class' => 'thumbcaption' ],
				$this->parser->recursiveTagParse( $caption ) );
		}

		return Html::rawElement( 'div', [ 'class' => $containerClass ],
			Html::rawElement( 'div', [
					'class' => 'thumbinner',
					'style' => "width: $cssWidth;",
				], $html ) );
	}
}
