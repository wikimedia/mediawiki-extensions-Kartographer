<?php

namespace Kartographer;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use FormatJson;

class ApiQueryMapData extends ApiQueryBase {

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'mpd' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$limit = $params['limit'];
		$continue = isset( $params['continue'] ) ? $params['continue'] : 0;
		$groups = $params['groups'] === '' ? false : explode( '|', $params['groups'] );
		$titles = $this->getPageSet()->getGoodTitles();
		if ( !$titles ) {
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$this->addTables( 'page_props' );
		$this->addFields( [ 'pp_page', 'pp_value' ] );
		$this->addWhere( [ 'pp_page' => array_keys( $titles ), 'pp_propname' => 'kartographer' ] );
		$this->addWhereIf( 'pp_page >= ' . $dbr->addQuotes( $continue ), $continue );
		$this->addOption( 'ORDER BY', 'pp_page' );
		$this->addOption( 'LIMIT', $limit + 1 );

		$res = $this->select( __METHOD__ );

		$count = 0;
		foreach ( $res as $row ) {
			$pageId = $row->pp_page;
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
				break;
			}
			$status = FormatJson::parse( gzdecode( $row->pp_value ) );
			$data = $status->getValue();
			if ( !$status->isOK() || !is_object( $data ) ) {
				continue;
			}

			$result = [];
			if ( $groups ) {
				foreach ( $groups as $group ) {
					if ( property_exists( $data, $group ) ) {
						$result[$group] = $data->$group;
					} else {
						// Let the client know there is no data found for this group
						$result[$group] = null;
					}
				}
			} else {
				$result = $data;
			}
			$result = FormatJson::encode( $result, false, FormatJson::ALL_OK );

			$fit = $this->addPageSubItem( $pageId, $result );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'groups' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => '',
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'continue' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getExamplesMessages() {
		return [
			'action=query&prop=mapdata&titles=Metallica' => 'apihelp-query+mapdata-example-1',
			'action=query&prop=mapdata&titles=Metallica&mpdgroups=group1|group2'
				=> 'apihelp-query+mapdata-example-2',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheMode( $params ) {
		return 'public';
	}

	public function isInternal() {
		return true;
	}
}
