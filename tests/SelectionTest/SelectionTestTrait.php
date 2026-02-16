<?php declare(strict_types=1);

namespace SelectionTest;

use CommonTest\JobTestTrait;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Selection\Api\Representation\SelectionRepresentation;
use Selection\Api\Representation\SelectionResourceRepresentation;

/**
 * Shared test helpers for Selection module tests.
 *
 * Extends CommonTest\JobTestTrait with Selection-specific helpers.
 */
trait SelectionTestTrait
{
    use JobTestTrait {
        JobTestTrait::cleanupResources as baseCleanupResources;
    }

    /**
     * @var array List of created selection IDs for cleanup.
     */
    protected array $createdSelections = [];

    /**
     * @var array List of created selection resource IDs for cleanup.
     */
    protected array $createdSelectionResources = [];

    /**
     * @var array List of created user IDs for cleanup.
     */
    protected array $createdUsers = [];

    /**
     * @var array List of created site IDs for cleanup.
     */
    protected array $createdSites = [];

    /**
     * Login as specific user.
     *
     * @param string $email User email.
     * @param string $password User password.
     */
    protected function login(string $email, string $password): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        $auth->authenticate();
    }

    /**
     * Get the current authenticated user.
     */
    protected function getCurrentUser()
    {
        return $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
    }

    /**
     * Create a test item.
     *
     * @param string $title Item title.
     * @param array $additionalData Additional data.
     * @return ItemRepresentation
     */
    protected function createItem(string $title, array $additionalData = []): ItemRepresentation
    {
        $data = array_merge([
            'dcterms:title' => [
                [
                    'type' => 'literal',
                    '@value' => $title,
                ],
            ],
        ], $additionalData);

        return $this->createTrackedItem($data);
    }

    /**
     * Create a test site.
     *
     * @param string $slug Site slug.
     * @param string $title Site title.
     * @param bool $isPublic Whether the site is public.
     * @return SiteRepresentation
     */
    protected function createSite(string $slug, string $title, bool $isPublic = true): SiteRepresentation
    {
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:title' => $title,
            'o:is_public' => $isPublic,
        ]);
        $site = $response->getContent();
        $this->createdSites[] = $site->id();

        return $site;
    }

    /**
     * Create a test user.
     *
     * @param string $email User email.
     * @param string $password User password.
     * @param string $role User role.
     * @param bool $isActive Whether the user is active.
     * @return UserRepresentation
     */
    protected function createUser(
        string $email,
        string $password,
        string $role = 'researcher',
        bool $isActive = true
    ): UserRepresentation {
        $response = $this->api()->create('users', [
            'o:email' => $email,
            'o:name' => explode('@', $email)[0],
            'o:role' => $role,
            'o:is_active' => $isActive,
        ]);
        $user = $response->getContent();
        $this->createdUsers[] = $user->id();

        // Set password via entity.
        $em = $this->getEntityManager();
        $userEntity = $em->find(\Omeka\Entity\User::class, $user->id());
        $userEntity->setPassword($password);
        $em->flush();

        return $user;
    }

    /**
     * Create a selection.
     *
     * @param int|null $ownerId Owner user ID (null for current user).
     * @param string $label Selection label.
     * @param bool $isPublic Whether the selection is public.
     * @param bool|null $isValidated Validation status (null, true, or false).
     * @return SelectionRepresentation
     */
    protected function createSelection(
        ?int $ownerId = null,
        string $label = 'Test Selection',
        bool $isPublic = false,
        ?bool $isValidated = null
    ): SelectionRepresentation {
        $data = [
            'o:label' => $label,
            'o:is_public' => $isPublic,
            'o:is_validated' => $isValidated,
        ];
        if ($ownerId) {
            $data['o:owner'] = ['o:id' => $ownerId];
        }

        $response = $this->api()->create('selections', $data);
        $selection = $response->getContent();
        $this->createdSelections[] = $selection->id();

        return $selection;
    }

    /**
     * Create a selection resource.
     *
     * @param int $selectionId Selection ID.
     * @param int $resourceId Resource ID.
     * @return SelectionResourceRepresentation
     */
    protected function createSelectionResource(int $selectionId, int $resourceId): SelectionResourceRepresentation
    {
        $response = $this->api()->create('selection_resources', [
            'o:selection' => ['o:id' => $selectionId],
            'o:resource' => ['o:id' => $resourceId],
        ]);
        $selectionResource = $response->getContent();
        $this->createdSelectionResources[] = $selectionResource->id();

        return $selectionResource;
    }

    /**
     * Add a resource to a user's default selection.
     *
     * @param int $ownerId Owner user ID.
     * @param int $resourceId Resource ID.
     * @return SelectionResourceRepresentation
     */
    protected function addToSelection(int $ownerId, int $resourceId): SelectionResourceRepresentation
    {
        $response = $this->api()->create('selection_resources', [
            'o:owner' => ['o:id' => $ownerId],
            'o:resource' => ['o:id' => $resourceId],
        ]);
        $selectionResource = $response->getContent();
        $this->createdSelectionResources[] = $selectionResource->id();

        return $selectionResource;
    }

    /**
     * Get settings service.
     */
    protected function settings()
    {
        return $this->getServiceLocator()->get('Omeka\Settings');
    }

    /**
     * Set a module setting.
     *
     * @param string $key Setting key.
     * @param mixed $value Setting value.
     */
    protected function setSetting(string $key, $value): void
    {
        $this->settings()->set($key, $value);
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        // Delete created selection resources first.
        foreach ($this->createdSelectionResources as $id) {
            try {
                $this->api()->delete('selection_resources', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSelectionResources = [];

        // Delete created selections.
        foreach ($this->createdSelections as $id) {
            try {
                $this->api()->delete('selections', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSelections = [];

        // Clean up base resources (items, etc.)
        $this->baseCleanupResources();

        // Delete created users.
        foreach ($this->createdUsers as $id) {
            try {
                $this->api()->delete('users', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdUsers = [];

        // Delete created sites.
        foreach ($this->createdSites as $id) {
            try {
                $this->api()->delete('sites', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSites = [];
    }

    /**
     * Assert that a selection exists with the given criteria.
     *
     * @param array $criteria Search criteria.
     * @param int $expectedCount Expected number of matching selections.
     */
    protected function assertSelectionExists(array $criteria, int $expectedCount = 1): void
    {
        $selections = $this->api()->search('selections', $criteria)->getContent();
        $this->assertCount($expectedCount, $selections, sprintf(
            'Expected %d selection(s) matching criteria, found %d',
            $expectedCount,
            count($selections)
        ));
    }

    /**
     * Assert that a selection resource exists.
     *
     * @param int $selectionId Selection ID.
     * @param int $resourceId Resource ID.
     */
    protected function assertSelectionResourceExists(int $selectionId, int $resourceId): void
    {
        $selectionResources = $this->api()->search('selection_resources', [
            'selection_id' => $selectionId,
            'resource_id' => $resourceId,
        ])->getContent();
        $this->assertCount(1, $selectionResources, sprintf(
            'Expected selection resource with selection_id=%d and resource_id=%d to exist',
            $selectionId,
            $resourceId
        ));
    }

    /**
     * Assert that no selection resources exist for a user.
     *
     * @param int $ownerId Owner user ID.
     */
    protected function assertNoSelectionResourcesForUser(int $ownerId): void
    {
        $selectionResources = $this->api()->search('selection_resources', [
            'owner_id' => $ownerId,
        ])->getContent();
        $this->assertEmpty($selectionResources, sprintf(
            'Expected no selection resources for owner_id=%d, found %d',
            $ownerId,
            count($selectionResources)
        ));
    }
}
