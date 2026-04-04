$(document).ready(function() {
    // Admin dashboard chart
    if ($('#salesChart').length) {
        $.getJSON('/admin/api/stats/sales', function(data) {
            var ctx = document.getElementById('salesChart').getContext('2d');
            // Chart initialization
        });
    }

    // Admin bulk actions
    $('#select-all').on('change', function() {
        var checked = $(this).is(':checked');
        $('input.bulk-checkbox').prop('checked', checked);
        updateBulkActionButtons();
    });

    $('input.bulk-checkbox').on('change', function() {
        updateBulkActionButtons();
    });

    function updateBulkActionButtons() {
        var count = $('input.bulk-checkbox:checked').length;
        $('#bulk-actions').toggle(count > 0);
        $('#selected-count').text(count);
    }

    // Admin modal forms
    $('.admin-modal-trigger').on('click', function() {
        var url = $(this).data('url');
        var $modal = $('#admin-modal');

        $.get(url, function(html) {
            $modal.find('.content').html(html);
            $modal.modal('show');
        });
    });

    // Admin search with autocomplete
    $('#admin-search').on('keyup', function() {
        var query = $(this).val();
        if (query.length < 2) return;

        $.getJSON('/admin/api/search', { q: query }, function(results) {
            var $dropdown = $('#search-results');
            $dropdown.empty();
            $.each(results, function(i, result) {
                $dropdown.append(
                    $('<a>').attr('href', result.url).addClass('result').text(result.label)
                );
            });
            $dropdown.show();
        });
    });

    // WYSIWYG editor initialization
    if ($('.wysiwyg-editor').length) {
        $('.wysiwyg-editor').each(function() {
            $(this).trumbowyg({
                btns: [['bold', 'italic'], ['link'], ['unorderedList', 'orderedList']]
            });
        });
    }

    // Sortable tables via jQuery UI
    if ($.fn.sortable) {
        $('table.sortable-table tbody').sortable({
            handle: '.sort-handle',
            update: function(event, ui) {
                var positions = {};
                $(this).find('tr').each(function(index) {
                    positions[$(this).data('id')] = index;
                });
                $.post('/admin/api/sort', { positions: positions });
            }
        });
    }

    // Semantic UI form validation
    $('.ui.form').form({
        fields: {
            name: ['minLength[3]', 'empty'],
            email: ['email', 'empty'],
            code: ['minLength[2]', 'empty']
        }
    });
});
