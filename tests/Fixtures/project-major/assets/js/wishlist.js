$(function() {
    // Wishlist toggle
    $('.wishlist-toggle').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var productId = $btn.data('product-id');

        $.ajax({
            url: '/wishlist/toggle/' + productId,
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.added) {
                    $btn.addClass('active').find('i').removeClass('outline');
                } else {
                    $btn.removeClass('active').find('i').addClass('outline');
                }
                $('.wishlist-count').text(response.count);
            }
        });
    });

    // Move all wishlist items to cart
    $('#wishlist-to-cart').on('click', function() {
        $.post('/wishlist/move-to-cart', function(response) {
            if (response.success) {
                window.location.href = '/cart';
            }
        });
    });
});
