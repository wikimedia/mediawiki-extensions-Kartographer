<?php

namespace Kartographer\Tests;

use ApiTestCase;
use ApiUsageException;
use FlaggableWikiPage;
use FlaggedRevs;
use FlaggedRevsParserCache;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use ParserOptions;
use Title;
use WikitextContent;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \Kartographer\Api\ApiQueryMapData
 * @license MIT
 */
class ApiQueryMapDataTest extends ApiTestCase {

	private const MAPFRAME_JSON = '{"type":"Feature","geometry":{"type":"Point","coordinates":[1,2],"properties":{}}}';
	private const MAPFRAME_CONTENT = '<mapframe latitude=0 longitude=0 width=1 height=1>' .
		self::MAPFRAME_JSON . '</mapframe>';
	private const MAPFRAME_JSON_OTHER = '{"type":"Feature","geometry":{"type":"Point","coordinates":[2,1]}}';
	private const MAPFRAME_CONTENT_OTHER = '<mapframe latitude=0 longitude=0 width=1 height=1>' .
		self::MAPFRAME_JSON_OTHER . '</mapframe>';
	private const MAPFRAME_NAMED_GROUP = '<mapframe group="foo" latitude=0 longitude=0 width=1 height=1>' .
		self::MAPFRAME_JSON . '</mapframe>';

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'FlaggedRevsAutoReview' => 0,
			'FlaggedRevsNamespaces' => [ NS_MAIN ],
			'FlaggedRevsProtection' => false,
			'KartographerMapServer' => 'http://192.0.2.0',
			'KartographerWikivoyageMode' => true,
			MainConfigNames::LanguageCode => 'qqx',
		] );
	}

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
		/** @var Title $page */
		[ 'title' => $page ] = $this->insertPage( __METHOD__, $content );

		[ $apiResult ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => $page->getDBkey(),
		] );

		$this->assertResult( $expectedData, $apiResult );
	}

	public static function executeWithTitleProvider() {
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
			'Map with named group' => [
				self::MAPFRAME_NAMED_GROUP,
				[ [ '{"foo":[' . self::MAPFRAME_JSON . ']}' ] ]
			],
		];
	}

	public static function provideGroupRequests() {
		return [
			'Test filtering by group' => [
				self::MAPFRAME_NAMED_GROUP,
				'foo',
				[ [ '{"foo":[' . self::MAPFRAME_JSON . ']}' ] ],
			],
			'Test filtering by missing group' => [
				self::MAPFRAME_NAMED_GROUP,
				'missing',
				[ [ '{"missing":null}' ] ],
			],
		];
	}

	/**
	 * Tests the "mpdgroups" parameter.
	 *
	 * @dataProvider provideGroupRequests
	 * @param string $content page content
	 * @param string $groupId filter by this ID
	 * @param array $expected API results
	 */
	public function testGroupFiltering( string $content, string $groupId, array $expected ) {
		/** @var Title $page */
		[ 'title' => $page ] = $this->insertPage( __METHOD__, $content );
		$revId = $page->getLatestRevID();

		[ $result ] = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $revId,
			'mpdgroups' => $groupId,
		] );
		$this->assertResult( $expected, $result );
	}

	public function testQueryFromMultiplePages() {
		$hash = '_' . sha1( '[' . self::MAPFRAME_JSON . ']' );
		$hashOther = '_' . sha1( '[' . self::MAPFRAME_JSON_OTHER . ']' );
		$expected = [ '{"' . $hash . '":[' . self::MAPFRAME_JSON . ']}' ];
		$expectedOther = [ '{"' . $hashOther . '":[' . self::MAPFRAME_JSON_OTHER . ']}' ];

		/** @var Title $pageOne */
		[ 'title' => $pageOne ] = $this->insertPage( __METHOD__, self::MAPFRAME_CONTENT );
		$pageOneRevId = $pageOne->getLatestRevID();

		/** @var Title $pageTwo */
		[ 'title' => $pageTwo ] = $this->insertPage( __METHOD__ . '-2', self::MAPFRAME_CONTENT_OTHER );
		$pageTwoRevId = $pageTwo->getLatestRevID();

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
			'revids' => $pageOneRevId,
		];
		[ $apiResultOneRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expected ], $apiResultOneRevision );

		// query two different revisions from two differrent pages
		$params['revids'] .= '|' . $pageTwoRevId;
		[ $apiResultRevisions ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expected, $expectedOther ], $apiResultRevisions );
	}

	public function testQueryFromOldRevision() {
		$hashOther = '_' . sha1( '[' . self::MAPFRAME_JSON_OTHER . ']' );
		$expectedOther = [ '{"' . $hashOther . '":[' . self::MAPFRAME_JSON_OTHER . ']}' ];

		/** @var Title $page */
		[ 'title' => $page ] = $this->insertPage( __METHOD__, self::MAPFRAME_CONTENT_OTHER );
		$oldRevId = $page->getLatestRevID();
		// Add a second revision to make the previous revision old
		$this->addRevision( $page, self::MAPFRAME_CONTENT );

		// Requesting an old revision returns historical data
		$params = [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $oldRevId,
		];
		[ $apiResultOldRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expectedOther ], $apiResultOldRevision );
		$this->assertSame(
			[ $params['revids'] ],
			array_column( $apiResultOldRevision['query']['pages'], 'revid' ),
			'revid appears in API response'
		);
	}

	public function testConflictingRevisionIds() {
		/** @var Title $page */
		[ 'title' => $page ] = $this->insertPage( __METHOD__, self::MAPFRAME_CONTENT_OTHER );
		$oldRevId = $page->getLatestRevID();
		$currRev = $this->addRevision( $page, self::MAPFRAME_CONTENT );

		$params = [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $oldRevId . '|' . $currRev->getId(),
		];

		// Requesting multiple revisions from the same page is intentionally not supported
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( $params );
	}

	public function testStableAndLatest() {
		$this->markTestSkippedIfExtensionNotLoaded( 'FlaggedRevs' );

		$hashStable = '_' . sha1( '[' . self::MAPFRAME_JSON . ']' );
		$hashLatest = '_' . sha1( '[' . self::MAPFRAME_JSON_OTHER . ']' );
		$expectedStable = [ '{"' . $hashStable . '":[' . self::MAPFRAME_JSON . ']}' ];
		$expectedLatest = [ '{"' . $hashLatest . '":[' . self::MAPFRAME_JSON_OTHER . ']}' ];

		$page = $this->getExistingTestPage( __METHOD__ );

		$stableRevision = $this->addRevision( $page, self::MAPFRAME_CONTENT );
		FlaggedRevs::autoReviewEdit(
			$page,
			$this->getTestUser()->getUser(),
			$stableRevision
		);

		// Set up the latest revision
		$this->addRevision( $page, self::MAPFRAME_CONTENT_OTHER );

		$cache = $this->createNoOpMock( FlaggedRevsParserCache::class, [ 'get' ] );
		// Assert that the stable cache is only used once, i.e. not for the latest revision.
		$cache->expects( $this->once() )
			->method( 'get' )
			->willReturn( $page->getParserOutput( ParserOptions::newFromAnon(), $stableRevision->getId() ) );
		$this->setService( 'FlaggedRevsParserCache', $cache );

		// Test the latest revision.
		$params = [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $page->getLatest(),
		];
		[ $apiResultLatestRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expectedLatest ], $apiResultLatestRevision );

		// Test the stable revision.
		$flaggedRevision = FlaggableWikiPage::newInstance( $page )->getStableRev();
		if ( !$flaggedRevision ) {
			$this->markTestIncomplete( 'T312517' );
		}
		$params = [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $flaggedRevision->getRevId(),
		];
		[ $apiResultStableRevision ] = $this->doApiRequest( $params );
		$this->assertResult( [ $expectedStable ], $apiResultStableRevision );
	}

	private function addRevision( PageIdentity $page, string $wikitext ): ?RevisionRecord {
		$status = $this->editPage( $page, new WikitextContent( $wikitext ) );
		return $status->getValue()['revision-record'];
	}

	private function assertResult( array $expectedMapData, array $apiResult ) {
		$this->assertSame(
			$expectedMapData,
			array_column( $apiResult['query']['pages'], 'mapdata' )
		);
	}
}
