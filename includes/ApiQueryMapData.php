<?php

namespace Kartographer;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use FormatJson;
use MediaWiki\Page\WikiPageFactory;
use ParserOptions;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class ApiQueryMapData extends ApiQueryBase {

	/** @var WikiPageFactory */
	private $pageFactory;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param WikiPageFactory $pageFactory
	 */
	public function __construct( ApiQuery $query, $moduleName,
		WikiPageFactory $pageFactory
	) {
		parent::__construct( $query, $moduleName, 'mpd' );
		$this->pageFactory = $pageFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$limit = $params['limit'];
		$groupIds = $params['groups'] === '' ? false : explode( '|', $params['groups'] );
		$titles = $this->getPageSet()->getGoodPages();
		if ( !$titles ) {
			return;
		}

		$revIds = [];
		// Temporary feature flag to control whether we support fetching mapdata from older revisions
		if ( $this->getConfig()->get( 'KartographerVersionedMapdata' ) ) {
			$revisionToPageMap = $this->getPageSet()->getLiveRevisionIDs();
			$revIds = array_flip( $revisionToPageMap );
			// Note: It's probably possible to merge data from multiple revisions of the same page
			// because of the way group IDs are unique. Intentionally not implemented yet.
			if ( count( $revisionToPageMap ) > count( $revIds ) ) {
				$this->dieWithError( 'apierror-kartographer-conflicting-revids' );
			}
		}

		$count = 0;
		foreach ( $titles as $pageId => $title ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
				break;
			}

			$revId = $revIds[$pageId] ?? null;
			if ( $revId ) {
				// This isn't strictly needed, but the only way a consumer can distinguish an
				// endpoint that supports revids from an endpoint that doesn't
				$this->getResult()->addValue( [ 'query', 'pages', $pageId ], 'revid', $revId );
			}

			$page = $this->pageFactory->newFromTitle( $title );
			$parserOutput = $page->getParserOutput( ParserOptions::newFromAnon(), $revId );
			$state = $parserOutput ? State::getState( $parserOutput ) : null;
			if ( !$state ) {
				continue;
			}
			$data = $state->getData();

			$result = [];
			if ( $groupIds ) {
				foreach ( $groupIds as $groupId ) {
					if ( array_key_exists( $groupId, $data ) ) {
						$result[$groupId] = $data[$groupId];
					} else {
						// Let the client know there is no data found for this group
						$result[$groupId] = null;
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
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'limit' => [
				ParamValidator::PARAM_TYPE => 'limit',
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'continue' => [
				ParamValidator::PARAM_TYPE => 'integer',
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

	/**
	 * @inheritDoc
	 */
	public function isInternal() {
		return true;
	}
}
