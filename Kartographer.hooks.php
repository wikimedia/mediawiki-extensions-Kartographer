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
use FormatJson;
use Status;

class Singleton {

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'map', 'Kartographer\Singleton::onMapTag' );
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
		$input = trim( $parser->recursivePreprocess( $input, $frame ) );
		if ( $input === '' ) {
			$input = '[]';
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

		$style = $zoom = $lat = $lon = $width = $height = $group = $groups = $liveId = null;
		switch ( $mode ) {
			default:
				$status->fatal( 'kartographer-error-bad_attr', 'mode' );
				break;

			/** @noinspection PhpMissingBreakStatementInspection */
			case 'interactive':
			case 'static':
				$zoom = self::validateNumber( $status, $args, 'zoom', true );
				$lat = self::validateNumber( $status, $args, 'latitude', false );
				$lon = self::validateNumber( $status, $args, 'longitude', false );
				$width = self::validateNumber( $status, $args, 'width', true );
				$height = self::validateNumber( $status, $args, 'height', true );
				$style = self::validateEnum( $status, $args, 'style', $wgKartographerStyles,
						$wgKartographerDfltStyle );

				// By default, show data from all groups defined with mode=data
				// Otherwise, user may supply one or more (comma separated) groups
				$groups = self::validateEnum( $status, $args, 'group', false, '*' );
				if ( $groups !== '*' ) {
					$groups = explode( ',', $groups );
					foreach ( $groups as $grp ) {
						if ( !self::validateGroup( $grp, $status ) ) {
							break;
						}
					}
				} else {
					$groups = array( '*' );
				}
				break;

			case 'data':
				if ( !isset( $args['group'] ) ) {
					$group = '*'; // use default group
				} else {
					$group = $args['group'];
					self::validateGroup( $group, $status );
				}
				if ( !$value ) {
					// For mode=data, at least some data should be given
					$status->fatal( 'kartographer-error-bad_data' );
				}
				break;
		}

		if ( !$status->isOK() ) {
			$output->addModules( 'ext.kartographer.error' );
			$output->setExtensionData( 'kartographer_broken', true );
			return Html::rawElement( 'div', array( 'class' => 'mw-kartographer-error' ),
					$status->getWikiText( false, 'kartographer-errors' ) );
		}

		// Merge existing data with the new tag's data under the same group name
		if ( $value ) {
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
		switch ( $mode ) {
			case 'static':
				// http://.../img/{source},{zoom},{lat},{lon},{width}x{height} [ @{scale}x ] .{format}
				// Optional query value:  ? data = {title}|{group1}|{group2}|...
				global $wgKartographerMapServer, $wgKartographerSrcsetScales;

				$statParams = sprintf( '%s/img/%s,%s,%s,%s,%sx%s', $wgKartographerMapServer, $style,
						$zoom, $lat, $lon, $width, $height );
				$attrs = array(
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
					$attrs['srcset'] = Html::srcSet( $srcSet );
				}

				$output->addModules( 'ext.kartographer.static' );
				$html = Html::rawElement( 'img', $attrs );
				break;

			case 'interactive':
				$attrs = array(
						'class' => 'mw-kartographer-live',
						'style' => "width:${width}px;height:${height}px",
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

//		if ( !is_array( $value ) ||
//			 count( array_filter( array_keys( $value ), 'is_string' ) ) !== count( $value )
//		) {

		// The content must be a non-associative array of values
		if ( !is_array( $value ) && !( $value instanceof stdClass ) ) {
			$status->fatal( 'kartographer-error-bad_data' );
			return false;
		}
		// TODO: TBD: security check?
		return $value;
	}

	/**
	 * @param $group
	 * @param $status
	 * @return bool
	 */
	private static function validateGroup( $group, $status ) {
		if ( !preg_match( '/^[a-zA-Z0-9)]+$/', $group ) ) {
			$status->fatal( 'kartographer-error-bad_attr', 'group' );
			return false;
		}
		return true;
	}
}
