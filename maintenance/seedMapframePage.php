<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\TextContent;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * Seeds a development page with a sample <mapframe> tag for Kartographer.
 *
 * @license MIT
 */
class SeedMapframePage extends Maintenance {
	private const PAGE_TITLE = 'Extension/Kartographer/Mapframe';
	private const PAGE_CONTENT =
		'<mapframe text="[[wikipedia:Stockholm|Stockholm]] in Wikipedia" ' .
		'width=250 height=250 zoom=7 latitude="59" longitude="18" />';
	private const EDIT_SUMMARY = 'Seed Kartographer mapframe development content';

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Create or reset a development page containing a sample <mapframe> tag.' );
		$this->requireExtension( 'Kartographer' );
	}

	/** @inheritDoc */
	public function execute() {
		$title = Title::newFromText( self::PAGE_TITLE );
		if ( !$title ) {
			$this->fatalError( 'Invalid title: ' . self::PAGE_TITLE );
		}

		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		if ( !$user ) {
			$this->fatalError( 'Failed to initialize maintenance script user.' );
		}

		$page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$existingRevision = $page->getRevisionRecord();
		if ( $existingRevision ) {
			$existingContent = $existingRevision->getContent( SlotRecord::MAIN );
			if ( $existingContent instanceof TextContent && $existingContent->getText() === self::PAGE_CONTENT ) {
				$this->output( 'Page already has the expected content: ' . self::PAGE_TITLE . "\n" );
				return;
			}
		}

		$newContent = ContentHandler::makeContent( self::PAGE_CONTENT, $title );
		$updater = $page->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $newContent );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( self::EDIT_SUMMARY ) );

		$status = $updater->getStatus();
		if ( !$status->isOK() ) {
			$this->fatalError( $status );
		}

		if ( $existingRevision ) {
			$this->output( 'Updated page content: ' . self::PAGE_TITLE . "\n" );
		} else {
			$this->output( 'Created page: ' . self::PAGE_TITLE . "\n" );
		}
	}
}

$maintClass = SeedMapframePage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
