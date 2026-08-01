/**
 * One webpack entry per `blocks/<name>/index.js`.
 *
 * The previous `wp-scripts build blocks/*​/index.js --output-path=build` glob
 * collapsed every block into a single `build/index.js` — with more than one
 * block, all but the first were silently dropped, with no error. Each block
 * needs its own bundle *and* its own `index.asset.php`, because that asset
 * file is what lets WordPress register the editor script from `block.json`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const blocksDir = path.resolve( __dirname, 'blocks' );

const entry = fs
	.readdirSync( blocksDir, { withFileTypes: true } )
	.filter( ( d ) => d.isDirectory() )
	.filter( ( d ) => fs.existsSync( path.join( blocksDir, d.name, 'index.js' ) ) )
	.reduce( ( acc, d ) => {
		// Key "<name>/index" → build/<name>/index.js + build/<name>/index.asset.php
		acc[ `${ d.name }/index` ] = path.join( blocksDir, d.name, 'index.js' );
		return acc;
	}, {} );

module.exports = {
	...defaultConfig,
	entry,
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
};
