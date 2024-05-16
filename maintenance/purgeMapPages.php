<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Title\Title;

/**
 * Purges all pages that use <maplink> or <mapframe>, using the tracking category.
 *
 * @license MIT
 */
class PurgeMapPages extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge all pages that use <maplink> or <mapframe>.' );
		$this->addOption( 'dry-run', 'Only print page names, do not purge them' );
		$this->setBatchSize( 100 );
		$this->requireExtension( 'Kartographer' );
	}

	/** @inheritDoc */
	public function execute() {
		$categoryMessage = wfMessage( 'kartographer-tracking-category' );
		if ( $categoryMessage->isDisabled() ) {
			$this->error( "Tracking category for maps pages is disabled\n" );
			return;
		}
		$categoryTitle = Title::makeTitle( NS_CATEGORY, $categoryMessage->inContentLanguage()->text() );
		$dryRun = $this->hasOption( 'dry-run' );
		$iterator = new BatchRowIterator(
			$this->getReplicaDB(),
			[ 'categorylinks', 'page' ],
			[ 'cl_type', 'cl_sortkey', 'cl_from' ],
			$this->getBatchSize()
		);
		$iterator->addConditions( [ 'cl_to' => $categoryTitle->getDBkey() ] );
		$iterator->addJoinConditions( [ 'page' => [ 'INNER JOIN', [ 'page_id=cl_from' ] ] ] );
		$iterator->setFetchColumns( [ 'page_id', 'page_namespace', 'page_title' ] );
		$iterator->setCaller( __METHOD__ );

		$pages = 0;
		$failures = 0;
		$wikiPageFactory = $this->getServiceContainer()->getWikiPageFactory();
		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				$title = Title::newFromRow( $row );
				if ( $dryRun ) {
					$this->output( $title->getPrefixedText() . "\n" );
				} else {
					$page = $wikiPageFactory->newFromTitle( $title );
					if ( $page->doPurge() ) {
						$this->output( "Purged {$title->getPrefixedText()}\n" );
					} else {
						$this->error( "FAILED TO PURGE {$title->getPrefixedText()}\n" );
						$failures++;
					}
				}
			}
			$pages += count( $batch );
		}
		$this->output( "\nFinished. Total: $pages pages. Failed: $failures.\n" );
	}
}

$maintClass = PurgeMapPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
