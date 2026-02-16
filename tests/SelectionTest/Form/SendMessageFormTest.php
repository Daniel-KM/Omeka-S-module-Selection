<?php declare(strict_types=1);

namespace SelectionTest\Form;

use Laminas\Form\Element;
use PHPUnit\Framework\TestCase;
use Selection\Form\SendMessageForm;

/**
 * Unit tests for the SendMessageForm.
 */
class SendMessageFormTest extends TestCase
{
    /**
     * @var SendMessageForm
     */
    protected $form;

    public function setUp(): void
    {
        $this->form = new SendMessageForm();
        $this->form->init();
    }

    public function testFormHasCorrectName(): void
    {
        $this->assertEquals('send-message', $this->form->getName());
    }

    public function testFormHasCorrectId(): void
    {
        $this->assertEquals('selection-send-message-form', $this->form->getAttribute('id'));
    }

    public function testFormHasCorrectMethod(): void
    {
        $this->assertEquals('post', $this->form->getAttribute('method'));
    }

    public function testFormHasSelectionIdElement(): void
    {
        $this->assertTrue($this->form->has('selection_id'));
        $this->assertInstanceOf(Element\Hidden::class, $this->form->get('selection_id'));
    }

    public function testFormHasSubjectElement(): void
    {
        $this->assertTrue($this->form->has('subject'));
        $element = $this->form->get('subject');
        $this->assertInstanceOf(Element\Text::class, $element);
        $this->assertFalse($element->getAttribute('required'));
    }

    public function testFormHasBodyElement(): void
    {
        $this->assertTrue($this->form->has('body'));
        $element = $this->form->get('body');
        $this->assertInstanceOf(Element\Textarea::class, $element);
        $this->assertTrue($element->getAttribute('required'));
        $this->assertEquals(10, $element->getAttribute('rows'));
    }

    public function testFormHasSubmitElement(): void
    {
        $this->assertTrue($this->form->has('submit'));
        $element = $this->form->get('submit');
        $this->assertInstanceOf(Element\Button::class, $element);
        $this->assertEquals('submit', $element->getAttribute('type'));
    }

    public function testSubmitButtonHasSpinnerAttribute(): void
    {
        $element = $this->form->get('submit');
        $this->assertEquals('true', $element->getAttribute('data-spinner'));
    }

    public function testFormValidatesWithRequiredFields(): void
    {
        $data = [
            'selection_id' => '1',
            'subject' => 'Test Subject',
            'body' => 'Test message body',
        ];

        $this->form->setData($data);
        $this->assertTrue($this->form->isValid());
    }

    public function testFormValidatesWithEmptySubject(): void
    {
        $data = [
            'selection_id' => '1',
            'subject' => '',
            'body' => 'Test message body',
        ];

        $this->form->setData($data);
        $this->assertTrue($this->form->isValid());
    }

    public function testBodyElementIsRequired(): void
    {
        $element = $this->form->get('body');

        $this->assertTrue($element->getAttribute('required'));
    }
}
