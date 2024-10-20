<?php

namespace Kartographer\Api;

use FlaggableWikiPage;
use FlaggedRevs;
use FlaggedRevsParserCache;
use Kartographer\State;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Registration\ExtensionRegistry;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * @license MIT
 */
class ApiQueryMapData extends ApiQueryBase {

	private WikiPageFactory $pageFactory;
	private ?FlaggedRevsParserCache $parserCache;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param WikiPageFactory $pageFactory
	 * @param FlaggedRevsParserCache|null $parserCache
	 */
	public function __construct( ApiQuery $query, $moduleName,
		WikiPageFactory $pageFactory,
		?FlaggedRevsParserCache $parserCache
	) {
		parent::__construct( $query, $moduleName, 'mpd' );
		$this->pageFactory = $pageFactory;
		$this->parserCache = $parserCache;
	}

	/** @inheritDoc */
	public function execute() {
		$params = $this->extractRequestParams();
		$limit = $params['limit'];
		$groupIds = $params['groups'] === '' ? [] : explode( '|', $params['groups'] );
		$titles = $this->getPageSet()->getGoodPages();
		if ( !$titles ) {
			return;
		}

		$revisionToPageMap = $this->getPageSet()->getLiveRevisionIDs();
		$revIds = array_flip( $revisionToPageMap );
		// Note: It's probably possible to merge data from multiple revisions of the same page
		// because of the way group IDs are unique. Intentionally not implemented yet.
		if ( count( $revisionToPageMap ) > count( $revIds ) ) {
			$this->dieWithError( 'apierror-kartographer-conflicting-revids' );
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

			$parserOutput = $this->getParserOutput( $title, $revId );
			$state = $parserOutput ? State::getState( $parserOutput ) : null;
			if ( !$state ) {
				continue;
			}
			$data = $state->getData();

			$result = $this->filterGroups( $data, $groupIds, $revId !== null );
			$this->normalizeGeoJson( $result );
			$result = FormatJson::encode( $result, false, FormatJson::ALL_OK );

			$fit = $this->addPageSubItem( $pageId, $result );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $pageId );
			}
		}
	}

	/**
	 * @param array<string,array> $data All groups
	 * @param string[] $groupIds requested groups or empty to disable filtering
	 * @param bool $isStrict If true, log missing groups
	 * @return array<string,?array> Filtered groups, with the same keys as $data
	 */
	private function filterGroups( array $data, array $groupIds, bool $isStrict ): array {
		if ( !$groupIds ) {
			return $data;
		}
		return array_reduce( $groupIds,
			static function ( $result, $groupId ) use ( $data, $isStrict ) {
				if ( array_key_exists( $groupId, $data ) ) {
					$result[$groupId] = $data[$groupId];
				} else {
					// Temporary logging, remove when not needed any more
					if ( $isStrict && str_starts_with( $groupId, '_' ) ) {
						LoggerFactory::getInstance( 'Kartographer' )->notice( 'Group id not found in revision' );
					}

					// Let the client know there is no data found for this group
					$result[$groupId] = null;
				}
				return $result;
			}, [] );
	}

	/**
	 * ExtensionData are stored as serialized JSON strings and deserialized with
	 * {@see FormatJson::FORCE_ASSOC} set, see {@see JsonCodec::unserialize}. This means empty
	 * objects are serialized as "{}" but deserialized as empty arrays. We need to revert this.
	 * Luckily we know everything about the data that can end here: thanks to
	 * {@see SimpleStyleParser} it's guaranteed to be valid GeoJSON.
	 *
	 * @param array &$data
	 */
	private function normalizeGeoJson( array &$data ): void {
		foreach ( $data as $key => &$value ) {
			// Properties that must be objects according to schemas/geojson.json
			if ( $value === [] && ( $key === 'geometry' || $key === 'properties' ) ) {
				$value = (object)[];
			} elseif ( is_array( $value ) ) {
				// Note: No need to dive deeper when objects are deserialized as objects.
				$this->normalizeGeoJson( $value );
			}
		}
	}

	/** @inheritDoc */
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

	/** @inheritDoc */
	public function getExamplesMessages() {
		return [
			'action=query&prop=mapdata&titles=Metallica' => 'apihelp-query+mapdata-example-1',
			'action=query&prop=mapdata&titles=Metallica&mpdgroups=group1|group2'
				=> 'apihelp-query+mapdata-example-2',
		];
	}

	/** @inheritDoc */
	public function getCacheMode( $params ) {
		return 'public';
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/**
	 * Wrap parsing logic to accomplish a cache workaround
	 *
	 * TODO: Once T307342 is resolved, MediaWiki core will be able to dynamically select the
	 * correct cache.  Until then, we're explicitly using the FlaggedRevs stable-revision cache to
	 * avoid an unnecessary parse, and to avoid polluting the RevisionOutputCache.
	 *
	 * @param PageIdentity $title
	 * @param int|null $requestedRevId
	 *
	 * @return ParserOutput|false
	 */
	private function getParserOutput( PageIdentity $title, ?int $requestedRevId ) {
		$parserOptions = ParserOptions::newFromAnon();

		if ( ExtensionRegistry::getInstance()->isLoaded( 'FlaggedRevs' ) && $this->parserCache ) {
			$page = FlaggableWikiPage::newInstance( $title );
			$isOldRev = $requestedRevId && $requestedRevId !== $page->getLatest();
			$latestRevMayBeSpecial = FlaggedRevs::inclusionSetting() === FR_INCLUDES_STABLE;

			if ( $isOldRev || $latestRevMayBeSpecial ) {
				$requestedRevId = $requestedRevId ?: $page->getLatest();
				if ( $requestedRevId === $page->getStable() ) {
					// This is the stable revision, so we need to use the special FlaggedRevs cache.
					$parserOutput = $this->parserCache->get( $page, $parserOptions );
					if ( $parserOutput ) {
						return $parserOutput;
					}
				}
			}
		} else {
			$page = $this->pageFactory->newFromTitle( $title );
		}

		// This is the line that will replace the whole function, once the workaround can be
		// removed.
		//
		// Note: This might give slightly different results than a FlaggedRevs parse of the stable
		// revision, for example a mapframe template will use its latest revision rather than the
		// stable template revision.
		return $page->getParserOutput( $parserOptions, $requestedRevId );
	}

}
