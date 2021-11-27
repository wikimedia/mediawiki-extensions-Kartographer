<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 * @author Max Semenik
 */

namespace Kartographer;

use ApiBase;
use ApiMain;
use FormatJson;
use Parser;
use ParserOptions;
use Title;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class implements action=sanitize-mapdata API, validating and sanitizing user-entered
 * GeoJSON.
 * @package Kartographer
 */
class ApiSanitizeMapData extends ApiBase {

	/** @var Parser */
	private $parser;

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param Parser $parser
	 */
	public function __construct(
		ApiMain $main,
		$action,
		Parser $parser
	) {
		parent::__construct( $main, $action );
		$this->parser = $parser;
	}

	/**
	 * @inheritDoc
	 */
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
	private function sanitizeJson( Title $title, $text ) {
		$parserOptions = new ParserOptions( $this->getUser() );
		$this->parser->startExternalParse( $title, $parserOptions, Parser::OT_HTML );
		$this->parser->setPage( $title );
		$this->parser->clearState();
		$simpleStyle = new SimpleStyleParser( $this->parser, null, [ 'saveUnparsed' => true ] );
		$status = $simpleStyle->parse( $text );
		if ( !$status->isOK() ) {
			$error = $status->getHTML( false, false, $this->getLanguage() );
			$this->getResult()->addValue( null, $this->getModuleName(), [ 'error' => $error ] );
		} else {
			$data = $status->getValue();
			SimpleStyleParser::updateMarkerSymbolCounters( $data );
			$this->getResult()
				->addValue( null,
					$this->getModuleName(),
					[ 'sanitized' => FormatJson::encode( $data, false, FormatJson::ALL_OK ) ]
				);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => 'Dummy title (called from ' . __CLASS__ . ')',
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
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
