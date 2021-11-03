<?php

namespace Kartographer\Tests;

use ApiTestCase;
use CommentStoreComment;
use MediaWiki\Storage\SlotRecord;
use WikitextContent;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \Kartographer\ApiQueryMapData
 */
class ApiQueryMapDataTest extends ApiTestCase {

	// phpcs:disable Generic.Files.LineLength
	private const MAPFRAME_CONTENT = '<mapframe latitude=0 longitude=0 width=1 height=1>{"type": "Feature","geometry": {"type": "Point","coordinates": [1, 2]}}</mapframe>';
	private const MAPFRAME_JSON = [ '{"_1b3d2dce6411896528c219bf0af1e6c4c3985a1a":[{"type":"Feature","geometry":{"type":"Point","coordinates":[1,2]}}]}' ];
	private const MAPFRAME_CONTENT_OTHER = '<mapframe latitude=0 longitude=0 width=1 height=1>{"type": "Feature","geometry": {"type": "Point","coordinates": [2, 1]}}</mapframe>';
	private const MAPFRAME_JSON_OTHER = [ '{"_6602cbfa7dc93b2b4aa360dadf67eb61bf8792a4":[{"type":"Feature","geometry":{"type":"Point","coordinates":[2,1]}}]}' ];
	// phpcs:enable

	public function testExecuteMissingPage() {
		$apiResult = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => __METHOD__,
		] );

		$this->assertTrue( $apiResult[0]['query']['pages'][-1]['missing'] );
	}

	/**
	 * @dataProvider executeWithTitleProvider
	 */
	public function testExecuteWithTitle( $content, $expectedData ) {
		$page = $this->getExistingTestPage( __METHOD__ );
		$this->addRevision( $page, $content );

		$apiResult = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => $page->getDBkey(),
		] );

		$this->assertResult( $expectedData, $apiResult );
	}

	public function executeWithTitleProvider() {
		return [
			'No mapframe' => [ 'Nope', [ null ] ],
			'Empty mapframe' => [ '<mapframe latitude=0 longitude=0 width=1 height=1 />', [ [ '[]' ] ] ],
			'Map with features' => [ self::MAPFRAME_CONTENT, [ self::MAPFRAME_JSON ] ],
		];
	}

	public function testExecuteWithMultiple() {
		$pageOne = $this->getExistingTestPage( __METHOD__ );
		$oldRevPageOne = $this->addRevision( $pageOne, self::MAPFRAME_CONTENT_OTHER );
		$currRevPageOne = $this->addRevision( $pageOne, self::MAPFRAME_CONTENT );

		$pageTwo = $this->getExistingTestPage( __METHOD__ . '-2' );
		$currRevPageTwo = $this->addRevision( $pageTwo, self::MAPFRAME_CONTENT_OTHER );

		// query two different pages
		$apiResultTitles = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => $pageOne->getDBkey() . '|' . $pageTwo->getDBkey(),
		] );
		$this->assertResult( [ self::MAPFRAME_JSON, self::MAPFRAME_JSON_OTHER ], $apiResultTitles );

		// query a single revision
		$apiResultOneRevision = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $currRevPageOne->getId(),
		] );
		$this->assertResult( [ self::MAPFRAME_JSON ], $apiResultOneRevision );

		// query two different revisions from two differrent pages
		$apiResultRevisions = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $currRevPageOne->getId() . '|' . $currRevPageTwo->getId(),
		] );
		$this->assertResult( [ self::MAPFRAME_JSON, self::MAPFRAME_JSON_OTHER ], $apiResultRevisions );

		// using an old revision from the same page still just returns the data from the latest revision
		$apiResultOldRevision = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $oldRevPageOne->getId(),
		] );
		// with old revids working this should return `[ self::MAPFRAME_JSON_OTHER ]`
		$this->assertResult( [ self::MAPFRAME_JSON ], $apiResultOldRevision );
	}

	private function addRevision( $page, $content ) {
		return $page->newPageUpdater( $this->getTestUser()->getUser() )
			->setContent( SlotRecord::MAIN, new WikitextContent( $content ) )
			->saveRevision( CommentStoreComment::newUnsavedComment( __CLASS__ ) );
	}

	private function assertResult( array $expectedMapdata, $apiResult ) {
		$i = 0;
		foreach ( $apiResult[0]['query']['pages'] as $page ) {
			if ( $expectedMapdata[$i] !== null ) {
				$this->assertArrayHasKey( 'mapdata', $page );
				$this->assertArrayEquals( $expectedMapdata[$i], $page['mapdata'] );
			} else {
				$this->assertArrayNotHasKey( 'mapdata', $page );
			}
			$i++;
		}

		$this->assertSame( count( $expectedMapdata ), $i, 'number of results as expected' );
	}
}
