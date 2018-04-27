<?php

namespace Kartographer\Tag;

use FormatJson;
use Html;
use UnexpectedValueException;
use Kartographer\SpecialMap;

/**
 * The <mapframe> tag inserts a map into wiki page
 */
class MapFrame extends TagHandler {
	protected $tag = 'mapframe';

	private $width;
	private $height;
	private $align;

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
		global $wgKartographerFrameMode,
			$wgKartographerMapServer,
			$wgServerName,
			$wgKartographerStaticMapframe;

		$alignClasses = [
			'left' => 'floatleft',
			'center' => 'center',
			'right' => 'floatright',
		];

		$thumbAlignClasses = [
			'left' => 'tleft',
			'center' => 'center',
			'right' => 'tright',
		];

		$caption = $this->getText( 'text', null );
		$framed = $caption !== null || $this->getText( 'frameless', null ) === null;

		$output = $this->parser->getOutput();
		$options = $this->parser->getOptions();

		$useSnapshot =
			$wgKartographerStaticMapframe && !$options->getIsPreview() &&
			!$options->getIsSectionPreview();

		switch ( $wgKartographerFrameMode ) {
			/* Not implemented in Kartotherian yet
			case 'static':
				global $wgKartographerMapServer, $wgKartographerSrcsetScales
				// http://.../img/{source},{zoom},{lat},{lon},{width}x{height} [ @{scale}x ] .{format}
				// Optional query value:  ? data = {title}|{group1}|{group2}|...

				$statParams = sprintf( '%s/img/%s,%s,%s,%s,%sx%s',
					$wgKartographerMapServer,
					$this->style, $this->zoom, $this->lat, $this->lon, $this->width, $this->height
				);

				$dataParam = '';
				$showGroups = $this->showGroups;
				if ( $showGroups ) {
					array_unshift( $showGroups, $this->parser->getTitle()->getPrefixedDBkey() );
					$dataParam = '?data=' .
						implode( '|', array_map( 'rawurlencode', $showGroups ) );
				}
				$imgAttrs = array(
					'src' => $statParams . '.jpeg' . $dataParam,
					'width' => $this->width,
					'height' => $this->height,
				);
				if ( $wgKartographerSrcsetScales ) {
					$srcSet = array();
					foreach ( $wgKartographerSrcsetScales as $scale ) {
						$s = '@' . $scale . 'x';
						$srcSet[$scale] = $statParams . $s . '.jpeg' . $dataParam;
					}
					$imgAttrs['srcset'] = Html::srcSet( $srcSet );
				}

				return Html::rawElement( 'div', $attrs, Html::rawElement( 'img', $imgAttrs ) );
				break;
			*/

			case 'interactive':
				$output->addModules( $useSnapshot
					? 'ext.kartographer.staticframe'
					: 'ext.kartographer.frame' );

				$fullWidth = false;

				$width = is_numeric( $this->width ) ? "{$this->width}px" : $this->width;

				if ( preg_match( '/^\d+%$/', $width ) ) {
					if ( $width === '100%' ) {
						$fullWidth = true;
						$staticWidth = 800;
					} else {
						$width = '300px'; // @todo: deprecate old syntax completely
						$staticWidth = 300;
					}
				} elseif ( $width === 'full' ) {
					$width = '100%';
					$fullWidth = true;
					$staticWidth = 800;
				} else {
					$staticWidth = $this->width;
				}

				$height = "{$this->height}px";

				$attrs = [
					'class' => 'mw-kartographer-map',
					'mw-data' => 'interface',
					'data-style' => $this->mapStyle,
					'data-width' => $this->width,
					'data-height' => $this->height,
				];
				if ( $this->zoom !== null ) {
					$staticZoom = $this->zoom;
					$attrs['data-zoom'] = $this->zoom;
				} else {
					$staticZoom = 2;
				}

				if ( $this->lat !== null && $this->lon !== null ) {
					$attrs['data-lat'] = $this->lat;
					$attrs['data-lon'] = $this->lon;
					$staticLat = $this->lat;
					$staticLon = $this->lon;
				} else {
					$staticLat = 30;
					$staticLon = 0;
				}

				if ( $this->specifiedLangCode !== null ) {
					$attrs['data-lang'] = $this->specifiedLangCode;
				}

				if ( $this->showGroups ) {
					$attrs['data-overlays'] = FormatJson::encode( $this->showGroups, false,
						FormatJson::ALL_OK );
					$this->state->addInteractiveGroups( $this->showGroups );
				}
				break;
			default:
				throw new UnexpectedValueException(
					"Unexpected frame mode '$wgKartographerFrameMode'" );
		}

		$containerClass = 'mw-kartographer-container';
		if ( $fullWidth ) {
			$containerClass .= ' mw-kartographer-full';
		}

		$params = [
			'lang' => $this->resolvedLangCode,
		];
		$bgUrl = "{$wgKartographerMapServer}/img/{$this->mapStyle},{$staticZoom},{$staticLat}," .
			"{$staticLon},{$staticWidth}x{$this->height}.png";
		if ( $this->showGroups ) {
			$params += [
				'domain' => $wgServerName,
				'title' => $this->parser->getTitle()->getPrefixedText(),
				'groups' => implode( ',', $this->showGroups ),
			];
		}
		$bgUrl .= '?' . wfArrayToCgi( $params );

		$attrs['style'] = "background-image: url({$bgUrl});";
		$attrs['href'] = SpecialMap::link( $staticLat, $staticLon, $staticZoom, $this->resolvedLangCode )
			->getLocalURL();

		if ( !$framed ) {
			$attrs['style'] .= " width: {$width}; height: {$height};";
			$attrs['class'] .= " {$containerClass} {$alignClasses[$this->align]}";

			return Html::rawElement( 'a', $attrs );
		}

		$attrs['style'] .= " height: {$height};";
		$containerClass .= " thumb {$thumbAlignClasses[$this->align]}";

		$captionFrame = Html::rawElement( 'div', [ 'class' => 'thumbcaption' ],
			$this->parser->recursiveTagParse( $caption ) );

		$mapDiv = Html::rawElement( 'a', $attrs );

		return Html::rawElement( 'div', [ 'class' => $containerClass ],
			Html::rawElement( 'div', [
					'class' => 'thumbinner',
					'style' => "width: {$width};",
				], $mapDiv . $captionFrame ) );
	}
}
