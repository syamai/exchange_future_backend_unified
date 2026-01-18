let mix = require('laravel-mix');
let LiveReloadPlugin = require('webpack-livereload-plugin');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.copy('resources/assets/js/admin/lib/socket.io.js', 'public/js')
    .js([
        'resources/assets/js/admin/app.js',
        'resources/assets/js/admin/lib/adminlte.js'
        ], 'public/js/admin/app.js')
    .sass('resources/assets/sass/admin/app.scss', 'public/css/admin.css')
    .version();


/**
 * Webpack configuration
 */

mix.webpackConfig({
    plugins: [
    ]
});
