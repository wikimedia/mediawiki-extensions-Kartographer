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
		parent::parseArgs();
		// @todo: should these have defaults?
		$this->width = $this->getText( 'width', false, '/^(\d+|([1-9]\d?|100)%)$/' );
		$this->height = $this->getInt( 'height' );
		$defaultAlign = $this->getLanguage()->isRTL() ? 'left' : 'right';
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

		$output = $this->parser->getOutput();

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
					$dataParam = '?data=' . implode( '|', array_map( 'rawurlencode', $showGroups ) );
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
				$output->addModules( 'ext.kartographer.frame' );

				$width = is_numeric( $this->width ) ? "{$this->width}px" : $this->width;
				if ( preg_match( '/^\d+%$/', $width ) && $width != '100%' ) {
					$width = '300px'; // @todo: deprecate old syntax completely
				}
				$attrs = [
					'class' => 'mw-kartographer-interactive',
					'mw-data' => 'interface',
					'style' => "width:{$width}; height:{$this->height}px;",
					'data-style' => $this->mapStyle,
				];
				if ( $this->zoom !== null ) {
					$attrs['data-zoom'] = $this->zoom;
				}
				if ( $this->lat !== null && $this->lon !== null ) {
					$attrs['data-lat'] = $this->lat;
					$attrs['data-lon'] = $this->lon;

				}
				if ( isset( $alignClasses[$this->align] ) ) {
					$attrs['class'] .= ' ' . $alignClasses[$this->align];
				}
				if ( $this->showGroups ) {
					$attrs['data-overlays'] = FormatJson::encode( $this->showGroups, false,
						FormatJson::ALL_OK );
				}

				$this->state->addInteractiveGroups( $this->showGroups );

				return Html::rawElement( 'div', $attrs );
				break;
			default:
				throw new UnexpectedValueException(
					"Unexpected frame mode '$wgKartographerFrameMode'" );
		}
	}
}
