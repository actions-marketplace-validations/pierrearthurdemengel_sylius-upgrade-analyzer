$(document).ready(function() {
    // Address form handling
    $('#different-shipping-address').on('change', function() {
        var $checkbox = $(this);
        if ($checkbox.is(':checked')) {
            $('#shipping-address-form').slideDown();
        } else {
            $('#shipping-address-form').slideUp();
        }
    });

    // Country select with province loading
    $('select[name*="countryCode"]').on('change', function() {
        var $select = $(this);
        var countryCode = $select.val();
        var $provinceSelect = $select.closest('.address-form').find('select[name*="provinceCode"]');

        $.ajax({
            url: '/provinces/' + countryCode,
            method: 'GET',
            dataType: 'json',
            success: function(provinces) {
                $provinceSelect.empty();
                $provinceSelect.append('<option value="">Select province</option>');
                $.each(provinces, function(code, name) {
                    $provinceSelect.append($('<option>').val(code).text(name));
                });
                $provinceSelect.closest('.field').toggle(provinces.length > 0);
            }
        });
    });

    // Payment method selection
    $('input[name="payment_method"]').on('change', function() {
        var method = $(this).val();
        $('.payment-details').hide();
        $('#payment-details-' + method).show();

        if (method === 'stripe') {
            initializeStripeElements();
        }
    });

    // Shipping method selection with price update
    $('input[name="shipping_method"]').on('change', function() {
        var $radio = $(this);
        var price = $radio.data('price');
        $('#shipping-cost').text(price);
        recalculateTotal();
    });

    // Order summary accordion
    $('.checkout-summary .title').on('click', function() {
        $(this).next('.content').slideToggle();
    });

    // Form validation
    $('form.checkout-form').on('submit', function(e) {
        var $form = $(this);
        var isValid = true;

        $form.find('[required]').each(function() {
            var $field = $(this);
            if (!$field.val()) {
                $field.closest('.field').addClass('error');
                isValid = false;
            } else {
                $field.closest('.field').removeClass('error');
            }
        });

        if (!isValid) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $form.find('.field.error').first().offset().top - 100
            }, 300);
        }
    });

    function recalculateTotal() {
        var subtotal = parseFloat($('#subtotal').data('value'));
        var shipping = parseFloat($('#shipping-cost').data('value'));
        var total = subtotal + shipping;
        $('#order-total').text('$' + total.toFixed(2));
    }

    function initializeStripeElements() {
        // Stripe Elements initialization
        if (typeof Stripe !== 'undefined') {
            var stripe = Stripe($('#stripe-key').val());
            var elements = stripe.elements();
            var card = elements.create('card');
            card.mount('#card-element');
        }
    }
});
