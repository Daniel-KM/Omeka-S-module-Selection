<?php declare(strict_types=1);

namespace Selection\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Selection module'; // @translate

    protected $elementGroups = [
        'selection' => 'Selection', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'selection')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'selection_disable_anonymous',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Disable selection stored in session for anonymous visitors', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_disable_anonymous',
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
                'name' => 'selection_placement_button',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Display selection button', // @translate
                    'value_options' => [
                        'block/items' => 'Items: Via resource block or custom theme', // @translate
                        'block/media' => 'Media: Via resource block or custom theme', // @translate
                        'block/item_sets' => 'Item set: Via resource block or custom theme', // @translate
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
                    'element_group' => 'selection',
                    'label' => 'Display selection list', // @translate
                    'value_options' => [
                        'block/items' => 'Items: Via resource block or custom theme', // @translate
                        'block/media' => 'Media: Via resource block or custom theme', // @translate
                        'block/item_sets' => 'Item set: Via resource block or custom theme', // @translate
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
            // TODO Add a third view with a button to let user choose disposition.
            ->add([
                'name' => 'selection_browse_disposition',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Default disposition of page Selection', // @translate
                    'info' => 'When the url query argument "disposition" is set, it overrides this option.', // @ŧranslate
                    'value_options' => [
                        'list' => 'List', // @translate
                        'hierarchy' => 'Hierarchy', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection_browse_disposition',
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
                'name' => 'selection_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Label for the page Selection', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_label',
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
}
