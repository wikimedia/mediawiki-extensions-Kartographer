<?php

namespace Kartographer\Tag;

use MediaWiki\Config\Config;
use Wikimedia\Parsoid\Ext\ExtensionModule;

/**
 * @license MIT
 */
class ParsoidKartographerConfig implements ExtensionModule {

	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getConfig(): array {
		if ( $this->config->get( 'KartographerParsoidSupport' ) ) {
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
					] ],
				],
			];
		} else {
			return [
				'name' => 'Kartographer',
			];
		}
	}
}
