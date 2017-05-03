/* globals module, require */

( function () {
	var cjs = require( 'rollup-plugin-commonjs' );

	module.exports = {
		entry: 'node_modules/wikimedia-mapdata/src/index.js',
		format: 'cjs',
		plugins: [ cjs() ],
		dest: 'lib/wikimedia-mapdata.js'
	};
}() );
