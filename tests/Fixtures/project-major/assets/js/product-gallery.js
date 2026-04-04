jQuery(function($) {
    // Product image gallery with zoom
    var $gallery = $('.product-gallery');
    var $mainImage = $gallery.find('.main-image img');
    var $thumbnails = $gallery.find('.thumbnail-list img');

    $thumbnails.on('click', function() {
        var $thumb = $(this);
        var fullSrc = $thumb.data('full-src');
        $mainImage.attr('src', fullSrc);
        $thumbnails.removeClass('active');
        $thumb.addClass('active');
    });

    // Zoom on hover
    $mainImage.on('mouseenter', function() {
        $(this).css('transform', 'scale(1.5)');
    }).on('mouseleave', function() {
        $(this).css('transform', 'scale(1)');
    }).on('mousemove', function(e) {
        var offset = $(this).offset();
        var x = ((e.pageX - offset.left) / $(this).width()) * 100;
        var y = ((e.pageY - offset.top) / $(this).height()) * 100;
        $(this).css('transform-origin', x + '% ' + y + '%');
    });

    // Variant selector
    $('select.variant-selector').on('change', function() {
        var variantId = $(this).val();
        $.getJSON('/product/variant/' + variantId, function(data) {
            $('#product-price').text(data.price);
            if (data.image) {
                $mainImage.attr('src', data.image);
            }
            $('#add-to-cart-variant').val(variantId);
        });
    });
});
