<?php declare(strict_types=1);

namespace Selection\Controller\Admin;

use Common\Stdlib\PsrMessage;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Form\ConfirmForm;
use Selection\Form\SendMessageForm;

class SelectionController extends AbstractActionController
{
    public function browseAction()
    {
        $params = $this->params()->fromQuery();

        $this->setBrowseDefaults('created', 'desc');
        if (!isset($params['sort_by'])) {
            $params['sort_by'] = 'created';
            $params['sort_order'] = 'desc';
        }

        $this->browse()->setDefaults('selections');

        $response = $this->api()->search('selections', $params);
        $this->paginator($response->getTotalResults());

        $settings = $this->settings();

        $defaultMessages = $this->getDefaultMessages();
        $subject = $settings->get('selection_message_approve_subject')
            ?: $this->translate($defaultMessages['selection_message_approve_subject']);
        $body = $settings->get('selection_message_approve_body')
            ?: $this->translate($defaultMessages['selection_message_approve_body']);

        $messageData = [
            'subject' => $this->fillMessage($subject),
            'body' => $this->fillMessage($body),
        ];

        /** @var \Selection\Form\SendMessageForm $formSendMessage */
        $formSendMessage = $this->getForm(SendMessageForm::class);
        $formSendMessage
            ->setAttribute('action', $this->url()->fromRoute('admin/selection/default', ['action' => 'send-message'], true))
            ->setAttribute('id', 'selection-send-message-form')
            ->populateValues($messageData);

        /** @var \Omeka\Form\ConfirmForm $formDeleteSelected */
        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected
            ->setAttribute('id', 'confirm-delete-selected')
            ->setAttribute('action', $this->url()->fromRoute('admin/selection/default', ['action' => 'batch-delete'], true))
            ->setButtonLabel('Confirm Delete'); // @translate

        /** @var \Omeka\Form\ConfirmForm $formDeleteAll */
        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll
            ->setAttribute('id', 'confirm-delete-all')
            ->setAttribute('action', $this->url()->fromRoute('admin/selection/default', ['action' => 'batch-delete-all'], true))
            ->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll
            ->get('submit')->setAttribute('disabled', true);

        $selections = $response->getContent();

        return new ViewModel([
            'selections' => $selections,
            'resources' => $selections,
            'formSendMessage' => $formSendMessage,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
        ]);
    }

    public function showAction()
    {
        return $this->redirect()->toRoute('admin/selection', ['action' => 'browse']);
    }

    public function showDetailsAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('selections', $this->params('id'));
        $selection = $response->getContent();

