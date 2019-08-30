<?php

namespace Kartographer;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use FormatJson;
use ParserOptions;
use WikiPage;

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
		$continue = $params['continue'] ?? 0;
		$groups = $params['groups'] === '' ? false : explode( '|', $params['groups'] );
		$titles = $this->getPageSet()->getGoodTitles();
		if ( !$titles ) {
			return;
		}

		$count = 0;
		foreach ( $titles as $pageId => $title ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
				break;
			}

			$page = WikiPage::factory( $title );
			$parserOutput = $page->getParserOutput( ParserOptions::newCanonical( 'canonical' ) );
			$state = State::getState( $parserOutput );
			if ( !$state ) {
				continue;
			}
			$data = $state->getData();

			$result = [];
			if ( $groups ) {
				foreach ( $groups as $group ) {
					if ( array_key_exists( $group, $data ) ) {
						$result[$group] = $data[$group];
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
