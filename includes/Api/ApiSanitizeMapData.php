<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 * @author Max Semenik
 */

namespace Kartographer\Api;

use Kartographer\MediaWikiWikitextParser;
use Kartographer\SimpleStyleParser;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Json\FormatJson;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class implements action=sanitize-mapdata API, validating and sanitizing user-entered
 * GeoJSON.
 *
 * @license MIT
 */
class ApiSanitizeMapData extends ApiBase {

	public function __construct(
		ApiMain $main,
		string $action,
		private readonly ParserFactory $parserFactory,
	) {
		parent::__construct( $main, $action );
	}

	/** @inheritDoc */
	public function execute() {
		$params = $this->extractRequestParams();

		$title = Title::newFromText( $params['title'] );

		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['title'] ) ] );
		}

		$this->checkTitleUserPermissions( $title, 'read' );

		$this->sanitizeJson( $title, $params['text'] );
	}

	/**
	 * @param Title $title
	 * @param string $text
	 */
	private function sanitizeJson( Title $title, string $text ): void {
		$parserOptions = new ParserOptions( $this->getUser() );
		$parser = $this->parserFactory->getInstance();
		$parser->startExternalParse( $title, $parserOptions, Parser::OT_HTML );
		$parser->setPage( $title );
		$simpleStyle = new SimpleStyleParser(
			new MediaWikiWikitextParser( $parser ),
			$this->getConfig(),
			[ 'saveUnparsed' => true ]
		);
		$status = $simpleStyle->parse( $text );
		if ( !$status->isOK() ) {
			$error = Status::wrap( $status )->getHTML( false, false, $this->getLanguage() );
			$this->getResult()->addValue( null, $this->getModuleName(), [ 'error' => $error ] );
		} else {
			$data = $status->getValue()['data'];
			SimpleStyleParser::updateMarkerSymbolCounters( $data );
			$this->getResult()
				->addValue( null,
					$this->getModuleName(),
					[ 'sanitized' => FormatJson::encode( $data, false, FormatJson::ALL_OK ) ]
				);
		}
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => 'Dummy title (called from ' . __CLASS__ . ')',
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=sanitize-mapdata&text={"foo":"bar"}' => 'apihelp-sanitize-mapdata-example',
		];
	}

	/**
	 * Indicate that this API can change at any time
	 * @return bool
	 */
	public function isInternal() {
		return true;
	}
}
