<?php
namespace Kartographer\Tests;

use ApiMain;
use DerivativeContext;
use FauxRequest;
use MediaWikiTestCase;
use RequestContext;
use UsageException;

/**
 * @group Kartographer
 */
class ApiSanitizeMapDataTest extends MediaWikiTestCase {

	public function setUp() {
		$this->setMwGlobals( [
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
		] );
		parent::setUp();
	}

	/**
	 * @dataProvider provideTest
	 */
	public function test( $title, $json, $isValid, $text ) {
		$result = $this->makeRequest( $title, $json );

		$data =$result->getResultData();
		$this->assertArrayHasKey( 'sanitize-mapdata', $data );
		$data = $data['sanitize-mapdata'];
		if ( $isValid ) {
			$this->assertArrayHasKey( 'sanitized', $data );
			$this->assertArrayNotHasKey( 'error', $data );
			$this->assertEquals( self::normalizeJson( $text ),
				self::normalizeJson( $data['sanitized'] ) );
		} else {
			$this->assertArrayNotHasKey( 'sanitized', $data );
			$this->assertArrayHasKey( 'error', $data );
			$this->assertEquals( $text, trim( $data['error'] ) );
		}
	}

	/**
	 * @dataProvider provideErrors
	 * @expectedException UsageException
	 */
	public function testErrors( $title, $json ) {
		$this->makeRequest( $title, $json );
	}

	private static function normalizeJson( $json ) {
		return json_encode( json_decode( $json ) );
	}

	public function provideTest() {
		return [
			[ 'Foo', '{', false, '<p>Syntax error
</p>' ],
			[ null, '{', false, '<p>Syntax error
</p>' ],
			[ null, '[{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    },
    "properties": {
      "title": "A&B",
      "description": "[[Link to nowhere]]"
    }
},]', true, '[{
	"type":"Feature",
	"geometry":{
		"type":"Point",
		"coordinates":[-122.3988,37.8013]},
		"properties":{
			"title":"A&amp;B",
			"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"Link to nowhere (page does not exist)\">Link to nowhere<\/a>"
		}
	}]' ],
		];
	}

	public function provideErrors() {
		return [
			[ '[]', '[]' ],
			[ '', '[]' ]
		];
	}

	private function makeRequest( $title, $text, $post = true ) {
		$params = [ 'action' => 'sanitize-mapdata', 'text' => $text ];
		if ( $title !== null ) {
			$params['title'] = $title;
		}
		$request = new FauxRequest( $params, $post );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$api = new ApiMain( $context );
		$api->execute();
		return $api->getResult();
	}
}
