<?php declare(strict_types=1);

namespace Selection\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

class SendMessageForm extends Form implements InputFilterProviderInterface
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
                ],
                'attributes' => [
                    'id' => 'body',
                    'rows' => 10,
                    'required' => true,
                ],
            ])
            ->add([
                'name' => 'myself',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Copy to me', // @translate
                    'value_options' => [
                        '' => 'No copy', // @translate
                        'cc' => 'Copy (cc)', // @translate
                        'bcc' => 'Hidden copy (bcc)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'selection-send-myself',
                    'value' => '',
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

    public function getInputFilterSpecification(): array
    {
        // The "required" html attribute does not drive Laminas validation:
        // declare the input filter explicitly. "subject" is optional and
        // "myself" is a radio whose empty option means "no copy".
        return [
            'subject' => [
                'required' => false,
                'allow_empty' => true,
            ],
            'myself' => [
                'required' => false,
                'allow_empty' => true,
            ],
        ];
    }
}
