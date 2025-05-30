'use strict';

(function() {
    $(document).ready(function() {

        $(document).on('click', 'summary.selection-summary', function() {
            this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
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
         * Check all check boxes of a selection.
         *
         * Only available when the a checkbox with id "selection-check-all" is present,
         * for example for BulkExport.
         */
        $('body').on('change', '.selection-list #selection-check-all',  function() {
            const checked = $(this).prop('checked');
            $('.resource-list .selection-check-group, .resource-list .selection-check-resource').toArray().forEach(function(input) {
                $(input).prop('checked', checked);
            });
        });

        /**
         * Add multiple resources to a selection.
         */
        $('body').on('click', '.selection-button', function(e) {
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
                beforeSend: beforeSpin(button),
            })
            .done(function(data) {
                if (data.status === 'success') {
                    const selectionResource = data.data.selection_resource;
                    // The status may be "fail" when the action is add/delete
                    // and the item is already added or deleted.
                    if (selectionResource.status === 'success') {
                        updateSelectionButton(selectionResource);
                        updateSelectionList(selectionResource);
                        button.closest('body.selection.browse .selection-list .resource').remove();
                    }
                }
            })
            .always(function () {
                afterSpin(button)
            });
        });

        /**
         * Remove a resource from the list of selected resources block.
         */
        $('body').on('click', '.selection-resources .actions .delete, .selection-resources .selection-delete', function(e) {
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
                beforeSend: beforeSpin(button),
            })
            .done(function(data) {
                if (data.status === 'success') {
                    const selectionResource = data.data.selection_resource;
                    if (selectionResource.status === 'success' && selectionResource.value === 'unselected') {
                        updateSelectionButton(selectionResource);
                        updateSelectionList(selectionResource);
                    }
                }
            })
            .always(function () {
                afterSpin(button)
            });
        });

        /**
         * Add a group to a selection.
         */
        $('body').on('click', '.selection-list .add-group', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const msg = button.closest('.selection-structure').data('msg-group-name') ? button.closest('.selection-structure').data('msg-group-name') : button.text();
            var group = prompt(msg);
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
                if (data.status === 'success') {
                    // TODO Add events to update select, etc.
                    // TODO Reemove return false (for info for now).
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
                } else if (data.status === 'fail') {
                    alert(data.data.message ? data.data.message : 'An error occurred.');
                } else {
                    alert(data.message ? data.message : 'An error occurred.');
                }
            });
        });

        /**
         * Rename a group.
         *
         * @todo Factorize: this is nearly the same than addGroup.
         */
        $('body').on('click', '.selection-list .rename-group', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const msg = button.closest('.selection-structure').data('msg-group-name') ? button.closest('.selection-structure').data('msg-group-name') : button.text();
            var groupDestination = prompt(msg);
            if (!groupDestination || !groupDestination.trim().length) {
                return;
            }
            const selectionGroup = button.closest('.selection-group');
            const path = selectionGroup.data('path') ? selectionGroup.data('path') : null;
            const groupName = path ? path.substring(path.lastIndexOf('/') + 1) : null;
            if (!groupName || !groupName.length || groupName === '/' || groupName === groupDestination) {
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
                if (data.status === 'success') {
                    // TODO Add events to update select, etc.
                    window.location.reload();
                    return false;
                } else if (data.status === 'fail') {
                    alert(data.data.message ? data.data.message : 'An error occurred.');
                } else {
                    alert(data.message ? data.message : 'An error occurred.');
                }
            });
        });

        /**
         * Remove a group.
         *
         * @todo Factorize: this is nearly the same than addGroup.
         */
        $('body').on('click', '.selection-list .delete-group', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const selectionGroup = button.closest('.selection-group');
            const path = selectionGroup.data('path') ? selectionGroup.data('path') : null;
            if (!path || !path.length) {
                return;
            }
            const url = button.attr('data-url');
            $.ajax({
                url: url,
                data: {
                    group: path,
                },
            })
            .done(function(data) {
                if (data.status === 'success') {
                    // TODO Add events to update select, etc.
                    window.location.reload();
                    return false;
                } else if (data.status === 'fail') {
                    alert(data.data.message ? data.data.message : 'An error occurred.');
                } else {
                    alert(data.message ? data.message : 'An error occurred.');
                }
            });
        });

        /**
         * Prepare/remove the selector used to move a group or a resource.
         */
        $('body').on('click', '.selection-list .move-group, .selection-list .move-resource', function(e) {
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
        $('body').on('change', '.selection-list .selector-group-move select', function(e) {
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
                if (data.status === 'success') {
                    // TODO Add events to update select, etc.
                    window.location.reload();
                    return false;
                } else if (data.status === 'fail') {
                    alert(data.data.message ? data.data.message : 'An error occurred.');
                } else {
                    alert(data.message ? data.message : 'An error occurred.');
                }
            });
        });

        const updateSelectionButton = function(selectionResource) {
            const button = $('.selection-button[data-id=' + selectionResource.id + ']');
            if (!button.length) {
                return;
            }
            button
                .prop('title', button.attr('data-title-' + selectionResource.value))
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
         * Hide useless button on load.
         */
        const emptySelectionGroups = $('.selection-group .selection-resources:not(:has(.selection-resource))').closest('.selection-group');
        emptySelectionGroups.find('.move-resource, .export-group').hide();

        const beforeSpin = function (button) {
            button.find('span').addClass('fas fa-sync fa-spin');
        }

        const afterSpin = function (button) {
            button.find('span').removeClass('fas fa-sync fa-spin');
        }

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
         * Append resource_ids for contact us.
         */
        $('#contact-us').on('submit', function() {
            var selecteds = $('input.selected-resource');
            selecteds.prop('name', 'fields[id][]');
            selecteds.attr('form', 'contact-us');
            return true;
        });

    });
})();
