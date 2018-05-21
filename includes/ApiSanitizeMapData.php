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
use FormatJson;
use Parser;
use ParserOptions;
use stdClass;
use Title;

/**
 * This class implements action=sanitize-mapdata API, validating and sanitizing user-entered
 * GeoJSON.
 * @package Kartographer
 */
class ApiSanitizeMapData extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();

		$title = Title::newFromText( $params['title'] );

		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['title'] ) ] );
		}

		$this->checkTitleUserPermissions( $title, 'read' );

		$this->sanitizeJson( $title, $params['text'] );
	}

	private function sanitizeJson( Title $title, $text ) {
		/** @var Parser $wgParser */
		global $wgParser;

		$parser = $wgParser->getFreshParser();
		$parserOptions = new ParserOptions( $this->getUser() );
		$parser->startExternalParse( $title, $parserOptions, Parser::OT_HTML );
		$parser->setTitle( $title );
		$parser->clearState();
		$simpleStyle = new SimpleStyleParser( $parser, null, [ 'saveUnparsed' => true ] );
		$status = $simpleStyle->parse( $text );
		if ( !$status->isOK() ) {
			$error = $status->getHTML( false, false, $this->getLanguage() );
			$this->getResult()->addValue( null, $this->getModuleName(), [ 'error' => $error ] );
		} else {
			$data = $status->getValue();
			$counters = new stdClass();
			SimpleStyleParser::doCountersRecursive( $data, $counters );
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
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => 'Dummy title (called from ' . __CLASS__ . ')',
			],
			'text' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			]
		];
	}

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
