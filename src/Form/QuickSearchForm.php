<?php declare(strict_types=1);

namespace Selection\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Form;

class QuickSearchForm extends Form
{
    public function init(): void
    {
        $this->setAttribute('method', 'get');
        $this->setAttribute('id', 'quick-search-form');

        // No csrf: see main search form.
        $this->remove('csrf');

        $this
            ->add([
                'name' => 'label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Label', // @translate
                ],
                'attributes' => [
                    'id' => 'label',
                ],
            ])
            ->add([
                'name' => 'owner_id',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'User by id', // @translate
                ],
                'attributes' => [
                    'id' => 'owner_id',
                ],
            ])
            ->add([
                'name' => 'is_public',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Public', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'is_public',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'is_dynamic',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Dynamic', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'is_dynamic',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'submit',
                    'type' => 'submit',
                    'class' => 'button',
                ],
            ]);
    }
}
