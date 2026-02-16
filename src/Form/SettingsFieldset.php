<?php declare(strict_types=1);

namespace Selection\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Selection'; // @translate

    protected $elementGroups = [
        'selection' => 'Selection moderation', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'selection')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'selection_message_approve_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Subject of the approval email to the owner', // @translate
                    'info' => 'Placeholders: {main_title}, {main_url}, {selection_label}, {selection_url}.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_message_approve_subject',
                ],
            ])
            ->add([
                'name' => 'selection_message_approve_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Default approval message to the owner', // @translate
                    'info' => 'Placeholders: {main_title}, {main_url}, {selection_label}, {selection_url}.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_message_approve_body',
                    'rows' => 5,
                ],
            ])
            ->add([
                'name' => 'selection_message_reject_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Subject of the rejection email to the owner', // @translate
                    'info' => 'Placeholders: {main_title}, {main_url}, {selection_label}.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_message_reject_subject',
                ],
            ])
            ->add([
                'name' => 'selection_message_reject_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'selection',
                    'label' => 'Default rejection message to the owner', // @translate
                    'info' => 'Placeholders: {main_title}, {main_url}, {selection_label}.', // @translate
                ],
                'attributes' => [
                    'id' => 'selection_message_reject_body',
                    'rows' => 5,
                ],
            ])
        ;
    }
}
