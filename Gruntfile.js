/* eslint-env node */
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
					'!modules/ve-maps/**',
					'!node_modules/**',
					'!lib/**',
					'!docs/**'
				]
			},
			've-maps': {
				options: {
					configFile: 'modules/ve-maps/.stylelintrc'
				},
				src: [
					'modules/ve-maps/**/*.css'
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
