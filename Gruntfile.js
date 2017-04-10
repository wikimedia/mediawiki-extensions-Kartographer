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
			fix: {
				options: {
					fix: true
				},
				src: [
					'<%= eslint.main %>'
				]
			},
			main: [
				'**/*.js',
				'!node_modules/**',
				'!lib/**',
				'!docs/**'
			]
		},
		banana: conf.MessagesDirs,
		watch: {
			files: [
				'.{stylelintrc}',
				'<%= eslint.main %>'
			],
			tasks: 'test'
		},
		stylelint: {
			core: {
				options: {
					syntax: 'less'
				},
				src: [
					'**/*.css',
					'**/*.less',
					'!node_modules/**',
					'!lib/**',
					'!docs/**'
				]
			}
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'lint', [ 'eslint:main', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'fix', 'eslint:fix' );
	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'default', 'test' );
};
