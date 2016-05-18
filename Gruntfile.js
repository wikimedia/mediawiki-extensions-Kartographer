/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: [
				'**/*.js',
				'!node_modules/**',
				'!lib/**'
			]
		},
		jscs: {
			options: {
				config: '.jscsrc',
				verbose: true
			},
			fix: {
				options: {
					fix: true
				},
				src: '<%= jshint.all %>'
			},
			main: {
				src: '<%= jshint.all %>'
			}
		},
		banana: {
			all: 'i18n/'
		},
		watch: {
			files: [
				'.{stylelintrc,jscsrc,jshintignore,jshintrc}',
				'<%= jshint.all %>'
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
					'!lib/**'
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

	grunt.registerTask( 'lint', [ 'jshint', 'jscs:main', 'stylelint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'fix', 'jscs:fix' );
	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'default', 'test' );
};
