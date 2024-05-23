<?php

namespace Kartographer\Tests;

use ApiMain;
use ApiResult;
use ApiUsageException;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;

/**
 * @covers \Kartographer\Api\ApiSanitizeMapData
 * @group Kartographer
 * @group Database
 * @license MIT
 */
class ApiSanitizeMapDataTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'KartographerMapServer' => 'http://192.0.2.0',
			MainConfigNames::ArticlePath => '/wiki/$1',
			MainConfigNames::LanguageCode => 'qqx',
			MainConfigNames::Script => '/w/index.php',
			MainConfigNames::ScriptPath => '/w',
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
			$this->assertSame( $text, $data['error'] );
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
		return json_encode( json_decode( $json ), JSON_UNESCAPED_SLASHES );
	}

	// phpcs:disable Generic.Files.LineLength
	public static function provideTest() {
		return [
			[ 'Foo', '{', false, '<p>(kartographer-error-json: (json-error-syntax))
</p>' ],
			[ null, '{', false, '<p>(kartographer-error-json: (json-error-syntax))
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
			"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"(red-link-title: Link to nowhere)\">Link to nowhere<\/a>",
			"marker-symbol":"a",
			"_origtitle":"A&B",
			"_origdescription": "[[Link to nowhere]]"
		}
	}]' ],
		];
	}

	public static function provideErrors() {
		return [
			[ '[]', '[]' ],
			[ '', '[]' ]
		];
	}

	public function testUserThumbSize() {
		$geoJson = '{
			"type": "Feature",
			"geometry": { "type": "Point", "coordinates": [ 0, 0 ] },
			"properties": { "description": "[[File:Foobar.jpg|thumb]]" }
		}';
		$expected = '[ {
			"type": "Feature",
			"geometry": { "type": "Point", "coordinates": [ 0, 0 ] },
			"properties": {
				"description": "<figure class=\"mw-default-size\" typeof=\"mw:Error mw:File/Thumb\"><a href=\"/w/index.php?title=Special:Upload&amp;wpDestFile=Foobar.jpg\" class=\"new\" title=\"File:Foobar.jpg\"><span class=\"mw-file-element mw-broken-media\" data-width=\"180\">File:Foobar.jpg</span></a><figcaption></figcaption></figure>",
				"_origdescription": "[[File:Foobar.jpg|thumb]]"
			}
		} ]';

		$imageInfoResponse = '{ "query": { "pages": { "1": {
			"imageinfo": [ { "width": 800, "height": 600, "thumburl": "#" } ]
		} } } }';
		$this->installMockHttp( $imageInfoResponse );

		$defaults = $this->getConfVar( 'DefaultUserOptions' );
		// thumbsize 0 is equivalent to 120px
		$this->overrideConfigValue( 'DefaultUserOptions', [ 'thumbsize' => 0 ] + $defaults );

		$manager = $this->getServiceContainer()->getUserOptionsManager();
		// thumbsize 1 is equivalent to 150px
		$manager->setOption( RequestContext::getMain()->getUser(), 'thumbsize', 1 );

		$result = $this->makeRequest( null, $geoJson );
		$data = $result->getResultData();
		$this->assertSame( $this->normalizeJson( $expected ), $data['sanitize-mapdata']['sanitized'] );
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
