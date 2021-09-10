/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [
				'**/*.{js,json}',
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
			options: {
				syntax: 'less'
			},
			all: [
				'**/*.{css,less}',
				'!node_modules/**',
				'!lib/**',
				'!docs/**',
				'!vendor/**'
			]
		}
	} );

	grunt.registerTask( 'libcheck', function () {
		var done = this.async();
		// Are there unstaged changes after synchronizing from upstream libraries?
		require( 'child_process' ).exec( 'git ls-files lib/external --modified', function ( err, stdout, stderr ) {
			// Before we try to rebuild lib/external files, let's make sure there aren't any local unstaged changes
			// first in those files, so we don't override uncommitted work
			var ret = err || stderr || stdout;
			if ( ret ) {
				grunt.log.error( 'There are uncommitted changes to external library files. Please change these files upstream, instead.' );
				grunt.log.error( ret );
			} else {
				// Build the lib files and verify there isn't a difference
				require( 'child_process' ).exec( 'npm run build-lib', function () {
					require( 'child_process' ).exec( 'git ls-files lib/external --modified', function ( err, stdout, stderr ) {
						var ret = err || stderr || stdout;
						if ( ret ) {
							grunt.log.error( 'These library files were directly changed. Please change them upstream, instead:' );
							grunt.log.error( ret );
						} else {
							grunt.log.ok( 'Library folder is synchronized with upstream libraries\' states.' );
							done();
						}
					} );
				} );
			}

		} );
	} );

	grunt.registerTask( 'lint', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'test', [ 'lint', 'libcheck' ] );
	grunt.registerTask( 'default', 'test' );
};
