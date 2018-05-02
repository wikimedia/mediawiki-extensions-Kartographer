<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Purges all pages that use <maplink> or <mapframe>, using the tracking category.
 */
class PurgeMapPages extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge all pages that use <maplink> or <mapframe>.' );
		$this->addOption( 'dry-run', 'Only print page names, do not purge them', false, false );
		$this->setBatchSize( 100 );
	}

	public function execute() {
		$categoryMessage = wfMessage( 'kartographer-tracking-category' );
		if ( $categoryMessage->isDisabled() ) {
			$this->error( "Tracking category for maps pages is disabled\n" );
			return;
		}
		$categoryTitle = Title::makeTitle( NS_CATEGORY, $categoryMessage->inContentLanguage()->text() );
		$dryRun = $this->hasOption( 'dry-run' );
		$iterator = new BatchRowIterator(
			wfGetDB( DB_REPLICA ),
			[ 'categorylinks', 'page' ],
			[ 'cl_type', 'cl_sortkey', 'cl_from' ],
			$this->getBatchSize()
		);
		$iterator->addConditions( [ 'cl_to' => $categoryTitle->getDBkey() ] );
		$iterator->addJoinConditions( [ 'page' => [ 'INNER JOIN', [ 'page_id=cl_from' ] ] ] );
		$iterator->setFetchColumns( [ 'page_id', 'page_namespace', 'page_title' ] );

		$pages = 0;
		$failures = 0;
		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				$title = Title::newFromRow( $row );
				if ( $dryRun ) {
					$this->output( $title->getPrefixedText() . "\n" );
				} else {
					$page = WikiPage::factory( $title );
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
