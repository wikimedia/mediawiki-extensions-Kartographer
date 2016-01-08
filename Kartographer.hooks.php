<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer;

use stdClass;
use Html;
use Parser;
use ParserOutput;
use FormatJson;
use Status;

class Singleton {

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'maps', 'Kartographer\Singleton::onMapTag' );
		return true;
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param \PPFrame $frame
	 * @return string
	 */
	public static function onMapTag( $input, /** @noinspection PhpUnusedParameterInspection */
									 array $args, Parser $parser, \PPFrame $frame ) {
		global $wgKartographerStyles, $wgKartographerDfltStyle;
		$output = $parser->getOutput();

		// Expand preprocessor markup for content and all arguments
		$input = trim( $parser->recursivePreprocess( $input, $frame ) );
		if ( $input === '' ) {
			$input = '[]';
		}
		foreach ( $args as &$v ) {
			$v = $parser->recursivePreprocess( $v, $frame );
		}

		$status = FormatJson::parse( $input, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );
		$value = false;
		if ( $status->isOK() ) {
			$value = self::validateContent( $status );
			if ( $value && !is_array( $value ) ) {
				$value = array( $value );
			}
		}

		$mode = self::validateEnum( $status, $args, 'mode', false, 'static' );
		if ( !in_array( $mode, array( 'interactive', 'static', 'data', 'anchor' ) ) ) {
			$status->fatal( 'kartographer-error-bad_attr', 'mode' );
			return self::reportError( $output, $status );
		}

		$style = $zoom = $lat = $lon = $width = $height = $groups = $liveId = null;
		$group = isset( $args['group'] ) ? $args['group'] : '*';

		switch ( $mode ) {
			case 'interactive':
			case 'static':
			case 'anchor':
				$zoom = self::validateNumber( $status, $args, 'zoom', true );
				$lat = self::validateNumber( $status, $args, 'latitude', false );
				$lon = self::validateNumber( $status, $args, 'longitude', false );
				$style = self::validateEnum( $status, $args, 'style', $wgKartographerStyles,
					$wgKartographerDfltStyle );
				break;
		}

		switch ( $mode ) {
			case 'interactive':
			case 'static':
				$width = self::validateNumber( $status, $args, 'width', true );
				$height = self::validateNumber( $status, $args, 'height', true );

				// By default, show data from all groups defined with mode=data
				// Otherwise, user may supply one or more (comma separated) groups
				if ( $group === '*' ) {
					$groups = (array)$group;
				} else {
					$groups = explode( ',', $group );
					foreach ( $groups as $grp ) {
						if ( !self::validateGroup( $grp, $status ) ) {
							break;
						}
					}
				}
				$group = null;
				break;

			case 'data':
			case 'anchor':
				if ( $group !== '*' ) {
					self::validateGroup( $group, $status );
				}
				if ( !$value ) {
					// For mode=data, at least some data should be given
					$status->fatal( 'kartographer-error-bad_data' );
				}
				break;
		}

		if ( !$status->isOK() ) {
			return self::reportError( $output, $status );
		}

		// Merge existing data with the new tag's data under the same group name
		$counter = false;
		if ( $value ) {
			if ( !$group ) {
				// If it's not mode=data, the tag's data is private for this tag only
				$group = '_' . sha1( FormatJson::encode( $value, false, FormatJson::ALL_OK ) );
			}
			$data = $output->getExtensionData( 'kartographer_data' ) ?: new stdClass();
			$counter = self::processCounters( $value, $data );
			if ( isset( $data->$group ) ) {
				$data->$group = array_merge( $data->$group, $value );
			} else {
				$data->$group = $value;
			}
			$output->setExtensionData( 'kartographer_data', $data );
			if ( $groups ) {
				$groups[] = $group;
			}
		}
		if ( $groups ) {
			$title = $parser->getTitle()->getPrefixedDBkey();
			$dataParam = '?data=' . rawurlencode( $title ) . '|' . implode( '|', $groups );
		} else {
			$dataParam = '';
		}

		$html = '';
		switch ( $mode ) {
			case 'static':
				// http://.../img/{source},{zoom},{lat},{lon},{width}x{height} [ @{scale}x ] .{format}
				// Optional query value:  ? data = {title}|{group1}|{group2}|...
				global $wgKartographerMapServer, $wgKartographerSrcsetScales;

				$statParams = sprintf( '%s/img/%s,%s,%s,%s,%sx%s',
					$wgKartographerMapServer, $style,
					$zoom, $lat, $lon, $width, $height
				);

				$imgAttrs = array(
					'class' => 'mw-kartographer-img',
					'src' => $statParams . '.jpeg' . $dataParam,
					'width' => $width,
					'height' => $height,
				);
				if ( $wgKartographerSrcsetScales ) {
					$srcSet = array();
					foreach ( $wgKartographerSrcsetScales as $scale ) {
						$s = '@' . $scale . 'x';
						$srcSet[$scale] = $statParams . $s . '.jpeg' . $dataParam;
					}
					$imgAttrs['srcset'] = Html::srcSet( $srcSet );
				}

				$output->addModules( 'ext.kartographer.static' );
				$html = Html::rawElement( 'img', $imgAttrs );
				break;

			case 'interactive':
				$attrs = array(
					'class' => 'mw-kartographer-live',
					'style' => "width:${width}px;height:${height}px;",
					'data-style' => $style,
					'data-zoom' => $zoom,
					'data-lat' => $lat,
					'data-lon' => $lon,
				);
				if ( $groups ) {
					$attrs['data-overlays'] = FormatJson::encode( $groups, false,
							FormatJson::ALL_OK );
				}
				$output->setExtensionData( 'kartographer_interact', true );
				$html = Html::rawElement( 'div', $attrs );
				break;

			case 'anchor':
				if ( $counter !== false ) {
					$html = Html::element( 'a', array(
						'class' => 'mw-kartographer',
						'data-zoom' => $zoom,
						'data-lat' => $lat,
						'data-lon' => $lon,
						'data-style' => $style,
					), $counter );
				}
				break;
		}
		$output->setExtensionData( 'kartographer_valid', true );
		return $html;
	}

	public static function onParserAfterParse( Parser $parser ) {
		$output = $parser->getOutput();
		if ( $output->getExtensionData( 'kartographer_broken' ) ) {
			$output->addTrackingCategory( 'kartographer-broken-category', $parser->getTitle() );
		}
		if ( $output->getExtensionData( 'kartographer_valid' ) ) {
			$output->addTrackingCategory( 'kartographer-tracking-category', $parser->getTitle() );
		}
		if ( $output->getExtensionData( 'kartographer_interact' ) ) {
			$output->addModules( 'ext.kartographer.live' );

			global $wgKartographerSrcsetScales, $wgKartographerMapServer, $wgKartographerIconServer, $wgKartographerForceHttps;
			if ( $wgKartographerSrcsetScales ) {
				$output->addJsConfigVars( 'wgKartographerSrcsetScales',
					$wgKartographerSrcsetScales );
			}
			$output->addJsConfigVars( 'wgKartographerMapServer', $wgKartographerMapServer );
			$output->addJsConfigVars( 'wgKartographerIconServer', $wgKartographerIconServer );
			$output->addJsConfigVars( 'wgKartographerForceHttps', $wgKartographerForceHttps );

			$output->addJsConfigVars( 'wgKartographerLiveData', $output->getExtensionData( 'kartographer_data' ) );
		}

		return true;
	}

	/**
	 * @param Status $status
	 * @param array $args
	 * @param string $value
	 * @param bool $isInt
	 * @return float|int|false
	 */
	private static function validateNumber( $status, $args, $value, $isInt ) {
		if ( isset( $args[$value] ) ) {
			$v = $args[$value];
			$pattern = $isInt ? '/^[0-9]+$/' : '/^[-+]?[0-9]*\.?[0-9]+$/';
			if ( preg_match( $pattern, $v ) ) {
				return $isInt ? intval( $v ) : floatval( $v );
			}
		}
		$status->fatal( 'kartographer-error-bad_attr', $value );
		return false;
	}

	/**
	 * @param Status $status
	 * @param array $args
	 * @param string $value
	 * @param array|bool|false $set
	 * @param string|bool|false $default
	 * @return string|false
	 */
	private static function validateEnum( $status, $args, $value, $set = false, $default = false ) {
		if ( !isset( $args[$value] ) ) {
			return $default;
		}
		$v = $args[$value];
		if ( !$set || !in_array( $v, $set ) ) {
			return $v;
		}
		$status->fatal( 'kartographer-error-bad_attr', $value );
		return false;
	}

	/**
	 * @param Status $status
	 * @return mixed
	 */
	private static function validateContent( $status ) {
		$value = $status->getValue();

		// The content must be a non-associative array of values or an object
		if ( $value instanceof stdClass ) {
			$value = array ( $value );
		} elseif ( !is_array( $value ) ) {
			$status->fatal( 'kartographer-error-bad_data' );
			return false;
		}

		return $value;
	}

	/**
	 * @param string $group
	 * @param Status $status
	 * @return bool
	 */
	private static function validateGroup( $group, $status ) {
		if ( !preg_match( '/^[a-zA-Z0-9)]+$/', $group ) ) {
			$status->fatal( 'kartographer-error-bad_attr', 'group' );
			return false;
		}
		return true;
	}

	/**
	 *
	 * @param mixed $values
	 * @param stdClass $data
	 * @return bool|int|string
	 */
	private static function processCounters( $values, $data ) {
		if ( !property_exists( $data, 'counters' ) ) {
			$numCounters = new stdClass();
			$alphaCounters = new stdClass();
			$data->counters = (object) array( 'numeric' => $numCounters, 'alpha' => $alphaCounters );
		} else {
			$numCounters = $data->counters->numeric;
			$alphaCounters = $data->counters->alpha;
		}
		return self::processItemList( $values, $numCounters, $alphaCounters );
	}

	/**
	 * @param $values
	 * @param $numCounters
	 * @param $alphaCounters
	 * @return bool|string
	 */
	private static function processItemList( $values, $numCounters, $alphaCounters ) {
		$firstMarker = false;
		if ( !is_array( $values ) ) {
			return $firstMarker;
		}
		foreach ( $values as $item ) {
			if ( property_exists( $item, 'properties' ) ) {
				$props = $item->properties;
				if ( property_exists( $props, 'counter' ) ) {
					$grp = $props->counter;
					unset( $props->counter );
					$count =
						property_exists( $numCounters, $grp ) ? min( $numCounters->$grp + 1, 99 )
							: 1;
					$marker = strval( $count );
					$numCounters->$grp = $count;
				} elseif ( property_exists( $props, 'letter' ) ) {
					$grp = $props->letter;
					unset( $props->letter );
					$count =
						property_exists( $alphaCounters, $grp ) ? min( $alphaCounters->$grp + 1,
							26 ) : 1;
					$marker = chr( ord( 'a' ) + $count - 1 );  // letters a..z
					$alphaCounters->$grp = $count;
				} else {
					$marker = false;
				}

				if ( $marker !== false ) {
					$props->{'marker-symbol'} = $marker;
					if ( $firstMarker === false ) {
						$firstMarker = $marker;
					}
				}
			}
			if ( !property_exists( $item, 'type' ) ) {
				continue;
			}
			$type = $item->type;
			if ( $type === 'FeatureCollection' && property_exists( $item, 'features' ) ) {
				$tmp = self::processItemList( $item->features, $numCounters, $alphaCounters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			} elseif ( $type === 'GeometryCollection' && property_exists( $item, 'geometries' ) ) {
				$tmp = self::processItemList( $item->geometries, $numCounters, $alphaCounters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			}
		}
		return $firstMarker;
	}

	/**
	 * @param ParserOutput $output
	 * @param Status $status
	 * @return string
	 */
	private static function reportError( $output, $status ) {
		$output->addModules( 'ext.kartographer.error' );
		$output->setExtensionData( 'kartographer_broken', true );
		return Html::rawElement( 'div', array( 'class' => 'mw-kartographer-error' ),
			$status->getWikiText( false, 'kartographer-errors' ) );
	}
}
