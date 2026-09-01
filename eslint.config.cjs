/**
 * Flat config, which is what ESLint 9 reads. A .eslintrc.js is silently ignored
 * under it: the file is not an error, it simply never applies, so rules would
 * look configured and not be.
 *
 * This repository used to get its config implicitly from wp-scripts, which
 * assembled the same pieces in `config/eslint.config.cjs` and applied them when
 * no project config was present. wp-scripts is gone, so the assembly is written
 * out here. It is a transcription rather than a redesign: the ignores, the
 * recommended ruleset and the Babel parser defaults are what wp-scripts used,
 * so `npm run lint:js` enforces the same rules it did before.
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );

module.exports = [
	{
		ignores: [ '**/build/**', '**/node_modules/**', '**/vendor/**' ],
	},

	...wpPlugin.configs.recommended,

	{
		// This repository has no Babel config of its own, so the parser needs
		// the preset handed to it, exactly as wp-scripts did when it found none.
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
];
