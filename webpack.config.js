/**
 * Build config: compile src/Admin/resources into assets/.
 *
 * Extends @wordpress/scripts' default webpack config and only changes the
 * entry + output, and routes extracted CSS into assets/css/ (JS into
 * assets/js/). Public/neutral tooling only.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' ); // eslint-disable-line import/no-extraneous-dependencies

const plugins = defaultConfig.plugins.map( ( plugin ) => {
	if ( plugin instanceof MiniCssExtractPlugin ) {
		return new MiniCssExtractPlugin( { filename: '../css/[name].css' } );
	}
	return plugin;
} );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( __dirname, 'src/Admin/resources/js/index.js' ),
		editor: path.resolve( __dirname, 'src/Admin/resources/js/editor.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/js' ),
	},
	plugins,
};
