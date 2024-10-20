<?php

namespace Kartographer\Tag;

use InvalidArgumentException;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use StatusValue;

/**
 * @license MIT
 */
class ErrorReporter {

	/** @var Language|string */
	private $language;

	/**
	 * @param Language|string $languageCode
	 */
	public function __construct( $languageCode ) {
		$this->language = $languageCode;
	}

	/**
	 * @param StatusValue $status
	 * @param string $tag
	 * @return string HTML
	 */
	public function getHtml( StatusValue $status, string $tag ): string {
		$errors = $status->getErrors();
		if ( !$errors ) {
			throw new InvalidArgumentException( 'Attempt to report error when none took place' );
		}

		if ( count( $errors ) > 1 ) {
			$html = '';
			foreach ( $errors as $err ) {
				$html .= Html::rawElement( 'li', [], wfMessage( $err['message'], $err['params'] )
					->inLanguage( $this->language )->parse() ) . "\n";
			}
			$msg = wfMessage( 'kartographer-error-context-multi', "<$tag>" )
				->rawParams( Html::rawElement( 'ul', [], $html ) );
		} else {
			$errorText = wfMessage( $errors[0]['message'], $errors[0]['params'] )
				->inLanguage( $this->language )->parse();
			$msg = wfMessage( 'kartographer-error-context', "<$tag>" )
				->rawParams( $errorText );
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-kartographer-error' ],
			$msg->inLanguage( $this->language )->escaped() .
			$this->getJSONValidatorLog( $status->getValue()['schema-errors'] ?? [] )
		);
	}

	/**
	 * @param array[] $errors
	 * @return string HTML
	 */
	private function getJSONValidatorLog( array $errors ): string {
		if ( !$errors ) {
			return '';
		}

		$log = "\n";
		/** These errors come from {@see \JsonSchema\Constraints\BaseConstraint::addError} */
		foreach ( $errors as $error ) {
			$log .= Html::element( 'li', [],
				$error['pointer'] . wfMessage( 'colon-separator' )->text() . $error['message']
			) . "\n";
		}
		return Html::rawElement( 'ul', [ 'class' => [
			'mw-kartographer-error-log',
			'mw-collapsible',
			'mw-collapsed',
		] ], $log );
	}

}
