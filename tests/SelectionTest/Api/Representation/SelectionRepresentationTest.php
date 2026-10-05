<?php declare(strict_types=1);

namespace SelectionTest\Api\Representation;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Test\AbstractHttpControllerTestCase;
use SelectionTest\SelectionTestTrait;

/**
 * Tests of the thumbnail helpers of the selection representation.
 */
class SelectionRepresentationTest extends AbstractHttpControllerTestCase
{
    use SelectionTestTrait;

    /**
     * @var \Omeka\Api\Representation\UserRepresentation
     */
    protected $testUser;

    /**
     * @var int[]
     */
    protected array $createdDigitalObjects = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->testUser = $this->createUser('thumb@test.fr', 'toto', 'researcher');
    }

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->cleanupResources();
        foreach ($this->createdDigitalObjects as $id) {
            try {
                $this->api()->delete('digital_objects', $id);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdDigitalObjects = [];
        parent::tearDown();
    }

    protected function createItemWithHtmlMedia(string $title): ItemRepresentation
    {
        return $this->createItem($title, [
            'o:media' => [
                [
                    'o:ingester' => 'html',
                    'html' => '<p>Media</p>',
                ],
            ],
        ]);
    }

    protected function requireDigitalObject(): void
    {
        if (!class_exists(\DigitalObject\Entity\DigitalObject::class)
            || !$this->getServiceLocator()->get('Omeka\ApiAdapterManager')->has('digital_objects')
        ) {
            $this->markTestSkipped('Requires DigitalObject module.');
        }
    }

    /**
     * Persist a minimal file-backed digital object.
     *
     * The api requires an uploaded file or a url, so the entity is persisted
     * directly, like in the DigitalObject module tests.
     */
    protected function createDigitalObject(): AbstractResourceEntityRepresentation
    {
        $entityManager = $this->getEntityManager();
        $entity = new \DigitalObject\Entity\DigitalObject();
        $entity->setCreated(new \DateTime('now'));
        $entity->setStorageId(bin2hex(random_bytes(8)));
        $entity->setExtension('jpg');
        $entity->setMediaType('image/jpeg');
        $entity->setSha256(hash('sha256', uniqid('do', true)));
        $entity->setSize(1);
        $entity->setHasOriginal(true);
        $entity->setHasThumbnails(true);
        $entityManager->persist($entity);
        $entityManager->flush();
        $this->createdDigitalObjects[] = $entity->getId();
        return $this->api()->read('digital_objects', $entity->getId())->getContent();
    }

    protected function createItemLinkedToDigitalObject(string $title, int $doId, array $additionalData = []): ItemRepresentation
    {
        $property = $this->api()->search('properties', ['term' => 'dcterms:hasPart'])->getContent()[0];
        return $this->createItem($title, [
            'dcterms:hasPart' => [
                [
                    'type' => 'resource',
                    'property_id' => $property->id(),
                    'value_resource_id' => $doId,
                ],
            ],
        ] + $additionalData);
    }

    public function testEmptySelectionHasNoThumbnail(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Empty');

        $this->assertNull($selection->primaryResource());
        $this->assertNull($selection->primaryMedia());
        $this->assertNull($selection->thumbnailDisplayUrl('medium'));
    }

    public function testItemWithoutMediaIsPrimaryResourceWithoutThumbnail(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'No media');
        $item = $this->createItem('Item without media');
        $this->createSelectionResource($selection->id(), $item->id());

        $selection = $this->api()->read('selections', $selection->id())->getContent();
        $primaryResource = $selection->primaryResource();

        $this->assertInstanceOf(ItemRepresentation::class, $primaryResource);
        $this->assertSame($item->id(), $primaryResource->id());
        $this->assertNull($selection->primaryMedia());
        $this->assertNull($selection->thumbnailDisplayUrl('medium'));
    }

    public function testItemMediaIsPrimaryMediaWithThumbnail(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'With media');
        $item = $this->createItemWithHtmlMedia('Item with media');
        $this->createSelectionResource($selection->id(), $item->id());

        $selection = $this->api()->read('selections', $selection->id())->getContent();
        $media = $selection->primaryMedia();

        $this->assertInstanceOf(MediaRepresentation::class, $media);
        $this->assertSame($item->primaryMedia()->id(), $media->id());
        $this->assertSame($media->thumbnailUrl('medium'), $selection->thumbnailDisplayUrl('medium'));
    }

    public function testMediaInSelectionIsPrimaryMedia(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Media only');
        $item = $this->createItemWithHtmlMedia('Item');
        $mediaId = $item->primaryMedia()->id();
        $this->createSelectionResource($selection->id(), $mediaId);

        $selection = $this->api()->read('selections', $selection->id())->getContent();

        $this->assertInstanceOf(MediaRepresentation::class, $selection->primaryResource());
        $this->assertSame($mediaId, $selection->primaryResource()->id());
        $this->assertSame($mediaId, $selection->primaryMedia()->id());
    }

    public function testFirstResourceWithMediaIsUsed(): void
    {
        $selection = $this->createSelection($this->testUser->id(), 'Mixed');
        $itemWithout = $this->createItem('Without media');
        $itemWith = $this->createItemWithHtmlMedia('With media');
        $this->createSelectionResource($selection->id(), $itemWithout->id());
        $this->createSelectionResource($selection->id(), $itemWith->id());

        $selection = $this->api()->read('selections', $selection->id())->getContent();

        $this->assertSame($itemWithout->id(), $selection->primaryResource()->id());
        $this->assertSame($itemWith->primaryMedia()->id(), $selection->primaryMedia()->id());
        $this->assertNotNull($selection->thumbnailDisplayUrl('medium'));
    }

    public function testDigitalObjectInSelectionIsPrimaryMedia(): void
    {
        $this->requireDigitalObject();
        $selection = $this->createSelection($this->testUser->id(), 'Digital object');
        $do = $this->createDigitalObject();
        $this->createSelectionResource($selection->id(), $do->id());

        $selection = $this->api()->read('selections', $selection->id())->getContent();

        $this->assertSame($do->id(), $selection->primaryResource()->id());
        $this->assertSame($do->id(), $selection->primaryMedia()->id());
        $this->assertSame($do->thumbnailUrl('medium'), $selection->thumbnailDisplayUrl('medium'));
    }

    public function testItemLinkedToDigitalObjectFallsBackToIt(): void
    {
        $this->requireDigitalObject();
        $selection = $this->createSelection($this->testUser->id(), 'Linked digital object');
        $do = $this->createDigitalObject();
        $item = $this->createItemLinkedToDigitalObject('Item with digital object', $do->id());
        $this->assertNull($item->primaryMedia());
        $this->createSelectionResource($selection->id(), $item->id());

        $selection = $this->api()->read('selections', $selection->id())->getContent();

        $this->assertSame($item->id(), $selection->primaryResource()->id());
        $this->assertSame($do->id(), $selection->primaryMedia()->id());
        $this->assertNotNull($selection->thumbnailDisplayUrl('medium'));
        $this->assertSame($do->thumbnailUrl('medium'), $selection->thumbnailDisplayUrl('medium'));
    }

    public function testItemNativeMediaPreferredOverDigitalObject(): void
    {
        $this->requireDigitalObject();
        $selection = $this->createSelection($this->testUser->id(), 'Media and digital object');
        $do = $this->createDigitalObject();
        $item = $this->createItemLinkedToDigitalObject('Item with both', $do->id(), [
            'o:media' => [
                [
                    'o:ingester' => 'html',
                    'html' => '<p>Media</p>',
                ],
            ],
        ]);
        $this->createSelectionResource($selection->id(), $item->id());

        $selection = $this->api()->read('selections', $selection->id())->getContent();

        $this->assertSame($item->primaryMedia()->id(), $selection->primaryMedia()->id());
    }
}
