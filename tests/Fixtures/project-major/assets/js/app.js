import 'semantic-ui-css/semantic.min.css';
import 'semantic-ui-css/semantic.min.js';

$(document).ready(function() {
    // Initialize Semantic UI components
    $('.ui.dropdown').dropdown();
    $('.ui.accordion').accordion();
    $('.ui.modal').modal();
    $('.ui.popup').popup();
    $('.ui.sidebar').sidebar();

    // Initialize tooltips
    $('[data-tooltip]').popup({
        position: 'top center'
    });

    // Flash messages auto-hide
    $('.ui.message .close').on('click', function() {
        $(this).closest('.message').transition('fade');
    });

    // Newsletter subscription
    $('#newsletter-form').on('submit', function(e) {
        e.preventDefault();
        var email = $(this).find('input[name="email"]').val();
        $.ajax({
            url: '/newsletter/subscribe',
            method: 'POST',
            data: { email: email },
            success: function(response) {
                $(this).find('.success').show();
            },
            error: function() {
                $(this).find('.error').show();
            }
        });
    });
});