        $view = new ViewModel([
            'linkTitle' => $linkTitle,
            'resource' => $selection,
            'selection' => $selection,
        ]);
        return $view
            ->setTemplate('selection/admin/selection/show-details')
            ->setTerminal(true);
    }

    public function deleteConfirmAction()
    {
        $linkTitle = (bool) $this->params()->fromQuery('link-title', true);
        $response = $this->api()->read('selections', $this->params('id'));
        $selection = $response->getContent();

        $view = new ViewModel([
            'selection' => $selection,
            'resource' => $selection,
            'resourceLabel' => 'selection', // @translate
            'partialPath' => 'selection/admin/selection/show-details',
            'linkTitle' => $linkTitle,
        ]);
        return $view
            ->setTerminal(true)
            ->setTemplate('common/delete-confirm-details');
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('selections', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Selection successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/selection', ['action' => 'browse'], true);
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one selection to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('selections', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Selections successfully deleted'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $this->jobDispatcher()->dispatch(\Omeka\Job\BatchDelete::class, [
                'resource' => 'selections',
                'query' => $query,
            ]);
            $this->messenger()->addSuccess('Deleting selections. This may take a while.'); // @translate
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    /* Ajax actions */

    /**
     * Toggle public status of a selection.
     */
    public function togglePublicAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = $this->params('id');

        try {
            $selection = $this->api()->read('selections', $id)->getContent();
        } catch (\Exception $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        $isPublic = !$selection->isPublic();
        $data = ['o:is_public' => $isPublic];

        try {
            $this->api()->update('selections', $id, $data, [], ['isPartial' => true]);
            // Re-read to get the updated validation status (auto-validated by adapter).
            $selection = $this->api()->read('selections', $id)->getContent();
        } catch (\Exception $e) {
            return $this->jSend()->error(null, $this->translate(
                'An internal error occurred.' // @translate
            ));
        }

        // Get updated validation status.
        $isValidated = $selection->isValidated();
        if ($isValidated === null) {
            $validatedStatus = 'undetermined';
            $validatedLabel = $this->translate('Undetermined'); // @translate
        } elseif ($isValidated) {
            $validatedStatus = 'validated';
            $validatedLabel = $this->translate('Validated'); // @translate
        } else {
            $validatedStatus = 'rejected';
            $validatedLabel = $this->translate('Rejected'); // @translate
        }

        return $this->jSend()->success([
            'selection' => [
                'id' => $id,
                'is_public' => $isPublic,
                'status' => $isPublic ? 'public' : 'private',
                'statusLabel' => $isPublic ? $this->translate('Public') : $this->translate('Private'), // @translate
                'is_validated' => $isValidated,
                'validatedStatus' => $validatedStatus,
                'validatedStatusLabel' => $validatedLabel,
            ],
        ]);
    }

    /**
     * Toggle validated status of a selection.
     */
    public function toggleValidatedAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = $this->params('id');

        try {
            $selection = $this->api()->read('selections', $id)->getContent();
        } catch (\Exception $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        // Private selections cannot be moderated.
        if (!$selection->isPublic()) {
            return $this->jSend()->fail(null, $this->translate(
                'Private selections cannot be moderated.' // @translate
            ), HttpResponse::STATUS_CODE_400);
        }

        // Cycle through: null (undetermined) -> true (validated) -> false (rejected) -> null
        $currentValidated = $selection->isValidated();
        if ($currentValidated === null) {
            $newValidated = true;
            $status = 'validated';
            $statusLabel = $this->translate('Validated'); // @translate
        } elseif ($currentValidated === true) {
            $newValidated = false;
            $status = 'rejected';
            $statusLabel = $this->translate('Rejected'); // @translate
        } else {
            $newValidated = null;
            $status = 'undetermined';
            $statusLabel = $this->translate('Undetermined'); // @translate
        }

        $data = ['o:is_validated' => $newValidated];

        try {
            $this->api()->update('selections', $id, $data, [], ['isPartial' => true]);
        } catch (\Exception $e) {
            return $this->jSend()->error(null, $this->translate(
                'An internal error occurred.' // @translate
            ));
        }

        return $this->jSend()->success([
            'selection' => [
                'id' => $id,
                'is_validated' => $newValidated,
                'status' => $status,
                'statusLabel' => $statusLabel,
            ],
        ]);
    }

    /**
     * Send a message to the selection owner.
     */
    public function sendMessageAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $params = $this->params();
        $id = $params->fromPost('selection_id') ?: $params->fromRoute('id');

        try {
            $selection = $this->api()->read('selections', $id)->getContent();
        } catch (\Exception $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        $user = $this->identity();
        if (!$user) {
            return $this->jSend()->fail(null, $this->translate(
                'Unauthorized access.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        $body = (string) $params->fromPost('body');
        $body = trim($body);

        if (!strlen($body)) {
            return $this->jSend()->fail(null, $this->translate(
                'Empty message.' // @translate
            ));
        }

        if (mb_strlen($body) > 10000) {
            return $this->jSend()->fail(null, $this->translate(
                'Too long message.' // @translate
            ));
        }

        $subject = $params->fromPost('subject');
        $subject = trim((string) $subject);
        if (!strlen($subject)) {
            $defaultMessages = $this->getDefaultMessages();
            $subject = $this->settings()->get('selection_message_approve_subject')
                ?: $this->translate($defaultMessages['selection_message_approve_subject']);
            $subject = $this->fillMessage($subject, $selection);
        }

        if (mb_strlen($subject) > 190) {
            return $this->jSend()->fail(null, $this->translate(
                'Too long subject.' // @translate
            ));
        }

        $owner = $selection->owner();
        if (!$owner) {
            return $this->jSend()->fail(null, $this->translate(
                'No owner defined for this selection.' // @translate
            ));
        }

        $toEmail = $owner->email();
        if (!$toEmail) {
            return $this->jSend()->fail(null, $this->translate(
                'No email defined for this selection owner.' // @translate
            ));
        }

        $to = [$toEmail => $owner->name()];

        /** @see \Common\Mvc\Controller\Plugin\SendEmail */
        $result = $this->sendEmail($body, $subject, $to);
        if (!$result) {
            return $this->jSend()->error(null, $this->translate(
                'Sorry, the message cannot be sent. Contact the administrator.' // @translate
            ));
        }

        $message = new PsrMessage(
            'Message successfully sent to {email}.', // @translate
            ['email' => $toEmail]
        );
        return $this->jSend()->success([
            'selection' => ['id' => $id],
        ], $message->setTranslator($this->translator()));
    }

    /**
     * Get default messages from config.
     */
    protected function getDefaultMessages(): array
    {
        $config = $this->getEvent()->getApplication()->getServiceManager()->get('Config');
        return $config['selection']['settings'] ?? [];
    }

    /**
     * Fill a message with placeholders (moustache style).
     */
    protected function fillMessage(?string $message, $selection = null): string
    {
        if (empty($message)) {
            return (string) $message;
        }

        $plugins = $this->getPluginManager();
        $url = $plugins->get('url');
        $settings = $plugins->get('settings')();

        $replace = [
            '{ip}' => (new RemoteAddress())->getIpAddress(),
            '{main_title}' => $settings->get('installation_title', 'Omeka S'),
            '{main_url}' => $url->fromRoute('top', [], ['force_canonical' => true]),
        ];

        if ($selection) {
            $replace['{selection_label}'] = $selection->label();
            $replace['{selection_url}'] = $selection->isPublic() ? $selection->siteUrl() : '';
        }

        return strtr($message, $replace);
    }
}
