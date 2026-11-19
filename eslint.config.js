'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

// Directories with their own config, which other configs don't apply to.
// These were directories with `"root": true` in .eslintrc.json.
const rootDirs = {
	modules: [ 'modules/**' ],
	tests: [ 'tests/qunit/**' ]
};

// Shared by modules and tests (was modules/.eslintrc.json)
const modulesConfig = [
	...wikimedia( 'client', 'jquery', 'mediawiki' ),
	{
		languageOptions: {
			globals: {
				// From `env: { commonjs: true }`
				global: 'readonly',
				L: 'readonly',
				Kartographer: 'readonly'
			}
		},
		rules: {
			'jsdoc/check-tag-names': 'error',
			'jsdoc/require-returns': 'off',
			'jsdoc/require-returns-check': 'off',
			'max-len': 'off',
			'mediawiki/no-unlabeled-buttonwidget': 'warn'
		}
	}
];

module.exports = defineConfig( [
	globalIgnores( [
		'**/lib/',
		'**/docs/',
		'**/vendor/'
	] ),

	// Gruntfile.js, etc.
	{
		ignores: Object.values( rootDirs ).flat(),
		extends: [ wikimedia( 'server' ) ]
	},

	// Code which runs in MediaWiki
	{
		files: rootDirs.modules,
		extends: modulesConfig
	},
	{
		files: [ 'modules/ve-maps/**' ],
		languageOptions: {
			globals: {
				// From `env: { commonjs: false }`
				global: 'off',
				ve: 'readonly'
			}
		}
	},

	// QUnit tests
	{
		files: rootDirs.tests,
		extends: [ modulesConfig, wikimedia( 'qunit' ) ],
		languageOptions: {
			// wikimedia/qunit resets this to 'script'
			sourceType: 'commonjs'
		}
	}
] );
