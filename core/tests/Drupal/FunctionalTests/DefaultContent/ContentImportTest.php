<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\DefaultContent;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\FileInterface;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\media\MediaInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Psr\Log\LogLevel;

/**
 * @covers \Drupal\Core\DefaultContent\Importer
 * @group DefaultContent
 * @group Recipe
 */
class ContentImportTest extends BrowserTestBase {

  use EntityReferenceFieldCreationTrait;
  use MediaTypeCreationTrait;
  use RecipeTestTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_translation',
    'entity_test',
    'media',
    'menu_link_content',
    'node',
    'path',
    'path_alias',
    'system',
    'taxonomy',
    'user',
  ];

  private readonly string $contentDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    BlockContentType::create(['id' => 'basic', 'label' => 'Basic'])->save();
    block_content_add_body_field('basic');

    $this->createVocabulary(['vid' => 'tags']);
    $this->createMediaType('image', ['id' => 'image']);
    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalCreateContentType(['type' => 'article']);
    $this->createEntityReferenceField('node', 'article', 'field_tags', 'Tags', 'taxonomy_term');

    // Create a field with custom serialization, so we can ensure that the
    // importer handles that properly.
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'taxonomy_term',
      'field_name' => 'field_serialized_stuff',
      'type' => 'serialized_property_item_test',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'tags',
    ])->save();

    ConfigurableLanguage::createFromLangcode('fr')->save();
    ContentLanguageSettings::create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'article',
    ])
      ->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->save();

    $this->contentDir = $this->getDrupalRoot() . '/core/tests/fixtures/default_content';
  }

  /**
   * Tests importing content directly, via the API.
   */
  public function testDirectContentImport(): void {
    $logger = new TestLogger();

    /** @var \Drupal\Core\DefaultContent\Importer $importer */
    $importer = $this->container->get(Importer::class);
    $importer->setLogger($logger);
    $importer->importContent(new Finder($this->contentDir));

    $this->assertContentWasImported();
    // We should see a warning about importing a file entity associated with a
    // file that doesn't exist.
    $predicate = function (array $record): bool {
      return (
        $record['message'] === 'File entity %name was imported, but the associated file (@path) was not found.' &&
        $record['context']['%name'] === 'dce9cdc3-d9fc-4d37-849d-105e913bb5ad.png' &&
        $record['context']['@path'] === $this->contentDir . '/file/dce9cdc3-d9fc-4d37-849d-105e913bb5ad.png'
      );
    };
    $this->assertTrue($logger->hasRecordThatPasses($predicate, LogLevel::WARNING));
  }

  /**
   * Asserts that the default content was imported as expected.
   */
  private function assertContentWasImported(): void {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = $this->container->get(EntityRepositoryInterface::class);

    $node = $entity_repository->loadEntityByUuid('node', 'e1714f23-70c0-4493-8e92-af1901771921');
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('Crikey it works!', $node->body->value);
    $this->assertSame('article', $node->bundle());
    $this->assertSame('Test Article', $node->label());
    $tag = $node->field_tags->entity;
    $this->assertInstanceOf(TermInterface::class, $tag);
    $this->assertSame('Default Content', $tag->label());
    $this->assertSame('tags', $tag->bundle());
    $this->assertSame('550f86ad-aa11-4047-953f-636d42889f85', $tag->uuid());
    // The tag carries a field with serialized data, so ensure it came through
    // properly.
    $this->assertSame('a:2:{i:0;s:2:"Hi";i:1;s:6:"there!";}', $tag->field_serialized_stuff->value);
    $owner = $node->getOwner();
    $this->assertSame('Naomi Malone', $owner->getAccountName());
    $this->assertSame('94503467-be7f-406c-9795-fc25baa22203', $owner->uuid());
    // The node's URL should use the path alias shipped with the recipe.
    $node_url = $node->toUrl()->toString();
    $this->assertSame(Url::fromUserInput('/test-article')->toString(), $node_url);

    $media = $entity_repository->loadEntityByUuid('media', '344b943c-b231-4d73-9669-0b0a2be12aa5');
    $this->assertInstanceOf(MediaInterface::class, $media);
    $this->assertSame('image', $media->bundle());
    $this->assertSame('druplicon.png', $media->label());
    $file = $media->field_media_image->entity;
    $this->assertInstanceOf(FileInterface::class, $file);
    $this->assertSame('druplicon.png', $file->getFilename());
    $this->assertSame('d8404562-efcc-40e3-869e-40132d53fe0b', $file->uuid());

    // Another file entity referencing an existing file should have the same
    // file URI -- in other words, it should have reused the existing file.
    $duplicate_file = $entity_repository->loadEntityByUuid('file', '23a7f61f-1db3-407d-a6dd-eb4731995c9f');
    $this->assertInstanceOf(FileInterface::class, $duplicate_file);
    $this->assertSame('druplicon-duplicate.png', $duplicate_file->getFilename());
    $this->assertSame($file->getFileUri(), $duplicate_file->getFileUri());

    // Another file entity that references a file with the same name as, but
    // different contents than, an existing file, should be imported and the
    // file should be renamed.
    $different_file = $entity_repository->loadEntityByUuid('file', 'a6b79928-838f-44bd-a8f0-44c2fff9e4cc');
    $this->assertInstanceOf(FileInterface::class, $different_file);
    $this->assertSame('druplicon-different.png', $different_file->getFilename());
    $this->assertStringEndsWith('/druplicon_0.png', (string) $different_file->getFileUri());

    // Our node should have a menu link, and it should use the path alias we
    // included with the recipe.
    $menu_link = $entity_repository->loadEntityByUuid('menu_link_content', '3434bd5a-d2cd-4f26-bf79-a7f6b951a21b');
    $this->assertInstanceOf(MenuLinkContentInterface::class, $menu_link);
    $this->assertSame($menu_link->getUrlObject()->toString(), $node_url);
    $this->assertSame('main', $menu_link->getMenuName());

    $block_content = $entity_repository->loadEntityByUuid('block_content', 'd9b72b2f-a5ea-4a3f-b10c-28deb7b3b7bf');
    $this->assertInstanceOf(BlockContentInterface::class, $block_content);
    $this->assertSame('basic', $block_content->bundle());
    $this->assertSame('Useful Info', $block_content->label());
    $this->assertSame("<p>I'd love to put some useful info here.</p>", $block_content->body->value);

    // A node with a non-existent owner should be reassigned to user 1.
    $node = $entity_repository->loadEntityByUuid('node', '7f1dd75a-0be2-4d3b-be5d-9d1a868b9267');
    $this->assertInstanceOf(NodeInterface::class, $node);
    $this->assertSame('1', $node->getOwner()->id());

    // Ensure a node with a translation is imported properly.
    $node = $entity_repository->loadEntityByUuid('node', '2d3581c3-92c7-4600-8991-a0d4b3741198');
    $this->assertInstanceOf(NodeInterface::class, $node);
    $translation = $node->getTranslation('fr');
    $this->assertSame('Perdu en traduction', $translation->label());
    $this->assertSame("<p>Içi c'est la version français.</p>", $translation->body->value);
  }

}
