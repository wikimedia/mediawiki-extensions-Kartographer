<?php

namespace Kartographer\Tests;

use Kartographer\CoordFormatter;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @covers \Kartographer\CoordFormatter
 * @group Kartographer
 * @license MIT
 */
class CoordFormatterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideFormatter
	 */
	public function testFormatter( string $expected, float $lat, float $lon ) {
		$result = ( new CoordFormatter( $lat, $lon ) )->format( 'en' );
		$this->assertEquals( $expected, $result );
	}

	public function provideFormatter() {
		return [
			[ '0°0′0″N 0°0′0″E', 0, 0 ],
			[ '0°0′0″N 0°0′0″E', -0.000000000001, 0.000000000001 ],
			[ '1°0′0″S 1°0′0″W', -1, -1 ],
			[ '1°0′0″N 11°0′0″W', 0.999999999999, -10.999999999999 ],
			[ '10°20′0″N 0°0′0″E', 10.333333333333333333, 0 ],
			// Was getting 10°11′60″N 0°0′0″E here
			[ '10°12′0″N 0°0′0″E', 10.2, 0 ],
			[ '45°30′0″N 20°0′36″E', 45.5, 20.01 ],
			[ '27°46′23″N 82°38′24″W', 27.773056, -82.64 ],
			[ '29°57′31″N 90°3′54″W', 29.958611, -90.065 ],
		];
	}

	/**
	 * @group Parsoid
	 * @dataProvider provideParsoidFormatter
	 */
	public function testParsoidFormatter( string $expected, float $lat, float $lon ) {
		$extApi = new ParsoidExtensionAPI( new MockEnv( [] ) );
		$doc = DOMCompat::newDocument( true );
		$doc->loadHTML( '<html><body></body></html>' );
		DOMDataUtils::prepareDoc( $doc );
		$fragment = ( new CoordFormatter( $lat, $lon ) )->formatParsoidSpan( $extApi, 'en' );
		DOMDataUtils::visitAndStoreDataAttribs( $fragment, [ 'discardDataParsoid' => true ] );
		$result = DOMUtils::getFragmentInnerHTML( $fragment );
		$this->assertEquals( $expected, $result );
	}

	public function provideParsoidFormatter() {
		return [
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key":' .
				'"kartographer-coord-lat-pos-lon-pos","params":[0,0,0,0,0,0]}}\'></span>', 0, 0 ],
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key"' .
				':"kartographer-coord-lat-pos-lon-pos","params":[0,0,0,0,0,0]}}\'></span>',
				-0.000000000001, 0.000000000001 ],
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key"' .
				':"kartographer-coord-lat-neg-lon-neg","params":[1,0,0,1,0,0]}}\'></span>', -1, -1 ],
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key"' .
				':"kartographer-coord-lat-pos-lon-neg","params":[1,0,0,11,0,0]}}\'></span>',
				0.999999999999, -10.999999999999 ],
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key"' .
				':"kartographer-coord-lat-pos-lon-pos","params":[10,20,0,0,0,0]}}\'></span>',
				10.333333333333333333, 0 ],
			// Was getting 10°11′60″N 0°0′0″E here
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key"' .
				':"kartographer-coord-lat-pos-lon-pos","params":[10,12,0,0,0,0]}}\'></span>', 10.2, 0 ],
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key":' .
				'"kartographer-coord-lat-pos-lon-pos","params":[45,30,0,20,0,36]}}\'></span>', 45.5, 20.01 ],
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key":' .
				'"kartographer-coord-lat-pos-lon-neg","params":[27,46,23,82,38,24]}}\'></span>', 27.773056, -82.64 ],
			[ '<span typeof="mw:I18n" data-mw-i18n=\'{"/":{"lang":"en","key":' .
				'"kartographer-coord-lat-pos-lon-neg","params":[29,57,31,90,3,54]}}\'></span>', 29.958611, -90.065 ],
		];
	}
}
