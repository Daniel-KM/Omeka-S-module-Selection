<?php declare(strict_types=1);

namespace Selection\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

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
        'selection' => 'Selection', // @translate
        'themes_old' => 'Old themes', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'selection')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'selection_mode_visitor',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Selection mode for anonymous visitors', // @translate
                    'value_options' => [
                        'none' => 'Disabled', // @translate
                        'simple' => 'Simple favorites (single list, star button)', // @translate
                        'standard' => 'Multiple flat selections', // @translate
                        'nested' => 'Nested selections', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_mode_visitor',
                ],
            ])
            ->add([
                'name' => 'selection_mode_user',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Selection mode for authenticated users', // @translate
                    'value_options' => [
                        'simple' => 'Simple favorites (single list, star button)', // @translate
                        'standard' => 'Multiple flat selections', // @translate
                        'nested' => 'Nested selections', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_mode_user',
                ],
            ])
            // TODO Add a third view with a button to let user choose disposition.
            ->add([
                'name' => 'selection_disposition',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Default disposition of page Selection', // @translate
                    'info' => 'When the url query argument "disposition" is set, it overrides this option.', // @ŧranslate
                    'value_options' => [
                        'flat' => 'List of resources', // @translate
                        'grouped' => 'List of selections', // @translate
                        'hierarchy' => 'Nested selections', // @translate
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
                        '' => 'Only admins', // @translate
                        'moderate' => 'Any user with moderation', // @translate
                        'open' => 'Any user without moderation', // @translate
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
                    'label' => 'Redirect public selection page', // @translate
                    'value_options' => [
                        '' => 'Default selection page', // @translate
                        'items_browse' => 'Items browse page', // @translate
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
                    'label' => 'Display a login button when selection is disabled for anonymous', // @translate
                    'info' => 'When selection is disabled for anonymous visitors, display a login button instead of hiding the selection.', // @translate
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
                    'label' => 'Limit selections to specific resources', // @translate
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
                    'label' => 'Maximum resources per selection', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_max_resources',
                    'min' => 0,
                    'step' => 1,
                    'placeholder' => 50,
                ],
            ])

            ->add([
                'name' => 'selection_placement_button',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'themes_old',
                    'label' => 'Display selection button (old themes)', // @translate
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
                    'label' => 'Display selection list (old themes)', // @translate
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
                    'label' => 'Open the selection block by default in resource page', // @translate
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
                    'label' => 'Open the selection list block by default in resource page', // @translate
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
                    'label' => 'Add individual checkboxes for modules Bulk Export and Contact Us', // @translate
                    'value_options' => [
                        'auto' => 'When needed', // @translate
                        'no' => 'No', // @translate
                        'yes' => 'Yes', // @translate
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
                    'label' => 'Add a checkbox to select resources individually in lists (browse and search)', // @translate
                    'info' => 'This option is used only with the module Advanced Search for now.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_append_items_browse_individual',
                ],
            ])
            ->add([
                'name' => 'selection_label_visitor',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Label for selection (anonymous visitors)', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_label_visitor',
                ],
            ])
            ->add([
                'name' => 'selection_label_user',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Label for selection (authenticated users)', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_label_user',
                ],
            ])
            ->add([
                'name' => 'selection_label_guest_link',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Label for the link in guest account', // @translate
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
                    'label' => 'Warning for selection by anonymous', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_warning_anonymous',
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
