<?php
/**
 *
 * @license MIT
 * @file
 *
 * @author Yuri Astrakhan
 */

namespace Kartographer;

use ApiBase;
use FormatJson;
use Title;

/**
 * This class implements action=kartographer api, allowing client-side map drawing.
 * Class ApiKartographer
 * @package Kartographer
 */
class ApiKartographer extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();

		$title = Title::newFromText( $params['title'] );
		if ( !$title || !$title->exists() || !$title->userCan( 'read', $this->getUser() ) ) {
			$this->dieUsage( "Invalid title given.", "invalidtitle" );
		}

		$ppValue = $this->getDB()->selectField( 'page_props', 'pp_value', array(
			'pp_page' => $title->getArticleID(),
			'pp_propname' => 'kartographer',
		), __METHOD__ );

		$result = array();
		if ( $ppValue ) {
			$st = FormatJson::parse( gzdecode( $ppValue ) );
			if ( $st->isOK() ) {
				// NOTE: This code should be in-sync with kartographer.js
				// Given a list of group names, add them in the same order to the result
				// Assumes $data is always an array (not object)
				$data = $st->getValue();
				$groups = $params['group'];
				foreach ( $groups as $group ) {
					if ( $group === '*' ) {
						// Wildcard - include all groups that are NOT private (no '_' prefix)
						foreach ( $data as $g => $d ) {
							if ( $g[0] !== '_' ) {
								$result = array_merge( $result, $d );
							}
						}
					} elseif ( in_array( $group, $data ) ) {
						$result = array_merge( $result, $data[$group] );
					}
				}
			}
		}
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return array(
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'group' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_DFLT => '*',
				ApiBase::PARAM_ISMULTI => true,
			),
		);
	}

	protected function getExamplesMessages() {
		return array(
			'action=kartographer&title=Page' => 'apihelp-kartographer-example',
		);
	}
}
