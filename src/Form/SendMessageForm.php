<?php declare(strict_types=1);

namespace Selection\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class SendMessageForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('id', 'selection-send-message-form')
            ->setAttribute('class', 'form-send-message jsend-form')
            ->setAttribute('method', 'post')
            ->setName('send-message');

        $this
            ->add([
                'name' => 'selection_id',
                'type' => Element\Hidden::class,
                'attributes' => [
                    'id' => 'selection-id',
                ],
            ])
            ->add([
                'name' => 'subject',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Subject', // @translate
                ],
                'attributes' => [
                    'id' => 'subject',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'body',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Message', // @translate
                    'label_attributes' => [
                        'class' => 'required',
                    ],
                ],
                'attributes' => [
                    'id' => 'body',
                    'rows' => 10,
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Send message', // @translate
                ],
                'attributes' => [
                    'id' => 'submit',
                    'type' => 'submit',
                    'class' => 'submit',
                    'data-spinner' => 'true',
                ],
            ])
        ;
    }
}
