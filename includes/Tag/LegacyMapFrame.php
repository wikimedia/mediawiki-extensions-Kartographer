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

	/**
	 * @inheritDoc
	 */
	protected function render( bool $isPreview ): string {
		$mapServer = $this->config->get( 'KartographerMapServer' );

		$caption = (string)$this->args->text;
		$framed = $caption !== '' || $this->args->frameless === null;

		if ( $this->args->width === 'full' ) {
			$cssWidth = '100%';
			$this->args->align = 'none';
			$staticWidth = 800;
		} else {
			$cssWidth = $this->args->width . 'px';
			$staticWidth = (int)$this->args->width;
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
			'style' => "width: $cssWidth; height: {$this->args->height}px;",
			'data-mw' => 'interface',
			'data-style' => $this->args->mapStyle,
			'data-width' => $this->args->width,
			'data-height' => $this->args->height,
		];
		if ( $this->args->zoom !== null ) {
			$staticZoom = (int)$this->args->zoom;
			$attrs['data-zoom'] = $this->args->zoom;
		} else {
			$staticZoom = 'a';
		}

		if ( $this->args->lat !== null && $this->args->lon !== null ) {
			$attrs['data-lat'] = $this->args->lat;
			$attrs['data-lon'] = $this->args->lon;
			$staticLat = (float)$this->args->lat;
			$staticLon = (float)$this->args->lon;
		} else {
			$staticLat = 'a';
			$staticLon = 'a';
		}

		if ( $this->args->specifiedLangCode !== null ) {
			$attrs['data-lang'] = $this->args->specifiedLangCode;
		}

		if ( $this->args->showGroups ) {
			$attrs['data-overlays'] = FormatJson::encode( $this->args->showGroups, false,
				FormatJson::ALL_OK );
			$this->state->addInteractiveGroups( $this->args->showGroups );
		}

		$containerClass = 'mw-kartographer-container';
		if ( $this->args->width === 'full' ) {
			$containerClass .= ' mw-kartographer-full';
		}

		$attrs['href'] = SpecialMap::link( $staticLat, $staticLon, $staticZoom, $this->args->resolvedLangCode )
			->getLocalURL();
		$imgUrlParams = [
			'lang' => $this->args->resolvedLangCode,
		];
		if ( $this->args->showGroups && !$isPreview ) {
			$page = $this->parser->getPage();
			// Groups are not available to the static map renderer
			// before the page was saved, can only be applied via JS
			$imgUrlParams += [
				'domain' => $this->config->get( 'KartographerMediaWikiInternalUrl' ) ??
					$this->config->get( 'ServerName' ),
				'title' => $page ? MediaWikiServices::getInstance()->getTitleFormatter()->getPrefixedText( $page ) : '',
				'revid' => $this->parser->getRevisionId(),
				'groups' => implode( ',', $this->args->showGroups ),
			];
		}
		$imgUrl = "{$mapServer}/img/{$this->args->mapStyle},{$staticZoom},{$staticLat}," .
		"{$staticLon},{$staticWidth}x{$this->args->height}.png";
		$imgUrl .= '?' . wfArrayToCgi( $imgUrlParams );
		$imgAttrs = [
			'src' => $imgUrl,
			'alt' => '',
			'width' => $staticWidth,
			'height' => (int)$this->args->height,
			'decoding' => 'async'
		];

		$srcSetScalesConfig = $this->config->get( 'KartographerSrcsetScales' );
		if ( $this->config->get( 'ResponsiveImages' ) && $srcSetScalesConfig ) {
			// For now only support 2x, not 1.5. Saves some bytes...
			$srcSetScales = array_intersect( $srcSetScalesConfig, [ 2 ] );
			$srcSets = [];
			foreach ( $srcSetScales as $srcSetScale ) {
				$scaledImgUrl = "{$mapServer}/img/{$this->args->mapStyle},{$staticZoom},{$staticLat}," .
				"{$staticLon},{$staticWidth}x{$this->args->height}@{$srcSetScale}x.png";
				$scaledImgUrl .= '?' . wfArrayToCgi( $imgUrlParams );
				$srcSets[] = "{$scaledImgUrl} {$srcSetScale}x";
			}
			$imgAttrs[ 'srcset' ] = implode( ', ', $srcSets );
		}

		$usesAutoPosition = $staticZoom === 'a' || $staticLat === 'a' || $staticLon === 'a';
		if ( $isPreview && $usesAutoPosition ) {
			// The static thumbnail cannot be rendered at all, so show a blank
			// div instead.  This will be replaced by a dynamic thumbnail when
			// JavaScript is available.
			$thumbnail = Html::element( 'div', [
				'width' => $staticWidth,
				'height' => (int)$this->args->height,
			] );
		} else {
			$thumbnail = Html::element( 'img', $imgAttrs );
		}

		if ( !$framed ) {
			$attrs['class'] .= ' ' . $containerClass . ' ' . self::ALIGN_CLASSES[$this->args->align];
			return Html::rawElement( 'a', $attrs, $thumbnail );
		}

		$containerClass .= ' thumb ' . self::THUMB_ALIGN_CLASSES[$this->args->align];

		$html = Html::rawElement( 'a', $attrs, $thumbnail );

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
