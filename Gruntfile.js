/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-svgmin' );

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
					syntax: 'less',
					formatter: 'verbose'
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
		},
		// SVG Optimization
		svgmin: {
			options: {
				js2svg: {
					pretty: true,
					multipass: true
				},
				plugins: [ {
					cleanupIDs: false
				}, {
					removeDesc: false
				}, {
					removeRasterImages: true
				}, {
					removeTitle: false
				}, {
					removeViewBox: false
				}, {
					removeXMLProcInst: false
				}, {
					sortAttrs: true
				} ]
			},
			all: {
				files: [ {
					expand: true,
					cwd: 'styles/images',
					src: [
						'**/*.svg'
					],
					dest: 'styles/images/',
					ext: '.svg'
				} ]
			}
		}
	} );

	grunt.registerTask( 'lint', [ 'eslint', 'stylelint', 'jsonlint', 'banana', 'svgmin' ] );
	grunt.registerTask( 'test', 'lint' );
	grunt.registerTask( 'default', 'test' );
};
