<?php

namespace Kartographer\Tag;

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
					'handler' => [
						'class' => ParsoidMapFrame::class,
						'services' => [
							'MainConfig'
						],
					],
					'options' => [
						'outputHasCoreMwDomSpecMarkup' => true,
						'wt2html' => [
							'embedsHTMLInAttributes' => true
						]
					],
				]
			],
			'domProcessors' => [ [
				'class' => ParsoidDomProcessor::class,
				'services' => [
					'MainConfig',
					'TrackingCategories',
				] ],
			],
		];
	}
}
