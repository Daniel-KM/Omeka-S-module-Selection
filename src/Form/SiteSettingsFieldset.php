<?php declare(strict_types=1);

namespace Selection\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var array
     */
    protected $searchConfigs = [];

    /**
     * @var string
     */
    protected $label = 'Selection module'; // @translate

    protected $elementGroups = [
        'favorites' => 'Favorites', // @translate
        'selections' => 'Selections', // @translate
        'selection' => 'Selection (other)', // @translate
        'themes_old' => 'Old themes', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'selection')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'selection_favorites_visitor',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'favorites',
                    'label' => 'Enable favorites for anonymous visitors', // @translate
                    'info' => 'Add a star button on resources and a /favorites page. Stored in the visitor session cookie.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_favorites_visitor',
                ],
            ])
            ->add([
                'name' => 'selection_favorites_user',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'favorites',
                    'label' => 'Enable favorites for authenticated users', // @translate
                    'info' => 'Add a star button on resources and a /favorites page. A default selection holds the favorites.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_favorites_user',
                ],
            ])
            ->add([
                'name' => 'selection_label_favorites',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'favorites',
                    'label' => 'Label for the favorites page', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_label_favorites',
                ],
            ])
            ->add([
                'name' => 'selection_selections_visitor',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selections',
                    'label' => 'Enable multi-selections for anonymous visitors', // @translate
                    'info' => 'Allow visitors to create multiple named lists. Independent of favorites.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_selections_visitor',
                ],
            ])
            ->add([
                'name' => 'selection_selections_user',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selections',
                    'label' => 'Enable multi-selections for authenticated users', // @translate
                    'info' => 'Allow users to create multiple named lists. Independent of favorites.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_selections_user',
                ],
            ])
            ->add([
                'name' => 'selection_label_selections',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selections',
                    'label' => 'Label for the selections page', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_label_selections',
                ],
            ])
            ->add([
                'name' => 'selection_convert_auto',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Auto-convert anonymous favorites on login', // @translate
                    'info' => 'When enabled, the anonymous favorites are appended silently to the user default selection at login, without showing the convert dialog.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_convert_auto',
                ],
            ])
            // TODO Add a third view with a button to let user choose disposition.
            ->add([
                'name' => 'selection_disposition',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Default layout of resources inside a single selection', // @translate
                    'value_options' => [
                        'flat' => 'Flat: plain list of resources', // @translate
                        'grouped' => 'Grouped: resources grouped into manual named groups', // @translate
                        'hierarchy' => 'Hierarchy: nested tree of groups and resources', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_disposition',
                ],
            ])

            ->add([
                'name' => 'selection_share',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                'element_group' => 'selection',
                'label' => 'Allow users to share their own selections', // @translate
                'value_options' => [
                        '' => 'Only admins can publish', // @translate
                        'moderate' => 'Any user may request publication; an admin must validate', // @translate
                        'open' => 'Any user may publish without validation', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_share',
                ],
            ])
            ->add([
                'name' => 'selection_share_redirect',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Redirect public dynamic selection page', // @translate
                    'info' => 'A dynamic selection is a saved search query. This option controls the page used to replay the query when the public selection url is opened.', // @translate
                    'value_options' => [
                        '' => 'Default selection page (no redirect)', // @translate
                        'items_browse' => 'Items browse page of the site', // @translate
                        'advanced_search_site' => 'Advanced search page of the site', // @translate
                    ] + $this->searchConfigs,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'selection_share_redirect',
                ],
            ])

            ->add([
                'name' => 'selection_hide_login',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Hide login button for anonymous visitors', // @translate
                    'info' => 'By default, when anonymous selection is disabled, a login button is shown to invite visitors to sign in. Enable this option to hide this login button.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_hide_login',
                ],
            ])

            ->add([
                'name' => 'selection_selectable_resources',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Resource types that can be added to favorites or selections', // @translate
                    'info' => 'Only checked resource types will display the favorite or selection button on their page. Default is items only.', // @translate
                    'value_options' => [
                        'items' => 'Items', // @translate
                        'media' => 'Medias', // @translate
                        'item_sets' => 'Item sets', // @translate
                        // 'annotations' => 'Annotations',
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_selectable_resources',
                ],
            ])
            ->add([
                'name' => 'selection_max_resources',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Maximum number of resources per selection', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_max_resources',
                    'min' => 0,
                    'step' => 1,
                    'placeholder' => 50,
                ],
            ])
            ->add([
                'name' => 'selection_per_page',
                'type' => Element\Number::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Number of resources per page', // @translate
                    'info' => 'Pagination size for /guest/favorites and the public show page.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_per_page',
                    'min' => 1,
                    'step' => 1,
                    'placeholder' => 25,
                ],
            ])

            ->add([
                'name' => 'selection_placement_button',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'themes_old',
                    'label' => 'Auto-insert the selection button on resource pages', // @translate
                    'value_options' => [
                        'before/items' => 'Item: Top', // @translate
                        'before/media' => 'Media: Top', // @translate
                        'before/item_sets' => 'Item set: Top', // @translate
                        'after/items' => 'Item: Bottom', // @translate
                        'after/media' => 'Media: Bottom', // @translate
                        'after/item_sets' => 'Item set: Bottom', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_placement_button',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'selection_placement_list',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'themes_old',
                    'label' => 'Auto-insert the user selection list on resource pages', // @translate
                    'value_options' => [
                        'before/items' => 'Item: Top', // @translate
                        'before/media' => 'Media: Top', // @translate
                        'before/item_sets' => 'Item set: Top', // @translate
                        'after/items' => 'Item: Bottom', // @translate
                        'after/media' => 'Media: Bottom', // @translate
                        'after/item_sets' => 'Item set: Bottom', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_placement_list',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'selection_resource_show_open',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Open the selection block by default on resource pages', // @translate
                    'info' => 'When the resource-page block "Selection" is collapsible, this option controls whether it is open or closed on first display.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_resource_show_open',
                ],
            ])
            ->add([
                'name' => 'selection_resource_show_open_list',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Open the user selection list by default on resource pages', // @translate
                    'info' => 'Same as above for the resource-page block that displays the user list of selections.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_resource_show_open_list',
                ],
            ])
            ->add([
                'name' => 'selection_individual_select',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Show per-resource checkboxes on the selection page for Bulk Export and Contact Us', // @translate
                    'info' => 'Adds a checkbox next to each resource of a selection so the user can pick a subset before exporting (Bulk Export) or contacting (Contact Us).', // @translate
                    'value_options' => [
                        'auto' => 'Auto: only when Bulk Export or Contact Us is installed and active on the page', // @translate
                        'no' => 'Never show the checkboxes', // @translate
                        'yes' => 'Always show the checkboxes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_individual_select',
                ],
            ])
            ->add([
                'name' => 'selection_append_items_browse_individual',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Add a checkbox on items in browse and search results pages to select them individually', // @translate
                    'info' => 'Currently only effective when the module Advanced Search is installed and used on the page.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_append_items_browse_individual',
                ],
            ])
            ->add([
                'name' => 'selection_label_guest_link',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Label of the navigation link displayed in the guest account', // @translate
                    'info' => 'Text used for the link that points to the personal selection page in the guest account navigation menu.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_label_guest_link',
                ],
            ])
            ->add([
                'name' => 'selection_warning_anonymous',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Warning message shown above the selection page for anonymous visitors', // @translate
                    'info' => 'Reminds anonymous visitors that their selection is stored in the session cookie and lost when the cookie is cleared.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_warning_anonymous',
                ],
            ])
            ->add([
                'name' => 'selection_anonymous_add_dialog',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Show a dialog when an anonymous visitor adds a resource', // @translate
                    'info' => 'A modal dialog appears the first time of the session, inviting the visitor to log in or register to keep favorites.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_anonymous_add_dialog',
                ],
            ])
            ->add([
                'name' => 'selection_anonymous_add_message',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Dialog message for anonymous visitor', // @translate
                    'info' => 'Placeholders: {url_login}, {url_register}.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_anonymous_add_message',
                    'rows' => 6,
                ],
            ])
        ;
    }

    public function setSearchConfigs(array $searchConfigs): self
    {
        $this->searchConfigs = $searchConfigs;
        return $this;
    }
}
