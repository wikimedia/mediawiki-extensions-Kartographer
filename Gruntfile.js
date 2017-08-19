/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			all: [
				'**/*.js',
				'!node_modules/**',
				'!lib/**',
				'!docs/**',
				'!vendor/**'
			]
		},
		banana: conf.MessagesDirs,
		watch: {
			files: [
				'.{stylelintrc}.json',
				'<%= eslint.alll %>'
			],
			tasks: 'test'
		},
		stylelint: {
			all: {
				options: {
					syntax: 'less'
				},
				src: [
					'**/*.{css,less}',
					'!node_modules/**',
					'!lib/**',
					'!docs/**',
					'!vendor/**'
				]
			}
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'lint', [ 'eslint', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'default', 'test' );
};
