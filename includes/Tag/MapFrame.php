<?php

namespace Kartographer\Tag;


use FormatJson;
use Html;
use UnexpectedValueException;

/**
 * The <mapframe> tag inserts a map into wiki page
 */
class MapFrame extends TagHandler {
	protected $tag = 'mapframe';

	private $width;
	private $height;
	private $align;

	protected function parseArgs() {
		$this->parseMapArgs();
		// @todo: should these have defaults?
		$this->width = $this->getInt( 'width' );
		$this->height = $this->getInt( 'height' );
		$defaultAlign = $this->language->isRTL() ? 'left' : 'right';
		$this->align = $this->getText( 'align', $defaultAlign, '/^(left|center|right)$/' );
	}

	/**
	 * @return string
	 */
	protected function render() {
		global $wgKartographerFrameMode;

		$alignClasses = [
			'left' => 'floatleft',
			'center' => 'center',
			'right' => 'floatright',
		];

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
				$groups = $this->groups;
				if ( $groups ) {
					array_unshift( $groups, $this->parser->getTitle()->getPrefixedDBkey() );
					$dataParam = '?data=' . implode( '|', array_map( 'rawurlencode', $groups ) );
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
				$this->parser->getOutput()->addModules( 'ext.kartographer.live' );
				$attrs = $this->defaultAttributes;
				$attrs['class'] .= ' mw-kartographer-interactive';
				if ( isset( $alignClasses[$this->align] ) ) {
					$attrs['class'] .= ' ' . $alignClasses[$this->align];
				}
				$attrs['style'] = "width:{$this->width}px; height:{$this->height}px;";
				$attrs['data-style'] = $this->style;
				$attrs['data-zoom'] = $this->zoom;
				$attrs['data-lat'] = $this->lat;
				$attrs['data-lon'] = $this->lon;
				if ( $this->groups ) {
					$attrs['data-overlays'] = FormatJson::encode( $this->groups, false,
						FormatJson::ALL_OK );
				}
				$this->parser->getOutput()->setExtensionData( 'kartographer_interact', true );
				return Html::rawElement( 'div', $attrs );
				break;
			default:
				throw new UnexpectedValueException(
					"Unexpected frame mode '$wgKartographerFrameMode'" );
		}
	}
}
