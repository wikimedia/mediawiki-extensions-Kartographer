<?php

namespace Kartographer\Tests;

use ApiTestCase;
use CommentStoreComment;
use MediaWiki\Storage\SlotRecord;
use WikiPage;
use WikitextContent;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \Kartographer\ApiQueryMapData
 */
class ApiQueryMapDataTest extends ApiTestCase {

	private const MAPFRAME_JSON = '{"type":"Feature","geometry":{"type":"Point","coordinates":[1,2]}}';
	private const MAPFRAME_CONTENT = '<mapframe latitude=0 longitude=0 width=1 height=1>' .
		self::MAPFRAME_JSON . '</mapframe>';
	private const MAPFRAME_JSON_OTHER = '{"type":"Feature","geometry":{"type":"Point","coordinates":[2,1]}}';
	private const MAPFRAME_CONTENT_OTHER = '<mapframe latitude=0 longitude=0 width=1 height=1>' .
		self::MAPFRAME_JSON_OTHER . '</mapframe>';

	public function testExecuteMissingPage() {
		[ $apiResult ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => __METHOD__,
		] );

		$this->assertTrue( $apiResult['query']['pages'][-1]['missing'] );
	}

	/**
	 * @dataProvider executeWithTitleProvider
	 */
	public function testExecuteWithTitle( string $content, array $expectedData ) {
		$page = $this->getExistingTestPage( __METHOD__ );
		$this->addRevision( $page, $content );

		[ $apiResult ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => $page->getDBkey(),
		] );

		$this->assertResult( $expectedData, $apiResult );
	}

	public function executeWithTitleProvider() {
		$hash = '_' . sha1( '[' . self::MAPFRAME_JSON . ']' );

		return [
			'No mapframe' => [
				'',
				[]
			],
			'Empty mapframe' => [
				'<mapframe latitude=0 longitude=0 width=1 height=1 />',
				[ [ '[]' ] ]
			],
			'Map with features' => [
				self::MAPFRAME_CONTENT,
				[ [ '{"' . $hash . '":[' . self::MAPFRAME_JSON . ']}' ] ]
			],
		];
	}

	public function testExecuteWithMultiple() {
		$hash = '_' . sha1( '[' . self::MAPFRAME_JSON . ']' );
		$hashOther = '_' . sha1( '[' . self::MAPFRAME_JSON_OTHER . ']' );
		$expected = [ '{"' . $hash . '":[' . self::MAPFRAME_JSON . ']}' ];
		$expectedOther = [ '{"' . $hashOther . '":[' . self::MAPFRAME_JSON_OTHER . ']}' ];

		$pageOne = $this->getExistingTestPage( __METHOD__ );
		$oldRevPageOne = $this->addRevision( $pageOne, self::MAPFRAME_CONTENT_OTHER );
		$currRevPageOne = $this->addRevision( $pageOne, self::MAPFRAME_CONTENT );

		$pageTwo = $this->getExistingTestPage( __METHOD__ . '-2' );
		$currRevPageTwo = $this->addRevision( $pageTwo, self::MAPFRAME_CONTENT_OTHER );

		// query two different pages
		[ $apiResultTitles ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => $pageOne->getDBkey() . '|' . $pageTwo->getDBkey(),
		] );
		$this->assertResult( [ $expected, $expectedOther ], $apiResultTitles );

		// query a single revision
		[ $apiResultOneRevision ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $currRevPageOne->getId(),
		] );
		$this->assertResult( [ $expected ], $apiResultOneRevision );

		// query two different revisions from two differrent pages
		[ $apiResultRevisions ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $currRevPageOne->getId() . '|' . $currRevPageTwo->getId(),
		] );
		$this->assertResult( [ $expected, $expectedOther ], $apiResultRevisions );

		// using an old revision from the same page still just returns the data from the latest revision
		[ $apiResultOldRevision ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $oldRevPageOne->getId(),
		] );
		// with old revids working this should return `[ $expectedOther ]`
		$this->assertResult( [ $expected ], $apiResultOldRevision );
	}

	private function addRevision( WikiPage $page, string $wikitext ) {
		return $page->newPageUpdater( $this->getTestUser()->getUser() )
			->setContent( SlotRecord::MAIN, new WikitextContent( $wikitext ) )
			->saveRevision( CommentStoreComment::newUnsavedComment( __CLASS__ ) );
	}

	private function assertResult( array $expectedMapData, array $apiResult ) {
		$this->assertSame(
			$expectedMapData,
			array_column( $apiResult['query']['pages'], 'mapdata' )
		);
	}
}
