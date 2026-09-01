/**
 * WordPress uses a fork of Prettier, published as wp-prettier and installed
 * here under the `prettier` name, because upstream Prettier will not add the
 * `parenSpacing` option that WordPress coding standards require.
 *
 * wp-scripts supplied both the fork and this config to projects that had
 * neither. Without it, `eslint-plugin-prettier` resolves stock Prettier and
 * reports every WordPress-style `( $ )` as an error, which is 120 errors on
 * assets/js/admin.js that were not there before.
 */
module.exports = require( '@wordpress/prettier-config' );
