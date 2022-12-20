<?php
namespace Kartographer\Tests;

use ApiMain;
use ApiResult;
use ApiUsageException;
use DerivativeContext;
use FauxRequest;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \Kartographer\Api\ApiSanitizeMapData
 * @group Kartographer
 * @license MIT
 */
class ApiSanitizeMapDataTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
		] );
	}

	/**
	 * @dataProvider provideTest
	 */
	public function test( ?string $title, string $json, bool $isValid, string $text ) {
		$result = $this->makeRequest( $title, $json );

		$data = $result->getResultData();
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
	 */
	public function testErrors( string $title, string $json ) {
		$this->expectException( ApiUsageException::class );
		$this->makeRequest( $title, $json );
	}

	private static function normalizeJson( string $json ): string {
		return json_encode( json_decode( $json ) );
	}

	public function provideTest() {
		// phpcs:disable Generic.Files.LineLength
		return [
			[ 'Foo', '{', false, '<p>Couldn\'t parse JSON: Syntax error
</p>' ],
			[ null, '{', false, '<p>Couldn\'t parse JSON: Syntax error
</p>' ],
			[ null, '[{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    },
    "properties": {
      "title": "A&B",
      "description": "[[Link to nowhere]]",
      "marker-symbol": "-letter"
    }
},]', true, '[{
	"type":"Feature",
	"geometry":{
		"type":"Point",
		"coordinates":[-122.3988,37.8013]},
		"properties":{
			"title":"A&amp;B",
			"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"Link to nowhere (page does not exist)\">Link to nowhere<\/a>",
			"marker-symbol":"a",
			"_origtitle":"A&B",
			"_origdescription": "[[Link to nowhere]]"
		}
	}]' ],
		];
		// phpcs:enable
	}

	public function provideErrors() {
		return [
			[ '[]', '[]' ],
			[ '', '[]' ]
		];
	}

	private function makeRequest( ?string $title, string $text, bool $post = true ): ApiResult {
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
