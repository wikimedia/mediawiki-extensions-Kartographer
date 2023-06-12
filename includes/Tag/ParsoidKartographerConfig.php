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
						'outputHasCoreMwDomSpecMarkup' => true
					],
				],
				[
					'name' => 'mapframe',
					'handler' => ParsoidMapFrame::class,
					'options' => [
						'outputHasCoreMwDomSpecMarkup' => true
					],
				]
			],
			'domProcessors' => [ ParsoidDomProcessor::class ]
		];
	}
}
