const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/js/app.js')
    .addEntry('cart', './assets/js/cart.js')
    .addEntry('checkout', './assets/js/checkout.js')
    .addEntry('product-gallery', './assets/js/product-gallery.js')
    .addEntry('admin', './assets/js/admin.js')
    .addEntry('wishlist', './assets/js/wishlist.js')
    .addStyleEntry('styles', './assets/css/app.scss')
    .enableSassLoader()
    .enableSourceMaps(!Encore.isProduction())
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .autoProvidejQuery()
    .enableVersioning(Encore.isProduction())
    .configureBabel(null, {
        useBuiltIns: 'usage',
        corejs: 3,
    })
;

module.exports = Encore.getWebpackConfig();
