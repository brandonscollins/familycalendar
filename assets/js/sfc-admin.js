jQuery(document).ready(function($) {
    // Initialize color pickers on existing fields
    $('.sfc-color-picker').wpColorPicker();

    // Keep track of feed index
    let feedIndex = $('.sfc-feed-row').length;

    // Add new feed row
    $('#sfc-add-feed').on('click', function(e) {
        e.preventDefault();

        const newRow = `
            <div class="sfc-feed-row">
                <input type="text" name="sfc_options[feeds][${feedIndex}][name]" placeholder="Calendar Name" class="sfc-feed-name" />
                <input type="url" name="sfc_options[feeds][${feedIndex}][url]" placeholder="iCal Feed URL..." class="sfc-feed-url" />
                <input type="color" name="sfc_options[feeds][${feedIndex}][color]" value="#3788d8" class="sfc-color-picker-new" />
                <input type="number" name="sfc_options[feeds][${feedIndex}][offset]" value="0" title="Timezone Offset (hours)" step="1" style="width: 60px; text-align: center;" />
                <button type="button" class="button sfc-remove-feed">Remove</button>
            </div>
        `;

        $('#sfc-feeds-container').append(newRow);

        // Initialize color picker on new field if wp-color-picker is available
        if ($.fn.wpColorPicker) {
            $(`input[name="sfc_options[feeds][${feedIndex}][color]"]`).wpColorPicker();
        }

        feedIndex++;
    });

    // Remove feed row
    $(document).on('click', '.sfc-remove-feed', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to remove this calendar feed?')) {
            $(this).closest('.sfc-feed-row').fadeOut(300, function() {
                $(this).remove();
            });
        }
    });
});
