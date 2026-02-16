'use strict';

/**
 * Require common-dialog.js.
 *
 * @see Access, Comment, ContactUs, Contribute, Generate, Guest, Resa, SearchHistory, Selection, TwoFactorAuth, Urify.
 */

(function ($) {
    $(document).ready(function() {

        // Display the dialog to send a message.
        $('#content').on('click', '.selection .send-message', function(e) {
            e.preventDefault();
            const button = $(this);
            const url = button.data('url');
            const selectionId = button.data('selection-id');
            const dialog = document.querySelector('dialog.dialog-send-message.dialog-selection');
            $(dialog).find('form').prop('action', url);
            $(dialog).find('input[name="selection_id"]').val(selectionId);
            dialog.showModal();
            $(dialog).trigger('o:dialog-opened');
        });

        // Complete the batch delete form after confirmation.
        $('#confirm-delete-selected, #confirm-delete-all').on('submit', function(e) {
            var confirmForm = $(this);
            if ('confirm-delete-all' === this.id) {
                confirmForm.append($('.batch-query').clone());
            } else {
                $('#batch-form').find('input[name="resource_ids[]"]:checked').each(function() {
                    confirmForm.append($(this).clone().prop('disabled', false).attr('type', 'hidden'));
                });
            }
        });
        $('.delete-all').on('click', function(e) {
            Omeka.closeSidebar($('#sidebar-delete-selected'));
        });
        $('.delete-selected').on('click', function(e) {
            Omeka.closeSidebar($('#sidebar-delete-all'));
            var inputs = $('input[name="resource_ids[]"]');
            $('#delete-selected-count').text(inputs.filter(':checked').length);
        });
        $('#sidebar-delete-all').on('click', 'input[name="confirm-delete-all-check"]', function(e) {
            $('#confirm-delete-all input[type="submit"]').prop('disabled', this.checked ? false : true);
        });

        /**
         * Update UI after any jSend success.
         */
        document.addEventListener('o:jsend-success', function(ev) {
            const detail = ev.detail || {};
            const data = detail.data || {};
            const button = detail.context?.target || null;

            if (!button) {
                return;
            }

            // Fix issue when clicking span inside button, if any.
            let iconSpan = button;
            let actualButton = button;
            if (button.tagName === 'SPAN') {
                actualButton = button.closest('button') || button.closest('a');
                if (!actualButton) {
                    return;
                }
            } else {
                iconSpan = button.querySelector('span.fas');
            }

            const row = $(actualButton.closest('tr'));

            // Public toggle.
            if (actualButton.matches('.public-toggle') && data.data.selection) {
                const prev = actualButton.dataset.status;
                const isNowPublic = data.data.selection.is_public;
                actualButton.dataset.status = data.data.selection.status;
                actualButton.title = data.data.selection.statusLabel || actualButton.title;
                actualButton.ariaLabel = actualButton.title;
                $(iconSpan)
                    .removeClass('o-icon-' + prev)
                    .addClass('o-icon-' + actualButton.dataset.status);
                // Also update the static indicator
                const staticIndicator = row.find('.is-public span.fas');
                staticIndicator
                    .removeClass('o-icon-public o-icon-private')
                    .addClass('o-icon-' + actualButton.dataset.status)
                    .attr('data-status', actualButton.dataset.status)
                    .attr('title', data.data.selection.statusLabel)
                    .attr('aria-label', data.data.selection.statusLabel);

                // Handle validated toggle button visibility and status.
                const validatedToggle = row.find('.validated-toggle');
                if (isNowPublic) {
                    // Show or create the validated toggle button if selection is now public.
                    if (validatedToggle.length) {
                        // Update existing button status.
                        const prevValidated = validatedToggle.attr('data-status');
                        const newValidatedStatus = data.data.selection.validatedStatus;
                        validatedToggle
                            .attr('data-status', newValidatedStatus)
                            .attr('title', data.data.selection.validatedStatusLabel)
                            .attr('aria-label', data.data.selection.validatedStatusLabel);
                        validatedToggle.find('span.fas')
                            .removeClass('o-icon-' + prevValidated)
                            .addClass('o-icon-' + newValidatedStatus);
                        validatedToggle.closest('li').show();
                    }
                } else {
                    // Hide the validated toggle button if selection is now private.
                    validatedToggle.closest('li').hide();
                }
            }

            // Validated toggle.
            else if (actualButton.matches('.validated-toggle') && data.data.selection) {
                const prev = actualButton.dataset.status;
                actualButton.dataset.status = data.data.selection.status;
                actualButton.title = data.data.selection.statusLabel || actualButton.title;
                actualButton.ariaLabel = actualButton.title;
                $(iconSpan)
                    .removeClass('o-icon-' + prev)
                    .addClass('o-icon-' + actualButton.dataset.status);
            }

            // Send message success.
            else if (actualButton.name === 'submit' && actualButton.closest('form').name === 'send-message') {
                // Close the dialog
                const dialog = actualButton.closest('dialog');
                if (dialog) {
                    dialog.close();
                }
            }

        }, false);

    });
})(jQuery);
