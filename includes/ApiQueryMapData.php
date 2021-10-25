<?php

namespace Kartographer;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use FormatJson;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class ApiQueryMapData extends ApiQueryBase {

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'mpd' );
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
		// Temporary feature flag to control whether we support fetching mapdata from older revisions.
		$revIds = [];
		if ( $this->getConfig()->get( 'KartographerVersionedMapdata' ) ) {
			$revIds = $this->getPageSet()->getLiveRevisionIDs();
		}

		$pageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		// `revids` and `titles` can't be used simultaneously, we're either using revs or titles
		$revIdMode = (bool)count( $revIds );

		if ( !$revIdMode ) {
			foreach ( array_keys( $titles ) as $pageId ) {
				$rev = $revisionLookup->getRevisionByPageId( $pageId );
				$revIds[ $rev->getId() ] = $pageId;
			}
		}

		$count = 0;
		foreach ( $revIds as $revId => $pageId ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
				break;
			}
			$revison = $revisionLookup->getRevisionById( $revId );
			$page = $pageFactory->newFromLinkTarget( $revison->getPageAsLinkTarget() );
			$parserOutput = $page->getParserOutput( ParserOptions::newCanonical( 'canonical' ), $revId );
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

			// FIXME results are added by pageId, if revIds belong to the same pageId the mapdata
			// will be in one list of results
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
