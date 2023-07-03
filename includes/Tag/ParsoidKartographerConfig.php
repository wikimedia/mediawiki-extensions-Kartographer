<?php

namespace Kartographer\Tag;

use MediaWiki\MediaWikiServices;
use Wikimedia\Parsoid\Ext\ExtensionModule;

/**
 * @license MIT
 */
class ParsoidKartographerConfig implements ExtensionModule {

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getConfig(): array {
		$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
		if ( $mainConfig->get( 'KartographerParsoidSupport' ) ) {
			return [
				'name' => 'Kartographer',
				'tags' => [
					[
						'name' => 'maplink',
						'handler' => ParsoidMapLink::class,
						'options' => [
							'outputHasCoreMwDomSpecMarkup' => true,
							'wt2html' => [
								'embedsHTMLInAttributes' => true
							]
						],
					],
					[
						'name' => 'mapframe',
						'handler' => ParsoidMapFrame::class,
						'options' => [
							'outputHasCoreMwDomSpecMarkup' => true,
							'wt2html' => [
								'embedsHTMLInAttributes' => true
							]
						],
					]
				],
				'domProcessors' => [ ParsoidDomProcessor::class ]
			];
		} else {
			return [
				'name' => 'Kartographer',
			];
		}
	}
}
