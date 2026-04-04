$(document).ready(function() {
    // Cart quantity update
    $('.cart-quantity-input').on('change', function() {
        var $input = $(this);
        var itemId = $input.data('item-id');
        var quantity = $input.val();

        $.ajax({
            url: '/cart/item/' + itemId + '/quantity',
            method: 'PUT',
            data: { quantity: quantity },
            dataType: 'json',
            success: function(response) {
                $('#cart-total').text(response.total);
                $input.closest('tr').find('.item-total').text(response.itemTotal);
            },
            error: function(xhr) {
                $input.val($input.data('original'));
                alert('Error updating cart');
            }
        });
    });

    // Cart item removal
    $('.cart-remove-btn').on('click', function() {
        var $btn = $(this);
        var itemId = $btn.data('item-id');

        if (confirm('Remove this item from cart?')) {
            $.ajax({
                url: '/cart/item/' + itemId,
                method: 'DELETE',
                success: function() {
                    $btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        updateCartCounter();
                    });
                }
            });
        }
    });

    // Apply coupon code
    $('#apply-coupon').on('click', function() {
        var code = $('#coupon-code').val();
        $.post('/cart/coupon', { code: code }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                $('#coupon-error').text(response.message).show();
            }
        });
    });

    function updateCartCounter() {
        var count = $('table.cart tbody tr').length;
        $('.cart-counter').text(count);
    }
});
