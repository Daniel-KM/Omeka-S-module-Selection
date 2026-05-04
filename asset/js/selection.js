'use strict';

/**
 * Requires common-dialog.js.
 *
 * @see Access, Comment, ContactUs, Contribute, Generate, Guest, Resa, SearchHistory, Selection, TwoFactorAuth, Urify.
 */

(function() {
    $(document).ready(function() {

        $(document).on('click', 'summary.selection-summary', function() {
            this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
        });

        /**
         * Close other selection details when opening a new one.
         */
        $(document).on('click', 'details.selection-groups > summary', function() {
            var currentDetails = $(this).parent()[0];
            $('details.selection-groups').not(currentDetails).each(function() {
                $(this).removeAttr('open');
                $(this).find('select.chosen-select').trigger('chosen:close');
            });
        });

        /**
         * Handle login button click when selection is disabled for anonymous.
         */
        $(document).on('click', '.selection-button.selection-login', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const loginButton = $('.button-login[data-url]');
            if (loginButton.length) {
                loginButton.trigger('click');
            } else {
                window.location.href = button.data('url');
            }
        });

        /**
         * Hide details summary on outside click.
         * @todo Find a better way to manage only selection details.
         */
        /*
        $(document).on('click', function(e) {
            const clicked = e.target;
            $('details.selection-details')
                .filter(function() {
                    return !$.contains(this, clicked);
                })
                .removeAttr('open');
        });
        */

         /**
          * Add a new selection.
          */
         $(document).on('click', '.selection-list-actions button.add', function(e) {
             e.preventDefault();
             e.stopPropagation();
             const button = $(this);
             const msg = button.data('msg-selection-name') ? button.data('msg-selection-name') : button.text();
             CommonDialog.dialogPrompt({
                 heading: Omeka.jsTranslate('Selection'),
                 message: msg,
             })
                 .then(function(name) {
                     if (!name || !name.trim().length) {
                         return;
                     }
                     const url = button.attr('data-url');
                     $.ajax({
                        method: 'POST',
                         url: url,
                         data: {
                             'o:label': name.trim(),
                         },
                     })
                     .done(function(data) {
                         // TODO Add events to update the list, etc.
                         window.location.reload();
                         return false;
                     })
                     .fail(CommonDialog.jSendFail);
                 });
         });

         /**
          * Rename a selection.
          */
         $(document).on('click', '.selection .actions button.rename', function(e) {
             e.preventDefault();
             e.stopPropagation();
             const button = $(this);
             const msg = button.data('msg') ? button.data('msg') : button.text();
             const selection = button.closest('.selection.resource[data-id]');
             const currentName = selection.find('.resource-name:first').text();
             CommonDialog.dialogPrompt({
                 heading: Omeka.jsTranslate('Selection'),
                 message: msg,
                 defaultValue: currentName,
             })
                 .then(function(name) {
                     if (!name || !name.trim().length || name.trim() === currentName) {
                         return;
                     }
                     const url = button.attr('data-url');
                     $.ajax({
                        method: 'POST',
                         url: url,
                         data: {
                             'o:label': name.trim(),
                         },
                     })
                     .done(function(data) {
                         if (data.status === 'success') {
                             selection.find('.resource-name:first').text(name.trim());
                             $(document).trigger('o:selection-updated', data);
                         }
                     })
                     .fail(CommonDialog.jSendFail);
                 });
         });

        /**
         * Publish/unpublish a selection.
         */
        $(document).on('click', '.selection .actions button.publish, .selection .actions button.unpublish', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const selection = button.closest('.selection.resource[data-id]');
            const msg = button.hasClass('publish') ? button.data('msg-publish-confirm') : button.data('msg-unpublish-confirm');
            CommonDialog.dialogConfirm({
                heading: Omeka.jsTranslate('Selection'),
                message: msg,
            })
                .then(function(confirmed) {
                    if (!confirmed) return;
                    const url = button.attr('data-url');
                    $.ajax({
                        method: 'POST',
                        url: url,
                        data: {
                            'o:is_public': button.hasClass('publish') ? 1 : 0,
                        },
                    })
                    .done(function(data) {
                        if (data.status === 'success') {
                            var validation = selection.find('.validation');
                            if (button.hasClass('publish')) {
                                button.addClass('unpublish').removeClass('publish');
                                button.text(button.data('msg-unpublish'));
                                selection.removeClass('selection-private').addClass('selection-public');
                                selection.find('.visibility')
                                    .removeClass('visibility-private').addClass('visibility-public')
                                    .text(selection.find('.visibility').data('msg-visible'));
                                // Show validation status and reset to "not yet" (pending moderation).
                                selection.removeClass('selection-validated-true selection-validated-false').addClass('selection-validated-not-yet');
                                validation
                                    .removeClass('validated-true validated-false').addClass('validated-not-yet')
                                    .text(validation.data('msg-validated-not-yet'))
                                    .show();
                            } else {
                                button.addClass('publish').removeClass('unpublish');
                                button.text(button.data('msg-publish'));
                                selection.removeClass('selection-public').addClass('selection-private');
                                selection.find('.visibility')
                                    .removeClass('visibility-public').addClass('visibility-private')
                                    .text(selection.find('.visibility').data('msg-invisible'));
                                // Hide validation status (irrelevant for private selections).
                                validation.hide();
                            }
                            $(document).trigger('o:selection-updated', data);
                        }
                    })
                    .fail(CommonDialog.jSendFail);
                });
        });

        /**
          * Delete a selection.
          */
         $(document).on('click', '.selection .actions .selection-delete, .selection .selection-button.selection-delete', function(e) {
             e.preventDefault();
             e.stopPropagation();
             const button = $(this);
             const selection = button.closest('.selection.resource[data-id]');
             const msg = button.data('msg-selection-name') ? button.data('msg-selection-name') : button.text();
             CommonDialog.dialogConfirm({
                 heading: Omeka.jsTranslate('Selection'),
                 message: msg,
             })
                 .then(function(confirmed) {
                     if (!confirmed) return;
                     const url = button.attr('data-url');
                     $.ajax({
                         url: url,
                     })
                     .done(function(data) {
                        if (data.status === 'success') {
                            selection.remove();
                            $(document).trigger('o:selection-deleted', data);
                        }
                     })
                     .fail(CommonDialog.jSendFail);
                 });
         });

        /**
         * Check all check boxes of a selection.
         *
         * Only available when the a checkbox with id "selection-check-all" is present,
         * for example for BulkExport.
         */
        $(document).on('change', '.selection-list #selection-check-all',  function() {
            const checked = $(this).prop('checked');
            $('.resource-list .selection-check-group, .resource-list .selection-check-resource').toArray().forEach(function(input) {
                $(input).prop('checked', checked);
            });
        });

        /**
         * Update multiple resources from a selection.
         */
        $(document).on('click', '.selection-button.selection-update', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);

            // Check limit only when adding (not when removing or when already selected).
            const action = button.data('action');
            const isSelected = button.hasClass('selected');
            const isAdding = action === 'include' || (action === 'toggle' && !isSelected);
            if (isAdding && !checkSelectionLimit(button, 1)) {
                return;
            }

            const selectionResource = button.closest('.selection-resource');
            const url = button.attr('data-url');
            const id = selectionResource.length ? selectionResource.data('id') : button.attr('data-id');
            const urlId = getQueryParameter(url, 'id');
            $.ajax({
                url: url,
                data: id && !urlId ? { id: id } : null,
                beforeSend: function() { CommonDialog.spinnerEnable(button[0]); },
            })
            .done(function(data) {
                const selectionResource = data.data.selection_resource;
                // The status may be "fail" when the action is include/exclude
                // and the item is already included or excluded.
                if (selectionResource.status === 'success') {
                    updateSelectionButton(selectionResource);
                    updateSelectionList(selectionResource);
                    // Update the count based on action result.
                    if (selectionResource.value === 'selected') {
                        updateSelectionCount(1);
                    } else if (selectionResource.value === 'unselected') {
                        updateSelectionCount(-1);
                    }
                    button.closest('body.selection.browse .selection-list .resource').remove();
                    $(document).trigger('o:selection-updated', data);
                }
            })
            .fail(CommonDialog.jSendFail)
            .always(function () {
                CommonDialog.spinnerDisable(button[0]);
            });
        });

        /**
         * Remove a resource from the list of selected resources block.
         */
        $(document).on('click', '.selection-resources .actions .delete, .selection-resources .selection-delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const selectionResource = button.closest('.selection-resource');
            const url = button.attr('data-url');
            const id = selectionResource.length ? selectionResource.data('id') : button.attr('data-id');
            const urlId = getQueryParameter(url, 'id');
            $.ajax({
                url: url,
                data: id && !urlId ? { id: id } : null,
                beforeSend: function() { CommonDialog.spinnerEnable(button[0]); },
            })
            .done(function(data) {
                const selectionResource = data.data.selection_resource;
                if (selectionResource.status === 'success' && selectionResource.value === 'unselected') {
                    updateSelectionButton(selectionResource);
                    updateSelectionList(selectionResource);
                    updateSelectionCount(-1);
                    $(document).trigger('o:selection-updated', data);
                }
            })
            .fail(CommonDialog.jSendFail)
            .always(function () {
                CommonDialog.spinnerDisable(button[0]);
            });
        });

        /**
         * Add a group to a selection.
         */
        $(document).on('click', '.selection-list .add-group', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const msg = button.closest('.selection-structure').data('msg-group-name') ? button.closest('.selection-structure').data('msg-group-name') : button.text();
            CommonDialog.dialogPrompt({
                heading: Omeka.jsTranslate('Selection'),
                message: msg,
            })
                .then(function(group) {
                    if (!group || !group.trim().length) {
                        return;
                    }
                    const selectionGroup = button.closest('.selection-group');
                    const path = selectionGroup.data('path') ? selectionGroup.data('path') : null;
                    const url = button.attr('data-url');
                    $.ajax({
                        url: url,
                        data: {
                            group: path,
                            name: group.trim(),
                        },
                    })
                    .done(function(data) {
                        // TODO Add events to update select, etc.
                        // TODO Remove return false (for info for now).
                        window.location.reload();
                        return false;
                        // Path is checked and does not contain forbidden characters.
                        const parent = data.data.group && data.data.group.path
                            ? $('.selection-structure .selection-group[data-path="' + data.data.group.path + '"]')
                            : $('.selection-structure');
                        parent.append($('.selection-structure').data('template-group')
                            .replace('__GROUP_LEVEL__', data.data.group.path + '/' + data.data.group.id)
                            .replace('__GROUP_PATH__', data.data.group.path + '/' + data.data.group.id)
                            .replace('__GROUP_NAME__',
                                (data.data.group.path && data.data.group.path.length ? '<span>' + data.data.group.path.substring(1).replaceAll('/', '</span><span>') + '</span>' : '')
                                + '<span>' + data.data.group.id + '</span>'
                            )
                            .replace('__GROUP_RESOURCES__', '')
                        );
                        $(document).trigger('o:selection-updated', data);
                    })
                    .fail(CommonDialog.jSendFail);
                });
        });

        /**
         * Rename a group.
         *
         * @todo Factorize: this is nearly the same than addGroup.
         */
        $(document).on('click', '.selection-list .rename-group', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const msg = button.closest('.selection-structure').data('msg-group-name') ? button.closest('.selection-structure').data('msg-group-name') : button.text();
            CommonDialog.dialogPrompt({
                heading: Omeka.jsTranslate('Selection'),
                message: msg,
            })
                .then(function(groupDestination) {
                    if (!groupDestination || !groupDestination.trim().length) {
                        return;
                    }
                    const selectionGroup = button.closest('.selection-group');
                    const path = selectionGroup.data('path') ? selectionGroup.data('path') : null;
                    const name = path ? path.substring(path.lastIndexOf('/') + 1) : null;
                    if (!name || !name.length || name === '/' || name === groupDestination) {
                        return;
                    }
                    const url = button.attr('data-url');
                    $.ajax({
                        url: url,
                        data: {
                            group: path,
                            name: groupDestination.trim(),
                        },
                    })
                    .done(function(data) {
                        // TODO Add events to update select, etc.
                        window.location.reload();
                        return false;
                    })
                    .fail(CommonDialog.jSendFail);
                });
        });

        /**
         * Remove a group.
         */
        $(document).on('click', '.selection-list .delete-group', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const msg = Omeka.jsTranslate('Are you sure you want to delete this group?');
            CommonDialog.dialogConfirm({
                heading: Omeka.jsTranslate('Selection'),
                message: msg,
            })
                .then(function(confirmed) {
                    if (!confirmed) {
                        return;
                    }
                    const selectionGroup = button.closest('.selection-group');
                    const path = selectionGroup.data('path') ? selectionGroup.data('path') : null;
                    const url = button.attr('data-url');
                    $.ajax({
                        url: url,
                        data: {
                            group: path,
                        },
                    })
                    .done(function(data) {
                        // TODO Add events to update select, etc.
                        window.location.reload();
                        return false;
                    })
                    .fail(CommonDialog.jSendFail);
                });
        });

        /**
         * Prepare/remove the selector used to move a group or a resource.
         */
        $(document).on('click', '.selection-list .move-group, .selection-list .move-resource', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            var selector = button.parent().find('#selector-group');
            if (selector.length) {
                selector.remove();
                return;
            }
            selector = $('#selector-group').clone();
            button.after(selector);
            const select = selector.find('select');
            select
                .val(button.closest('.selection-group').data('path'))
                .attr('data-url', button.data('url'));
            selector
                .addClass('selector-group-move')
                .removeClass('inactive')
                .show();
        });

        /**
         * Move resources to a group.
         */
        $(document).on('change', '.selection-list .selector-group-move select', function(e) {
            const select = $(this);
            const selectionGroup = select.closest('.selection-group');
            const selectionResource = select.closest('.selection-resource');
            const path = selectionGroup.data('path') ? selectionGroup.data('path') : '';
            const newPath = select.val();
            if (newPath === path) {
                return;
            }
            const id = selectionResource.length ? selectionResource.attr('data-id') : resourceGroupIds(selectionGroup);
            const url = select.attr('data-url');
            $.ajax({
                url: url,
                data: {
                    id: id,
                    group: path,
                    name: newPath,
                },
            })
            .done(function(data) {
                // TODO Add events to update select, etc.
                window.location.reload();
                return false;
            })
            .fail(CommonDialog.jSendFail);
        });

        /**
         * Reset resources from a selection.
         */
        $(document).on('click', '.selection-button.selection-reset', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const url = button.attr('data-url');
            $.ajax({
                url: url,
                beforeSend: function() { CommonDialog.spinnerEnable(button[0]); },
            })
            .done(function(data) {
                // The status may be "fail" when the action is add/delete
                // and the item is already added or deleted.
                if (data.status === 'success') {
                    window.location.reload();
                    return false;
                    const selectionResource = button.closest('.selection.resource');
                    updateSelectionButton({id: selectionId});
                    updateSelectionList(selectionResource);
                    button.closest('body.selection.browse .selection-list .resource').remove();
                    $(document).trigger('o:selection-updated', data);
                }
            })
            .fail(CommonDialog.jSendFail)
            .always(function () {
                CommonDialog.spinnerDisable(button[0]);
            });
        });

        const updateSelectionButton = function(selectionResource) {
            const button = $('.selection-button[data-id=' + selectionResource.id + ']');
            if (!button.length) {
                return;
            }
            button
                .prop('title', button.attr('data-text-' + selectionResource.value))
                .removeClass('selected unselected')
                .addClass(selectionResource.value);
        }

        const updateSelectionList = function(selectionResource) {
            const list = $('.selection-resources');
            if (!list.length) {
                return;
            }
            if (selectionResource.value === 'selected') {
                if (!list.find('li[data-id=' + selectionResource.id + ']').length) {
                    list.append(
                        $('<li>').attr('data-id', selectionResource.id)
                            .append(
                                $('<a>').prop('href', selectionResource.url).append(selectionResource.title)
                            )
                            .append(
                                $('<span class="selection-delete">')
                                    .attr('data-id', selectionResource.id)
                                    .attr('data-url', selectionResource.url_remove)
                                    .attr('title', list.attr('data-text-delete'))
                                    .attr('aria-label', list.attr('data-text-delete'))
                            )
                    );
                }
            } else {
                list.find('li[data-id=' + selectionResource.id + ']').remove();
            }
            if (list.find('li').length) {
                $('.selection-empty').addClass('inactive').hide();
                $('.selection-count').removeClass('inactive').show();
            } else {
                $('.selection-count').addClass('inactive').hide();
                $('.selection-empty').removeClass('inactive').show();
            }
        }

        /**
         * Check if adding resources would exceed the max limit.
         */
        const checkSelectionLimit = function(button, addCount = 1) {
            const maxResources = parseInt(button.data('max-resources'), 10) || 0;
            const currentCount = parseInt(button.data('current-count'), 10) || 0;
            // 0 means unlimited.
            if (maxResources === 0) {
                return true;
            }
            if (currentCount + addCount > maxResources) {
                const msg = Omeka.jsTranslate('Selection limit reached. Maximum %d resources allowed.').replace('%d', maxResources);
                CommonDialog.dialogAlert({
                    heading: Omeka.jsTranslate('Selection'),
                    message: msg,
                });
                return false;
            }
            return true;
        };

        /**
         * Update the current count on all selection buttons.
         */
        const updateSelectionCount = function(delta) {
            $('.selection-button[data-current-count]').each(function() {
                const button = $(this);
                const currentCount = parseInt(button.data('current-count'), 10) || 0;
                button.data('current-count', Math.max(0, currentCount + delta));
            });
        };

        const getQueryParameter = function(url, param) {
            const urlSplit = url.split('?', 2);
            return urlSplit.length < 2 ? null : new URLSearchParams(urlSplit[1]).get(param);
        }

        function resourceGroupIds(selectionGroup) {
            var ids = [];
            selectionGroup.find(':not(.selection-group) .selection-resource[data-id != ""]').toArray()
                .forEach(function(el) { ids.push(el.dataset.id); });
            return ids;
        }

        /**
         * Include or exclude ids of resources in one or multiple selections.
         *
         * @todo Manage actions separately (do a full form or specific buttons).
         */
        $(document).on('change', '.selector-group select', function(e) {
            const select = $(this);
            const selector = select.closest('.selector-group');
            const resourceId = selector.data('resource-id');
            const selectionId = selector.data('id') && selector.data('id') !== '' ? selector.data('id') : null;

            // Check if it is a special option (empty value) and clean select.
            const selectedSpecial = (select.val() || []).filter(v => v === "0" || v === "00");
            const action = selectedSpecial.length ? select.find('option.selection-action[value=' + selectedSpecial[0] + ']').data('action') : null;
            select.find('option.selection-action').prop('selected', false);
            if (action) {
                select.trigger('chosen:updated');
            }

            const selecteds = select.val() || [];

            if (action === 'add') {
                CommonDialog.dialogPrompt({
                    heading: Omeka.jsTranslate('Selection'),
                    message: Omeka.jsTranslate('Enter new selection name'),
                })
                    .then(function(name) {
                        if (name && name.trim().length) {
                            // TODO Use CommonDialog.jSend.
                            const url = selector.data('url-selections') + '/add';
                            $.ajax({
                                method: 'POST',
                                // Include the current resource in the selection automatically.
                                url: url + '?id[]=' + resourceId,
                                data: {
                                    'o:label': name.trim(),
                                 },
                                beforeSend: function() { CommonDialog.spinnerEnable(select[0]); },
                            })
                            .done(function(data) {
                                if (data.status === 'success') {
                                    // Include the new selection in all selector dropdowns on the page.
                                    var selectionId = data.data.selection['o:id'];
                                    var selectionLabel = name;
                                    $('.selector-group select').each(function() {
                                        var sel = $(this);
                                        if (sel.find('option[value="' + selectionId + '"]').length) {
                                            return;
                                        }
                                        var newOption = $('<option>')
                                            .val(selectionId)
                                            .text(selectionLabel);
                                        // Only pre-select for the current resource's dropdown.
                                        if (sel.is(select)) {
                                            newOption.prop('selected', true);
                                        }
                                        sel.find('option.selection-action').first().before(newOption);
                                        sel.trigger('chosen:updated');
                                    });
                                }
                                $(document).trigger('o:selection-updated', data);
                            })
                            .fail(function() {
                                CommonDialog.jSendFail();
                            })
                            .always(function () {
                                CommonDialog.spinnerDisable(select[0]);
                                select.trigger('chosen:updated');
                            });
                        }
                    });
            } else if (action === 'add-group') {
                CommonDialog.dialogPrompt({
                    heading: Omeka.jsTranslate('Selection'),
                    message: Omeka.jsTranslate('Enter new group name'),
                })
                    .then(function(name) {
                        if (name && name.trim().length) {
                            // TODO Use CommonDialog.jSend.
                            const url = selector.data(selectionId ? 'url-selection' : 'url-selections') + '/add-group';
                            $.ajax({
                                url: url + '?id[]=' + resourceId,
                                method: 'GET',
                                // TODO No parent group for now: create at root for flat hierarchy.
                                data: {
                                    name: name.trim(),
                                    group: '/',
                                },
                                beforeSend: function() { CommonDialog.spinnerEnable(select[0]); },
                            })
                            .done(function(data) {
                                if (data.status === 'success' && data.data && data.data.group) {
                                    // Include the new group in the list and select it for the current resource.
                                    const groupLabel = data.data.group.id;
                                    const newOption = $('<option>')
                                        .val('/' + groupLabel)
                                        .text(groupLabel)
                                        .prop('selected', true);
                                    select.find('option.selection-action').first().before(newOption);
                                    select.prop('disabled', false);
                                    select.trigger('chosen:updated');
                                }
                                $(document).trigger('o:selection-updated', data);
                            })
                            .fail(function() {
                                CommonDialog.jSendFail();
                            })
                            .always(function () {
                                CommonDialog.spinnerDisable(select[0]);
                                select.trigger('chosen:updated');
                            });
                        }
                    });
            } else if (action === 'exclude' || !selecteds || !selecteds.length) {
                const url = selector.data(selectionId ? 'url-selection' : 'url-selections') + '/exclude';
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        id: resourceId,
                    },
                    beforeSend: function() { CommonDialog.spinnerEnable(select[0]); },
                })
                .done(function() {
                    select.find('option:selected').prop('selected', false);
                    select.prop('disabled', false);
                    select.trigger('chosen:updated');
                    $(document).trigger('o:selection-updated');
                })
                .fail(CommonDialog.jSendFail)
                .always(function () {
                    CommonDialog.spinnerDisable(select[0]);
                    select.trigger('chosen:updated');
                });
            } else {
                // The resource is included in one or multiple groups or selections.
                // All groups or selections are sent, so it is update, because
                // there may be excluded too.
                // No spinner/chosen:updated to keep the dropdown open.
                const url = selector.data(selectionId ? 'url-selection' : 'url-selections') + '/update';
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        id: [resourceId],
                        // Selected may be selections or groups or selection:groups).
                        selecteds: selecteds,
                    },
                })
                .done(function(data) {
                    $(document).trigger('o:selection-updated', data);
                })
                .fail(CommonDialog.jSendFail);
            }
        });

        /**
         * Hide useless button on load.
         */
        const emptySelectionGroups = $('.selection-group .selection-resources:not(:has(.selection-resource))').closest('.selection-group');
        emptySelectionGroups.find('.move-resource, .export-group').hide();

        /**
         * Prepare initial ids for groups and buttons.
         */
        $('.selection-group .selection-resources .selection-resource').closest('.selection-group').toArray()
            .forEach(function(selectionGroup) {
                const ids = resourceGroupIds($(selectionGroup));
                if (ids.length) {
                    $(selectionGroup).data('id', ids.join(','));
                    $(selectionGroup).find('a.download-id').toArray().forEach(function(el) {
                        const url = $(el).prop('href');
                        if (!getQueryParameter(url, 'id')) {
                            $(el).prop('href', url + '?id=' + ids.join(','));
                        }
                    });
                }
            });

        /**
         * Update direct links for bulk export according to checkboxes.
         * 
         * Unlike contact us, send all resources when none are set.
         * Nevertheless, if no resource are set on the page, let the url.
         */
        $('.bulk-export .exporters a.exporter').on('click', function() {
            var vals = $('input.selected-resource[name="resource_ids[]"]:checked');
            if (!vals.length)  {
                vals = $('input.selected-resource[name="resource_ids[]"]');
                if (!vals.length) {
                    return;
                }
            }
            var url = new URL($(this).prop('href'));
            var values = [];
            if (vals.length)  {
                for (var i = 0; i < vals.length; i++) {
                    values.push(vals[i].value);
                }
            } else {
                values.push(0);
            }
            url.search = 'id=' + values.join(',');
            $(this).prop('href', url.toString());
        });

        /**
         * Append resource_ids for contact us on the selection page.
         */
        $('body.selection.browse #contact-us').on('submit', function() {
            const selecteds = $('input.selected-resource');
            selecteds.prop('name', 'fields[id][]');
            selecteds.attr('form', 'contact-us');
            $('#contact-us [name="fields[id]"]').remove();
            return true;
        });

        /**
         * Trigger all chosen select on load.
         *
         * @todo Find a way to not trigger twice on the same select.
         */
        if ($.fn.chosen) {
            $('select.chosen-select').chosen();
            // Keep dropdown open after selecting in selection dropdowns
            // and preserve scroll position when results are redrawn.
            $('.selector-group select.chosen-select').each(function() {
                var chosen = $(this).data('chosen');
                if (chosen) {
                    chosen.hide_results_on_select = false;
                    // Wrap winnow_results to preserve scroll position
                    // after selecting/deselecting an item.
                    var originalWinnow = chosen.winnow_results;
                    chosen.winnow_results = function(options) {
                        var scrollTop = this.search_results ? this.search_results.scrollTop() : 0;
                        originalWinnow.call(this, options);
                        if (this.search_results && scrollTop > 0) {
                            this.search_results.scrollTop(scrollTop);
                        }
                    };
                }
            });
        }

        /**
         * Allow clicking on a selected result in the dropdown to deselect it.
         *
         * By default, Chosen marks selected items as "result-selected" and
         * makes them non-clickable. This handler deselects the option using
         * Chosen internals (without triggering change, to avoid spinner and
         * chosen:updated that would close the dropdown), then sends the
         * AJAX update directly.
         */
        $(document).on('click', '.selector-group .chosen-results .result-selected', function(e) {
            e.stopPropagation();
            e.preventDefault();
            var resultItem = $(this);
            var chosenContainer = resultItem.closest('.chosen-container');
            var select = chosenContainer.prev('select');
            var optionArrayIndex = resultItem.data('option-array-index');
            var chosen = select.data('chosen');
            if (!chosen || !chosen.results_data || !chosen.results_data[optionArrayIndex]) {
                return;
            }
            // Deselect internally without triggering change.
            var resultData = chosen.results_data[optionArrayIndex];
            resultData.selected = false;
            chosen.form_field.options[resultData.options_index].selected = false;
            chosen.selected_option_count = null;
            chosen.result_clear_highlight();
            chosen.winnow_results();
            chosen.search_field_scale();
            // Remove hidden search-choice tag.
            chosenContainer.find('.search-choice-close[data-option-array-index="' + optionArrayIndex + '"]')
                .parents('li').first().remove();
            // Send the AJAX update directly (no spinner, no chosen:updated).
            var selector = select.closest('.selector-group');
            var resourceId = selector.data('resource-id');
            var selectionId = selector.data('id') && selector.data('id') !== '' ? selector.data('id') : null;
            var selecteds = select.val() || [];
            if (!selecteds.length) {
                var url = selector.data(selectionId ? 'url-selection' : 'url-selections') + '/exclude';
                $.ajax({ url: url, method: 'POST', data: { id: resourceId } })
                    .done(function() { $(document).trigger('o:selection-updated'); })
                    .fail(CommonDialog.jSendFail);
            } else {
                var url = selector.data(selectionId ? 'url-selection' : 'url-selections') + '/update';
                $.ajax({ url: url, method: 'POST', data: { id: [resourceId], selecteds: selecteds } })
                    .done(function(data) { $(document).trigger('o:selection-updated', data); })
                    .fail(CommonDialog.jSendFail);
            }
        });

        /**
         * Toggle favorite (star) button (simple mode).
         */
        $(document).on('click', '.selection-favorite', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const resourceId = button.data('resource-id');
            const baseUrl = button.data('url');
            if (!resourceId || !baseUrl) {
                return;
            }
            const selected = button.hasClass('is-selected');
            const url = baseUrl.replace(/\/?$/, '/') + 'toggle';
            $.ajax({
                url: url,
                method: 'POST',
                data: { id: resourceId },
            })
            .done(function(data) {
                if (!data || data.status !== 'success') {
                    return;
                }
                const selectionResource = data.data && data.data.selection_resource
                    ? data.data.selection_resource : null;
                const newValue = selectionResource ? selectionResource.value : (selected ? 'unselected' : 'selected');
                const nowSelected = newValue === 'selected';
                button.toggleClass('is-selected', nowSelected);
                button.attr('aria-pressed', nowSelected ? 'true' : 'false');
                const icon = button.find('.selection-favorite-icon');
                icon.toggleClass('fas', nowSelected).toggleClass('far', !nowSelected);
                if (nowSelected && !selected) {
                    updateSelectionCount(1);
                } else if (!nowSelected && selected) {
                    updateSelectionCount(-1);
                }
                $(document).trigger('o:selection-updated', data);
            })
            .fail(CommonDialog.jSendFail);
        });

        /**
         * Auto-open convert dialog after login when visitor favorites pending.
         */
        (function() {
            const anchor = document.getElementById('selection-convert-auto');
            if (!anchor) return;
            const dialogUrl = anchor.dataset.url;
            if (!dialogUrl) return;

            const convertUrl = dialogUrl.replace(/\/convert-dialog$/, '/convert');

            $.ajax({
                url: dialogUrl,
                method: 'GET',
                dataType: 'html',
            })
            .done(function(html) {
                const trimmed = (html || '').trim();
                if (!trimmed || trimmed.charAt(0) === '{') {
                    anchor.remove();
                    return;
                }
                const wrap = document.createElement('div');
                wrap.innerHTML = html;
                document.body.appendChild(wrap);
                const dialog = wrap.querySelector('dialog.selection-convert-dialog');
                if (dialog && typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
                bindSelectionConvertDialog(wrap, dialog, convertUrl);
                if (dialog) {
                    dialog.addEventListener('cancel', function() {
                        $.ajax({url: convertUrl, method: 'POST', data: {action: 'dismiss'}});
                    }, { once: true });
                }
                anchor.remove();
            })
            .fail(function() {
                anchor.remove();
            });
        })();

        function bindSelectionConvertDialog(wrap, dialog, convertUrl) {
            const $dialog = $(wrap).find('.selection-convert-dialog');
            if (!$dialog.length) return;

            $dialog.on('click', 'button[data-action]', function(e) {
                e.preventDefault();
                const act = $(this).data('action');
                const $buttons = $dialog.find('button[data-action]');
                if ($buttons.prop('disabled')) {
                    return;
                }
                const payload = {};

                if (act === 'dismiss' || act === 'discard') {
                    payload.action = act;
                } else if (act === 'new') {
                    payload.action = 'new';
                } else if (act === 'submit') {
                    const $selected = $dialog.find('input[name="action"]:checked');
                    const chosen = $selected.length ? $selected.val() : 'new';
                    payload.action = chosen;
                    if (chosen === 'new') {
                        const name = $dialog.find('input[name="name"]').val();
                        if (name) payload.name = name;
                    } else if (chosen === 'merge') {
                        const selectionId = $dialog.find('select[name="selection_id"]').val();
                        if (selectionId) payload.selection_id = selectionId;
                    }
                } else {
                    return;
                }

                $buttons.prop('disabled', true);
                $.ajax({
                    url: convertUrl,
                    method: 'POST',
                    data: payload,
                })
                .done(function(data) {
                    if (data && data.status === 'success') {
                        if (dialog && typeof dialog.close === 'function') {
                            dialog.close();
                        }
                        $(wrap).remove();
                        $(document).trigger('o:selection-converted', data);
                    } else {
                        $buttons.prop('disabled', false);
                    }
                })
                .fail(function(xhr) {
                    $buttons.prop('disabled', false);
                    if (typeof CommonDialog !== 'undefined' && CommonDialog.jSendFail) {
                        CommonDialog.jSendFail(xhr);
                    }
                });
            });
        }

        /**
         * Drag-reorder resources within a selection list.
         *
         * Activates on any <ul class="selection-sortable"> with attributes
         * data-selection-id, data-group, data-url-reorder. Requires
         * Sortable.js.
         */
        if (typeof Sortable !== 'undefined') {
            document.querySelectorAll('ul.selection-sortable').forEach(function(list) {
                Sortable.create(list, {
                    animation: 150,
                    draggable: '> li',
                    onEnd: function() {
                        const ids = Array.from(list.querySelectorAll('> li[data-id]'))
                            .map(function(li) { return li.dataset.id; })
                            .filter(Boolean);
                        $.ajax({
                            url: list.dataset.urlReorder,
                            method: 'POST',
                            traditional: true,
                            data: {
                                selection_id: list.dataset.selectionId || 0,
                                group: list.dataset.group || '/',
                                ids: ids,
                            },
                        }).fail(function(xhr) {
                            if (typeof CommonDialog !== 'undefined' && CommonDialog.jSendFail) {
                                CommonDialog.jSendFail(xhr);
                            }
                        });
                    },
                });
                list.classList.add('selection-sortable-active');
            });
        }

    });
})();
