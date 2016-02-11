<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer;

use FormatJson;
use Html;
use Parser;
use ParserOutput;
use PPFrame;
use Status;
use stdClass;

class TagHandler {
	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function handleMapsTag(
		/** @noinspection PhpUnusedParameterInspection */
		$input, array $args, Parser $parser, PPFrame $frame
	) {
		global $wgKartographerStyles, $wgKartographerDfltStyle;
		$output = $parser->getOutput();
		$output->addModuleStyles( 'ext.kartographer' );

		if ( $input !== '' && $input !== null ) {
			$status = FormatJson::parse( $input, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );
			$value = false;
			if ( $status->isOK() ) {
				$value = self::validateContent( $status );
				if ( $value && !is_array( $value ) ) {
					$value = array( $value );
				}
				$sanitizer = new SimpleStyleSanitizer( $parser, $frame );
				$sanitizer->sanitize( $value );
			}
		} else {
			$status = Status::newGood();
			$value = array();
		}

		$mode = self::validateEnum( $status, $args, 'mode', false, 'static' );
		if ( !in_array( $mode, array( 'interactive', 'static', 'data', 'link' ) ) ) {
			$status->fatal( 'kartographer-error-bad_attr', 'mode' );
			return self::reportError( $output, $status );
		}

		$width = $height = $groups = $liveId = null;
		$group = isset( $args['group'] ) ? $args['group'] : '*';

		if ( in_array( $mode, array( 'interactive', 'static', 'link' ) ) ) {
			$zoom = self::validateNumber( $status, $args, 'zoom', true );
			$lat = self::validateNumber( $status, $args, 'latitude', false );
			$lon = self::validateNumber( $status, $args, 'longitude', false );
			$style = self::validateEnum( $status, $args, 'style', $wgKartographerStyles,
				$wgKartographerDfltStyle );
		} else {
			$style = $zoom = $lat = $lon = null;
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
			case 'link':
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
			// For all GeoJSON items whose marker-symbol value begins with '-counter' and '-letter',
			// recursively replace them with an automatically incremented marker icon.
			$counters = $output->getExtensionData( 'kartographer_counters' ) ?: new stdClass();
			$counter = self::doCountersRecursive( $value, $counters );
			$output->setExtensionData( 'kartographer_counters', $counters );

			if ( !$group ) {
				// If it's not mode=data, the tag's data is private for this tag only
				$group = '_' . sha1( FormatJson::encode( $value, false, FormatJson::ALL_OK ) );
			}
			$data = $output->getExtensionData( 'kartographer_data' ) ?: new stdClass();
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
		$attrs = array(
			'class' => 'mw-kartographer mw-kartographer-' . $mode,
		);
		switch ( $mode ) {
			case 'static':
				// http://.../img/{source},{zoom},{lat},{lon},{width}x{height} [ @{scale}x ] .{format}
				// Optional query value:  ? data = {title}|{group1}|{group2}|...
				global $wgKartographerMapServer, $wgKartographerSrcsetScales;

				$statParams = sprintf( '%s/img/%s,%s,%s,%s,%sx%s',
					$wgKartographerMapServer, $style, $zoom, $lat, $lon, $width, $height );

				$imgAttrs = array(
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

				$html = Html::rawElement( 'div', $attrs, Html::rawElement( 'img', $imgAttrs ) );
				break;

			case 'interactive':
				$attrs['style'] = "width:${width}px;height:${height}px;";
				$attrs['data-style'] = $style;
				$attrs['data-zoom'] = $zoom;
				$attrs['data-lat'] = $lat;
				$attrs['data-lon'] = $lon;
				if ( $groups ) {
					$attrs['data-overlays'] = FormatJson::encode( $groups, false,
						FormatJson::ALL_OK );
				}
				$output->setExtensionData( 'kartographer_interact', true );
				$html = Html::rawElement( 'div', $attrs );
				break;

			case 'link':
				if ( $counter !== false ) {
					$attrs['data-style'] = $style;
					$attrs['data-zoom'] = $zoom;
					$attrs['data-lat'] = $lat;
					$attrs['data-lon'] = $lon;
					$html = Html::element( 'a', $attrs, $counter );
				}
				break;
		}
		$output->setExtensionData( 'kartographer_valid', true );
		return $html;
	}

	/**
	 * Handles the last step of parse process
	 * @param Parser $parser
	 */
	public static function finalParseStep( Parser $parser ) {
		$output = $parser->getOutput();

		if ( $output->getExtensionData( 'kartographer_broken' ) ) {
			$output->addTrackingCategory( 'kartographer-broken-category', $parser->getTitle() );
		}
		if ( $output->getExtensionData( 'kartographer_valid' ) ) {
			$output->addTrackingCategory( 'kartographer-tracking-category', $parser->getTitle() );
		}
		if ( $output->getExtensionData( 'kartographer_interact' ) ) {
			$output->addModules( 'ext.kartographer.live' );
			$output->addJsConfigVars( 'wgKartographerLiveData', $output->getExtensionData( 'kartographer_data' ) );
		}
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
	 * Make sure that the group name is only alphanumeric
	 * @param string $group
	 * @param Status $status
	 * @return bool
	 */
	private static function validateGroup( $group, $status ) {
		if ( !preg_match( '/^[a-zA-Z0-9]+$/', $group ) ) {
			$status->fatal( 'kartographer-error-bad_attr', 'group' );
			return false;
		}
		return true;
	}

	/**
	 * @param $values
	 * @param stdClass $counters counter-name -> integer
	 * @return bool|string returns the very first counter value that has been used
	 */
	private static function doCountersRecursive( $values, $counters ) {
		$firstMarker = false;
		if ( !is_array( $values ) ) {
			return $firstMarker;
		}
		foreach ( $values as $item ) {
			if ( property_exists( $item, 'properties' ) &&
				 property_exists( $item->properties, 'marker-symbol' )
			) {
				$marker = $item->properties->{'marker-symbol'};
				// all special markers begin with a dash
				// both 'number' and 'letter' have 6 symbols
				$type = substr( $marker, 0, 7 );
				$isNumber = $type === '-number';
				if ( $isNumber || $type === '-letter' ) {
					// numbers 1..99 or letters a..z
					$count = property_exists( $counters, $marker ) ? $counters->$marker : 0;
					if ( $count < ( $isNumber ? 99 : 26 ) ) {
						$counters->$marker = ++$count;
					}
					$marker = $isNumber ? strval( $count ) : chr( ord( 'a' ) + $count - 1 );
					$item->properties->{'marker-symbol'} = $marker;
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
				$tmp = self::doCountersRecursive( $item->features, $counters );
				if ( $firstMarker === false ) {
					$firstMarker = $tmp;
				}
			} elseif ( $type === 'GeometryCollection' && property_exists( $item, 'geometries' ) ) {
				$tmp = self::doCountersRecursive( $item->geometries, $counters );
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
	private static function reportError( ParserOutput $output, Status $status ) {
		$output->setExtensionData( 'kartographer_broken', true );
		return Html::rawElement( 'div', array( 'class' => 'mw-kartographer mw-kartographer-error' ),
			$status->getWikiText( false, 'kartographer-errors' ) );
	}
}
