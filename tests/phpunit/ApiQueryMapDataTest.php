<?php

namespace Kartographer\Tests;

use ApiTestCase;
use ApiUsageException;
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
		$this->markTestSkipped( 'T302360' );

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
		$params = [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $currRevPageOne->getId(),
		];
		[ $apiResultOneRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expected ], $apiResultOneRevision );

		// query two different revisions from two differrent pages
		$params['revids'] .= '|' . $currRevPageTwo->getId();
		[ $apiResultRevisions ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expected, $expectedOther ], $apiResultRevisions );

		// Requesting an old revision returns historical data
		$this->setMwGlobals( 'wgKartographerVersionedMapdata', true );
		$params['revids'] = $oldRevPageOne->getId();
		[ $apiResultOldRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expectedOther ], $apiResultOldRevision );
		$this->assertSame(
			[ $params['revids'] ],
			array_column( $apiResultOldRevision['query']['pages'], 'revid' ),
			'revid appears in API response'
		);

		// Legacy behavior is to always return data from the latest revision, no matter if the
		// requested revision is a historical one
		$this->setMwGlobals( 'wgKartographerVersionedMapdata', false );
		[ $apiResultOldRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expected ], $apiResultOldRevision );
		$this->assertArrayNotHasKey( 'revid', reset( $apiResultOldRevision['query']['pages'] ),
			'revid does not appear in legacy API response'
		);

		// Using multiple revision IDs in legacy mode doesn't make a difference
		$params['revids'] .= '|' . $currRevPageOne->getId();
		[ $apiResultOldRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expected ], $apiResultOldRevision );

		// Requesting multiple revisions from the same page is intentionally not supported
		$this->setMwGlobals( 'wgKartographerVersionedMapdata', true );
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( $params );
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
