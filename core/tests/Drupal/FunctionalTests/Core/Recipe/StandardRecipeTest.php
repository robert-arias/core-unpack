<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Core\Recipe;

use Drupal\contact\Entity\ContactForm;
use Drupal\Tests\standard\Functional\StandardTest;
use Drupal\user\RoleInterface;

/**
 * Tests Standard recipe installation expectations.
 *
 * @group #slow
 * @group Recipe
 */
class StandardRecipeTest extends StandardTest {

  use RecipeTestTrait;

  /**
   * Tests Standard installation recipe.
   */
  public function testStandard(): void {
    // Install some modules that Standard has optional integrations with.
    \Drupal::service('module_installer')->install(['media_library', 'content_moderation']);
    // The standard config is sorted incorrectly. Save the config to make schema
    // sort apply.
    // @todo remove once https://www.drupal.org/i/3433042 lands
    $this->config('core.entity_view_display.node.article.teaser')->save();

    // Export all the configuration so we can compare later.
    $this->copyConfig(\Drupal::service('config.storage'), \Drupal::service('config.storage.sync'));

    // Set theme to stark and uninstall the other themes.
    $theme_installer = \Drupal::service('theme_installer');
    $theme_installer->install(['stark']);
    $this->config('system.theme')->set('admin', '')->set('default', 'stark')->save();
    $theme_installer->uninstall(['claro', 'olivero']);

    // Determine which modules to uninstall.
    $uninstall = array_diff(array_keys(\Drupal::moduleHandler()->getModuleList()), ['user', 'system', 'path_alias', \Drupal::database()->getProvider()]);
    foreach (['shortcut', 'field_config', 'filter_format', 'field_storage_config'] as $entity_type) {
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      $storage->delete($storage->loadMultiple());
    }

    // Uninstall all the modules including the Standard profile.
    \Drupal::service('module_installer')->uninstall($uninstall);

    // Clean up entity displays before recipe import.
    foreach (['entity_form_display', 'entity_view_display'] as $entity_type) {
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      $storage->delete($storage->loadMultiple());
    }

    // Clean up roles before recipe import.
    $storage = \Drupal::entityTypeManager()->getStorage('user_role');
    $roles = $storage->loadMultiple();
    unset($roles[RoleInterface::ANONYMOUS_ID], $roles[RoleInterface::AUTHENTICATED_ID]);
    $storage->delete($roles);

    $this->applyRecipe('core/recipes/standard');
    // These recipes provide functionality that is only optionally part of the
    // Standard profile, so we need to explicitly apply them.
    $this->applyRecipe('core/recipes/editorial_workflow');
    $this->applyRecipe('core/recipes/audio_media_type');
    $this->applyRecipe('core/recipes/document_media_type');
    $this->applyRecipe('core/recipes/image_media_type');
    $this->applyRecipe('core/recipes/local_video_media_type');
    $this->applyRecipe('core/recipes/remote_video_media_type');

    // Remove the theme we had to install.
    \Drupal::service('theme_installer')->uninstall(['stark']);

    // Add a Home link to the main menu as Standard expects "Main navigation"
    // block on the page.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/structure/menu/manage/main/add');
    $this->submitForm([
      'title[0][value]' => 'Home',
      'link[0][uri]' => '<front>',
    ], 'Save');

    // Standard expects to set the contact form's recipient email to the
    // system's email address, but our feedback_contact_form recipe hard-codes
    // it to another value.
    // @todo This can be removed after https://drupal.org/i/3303126, which
    //   should make it possible for a recipe to reuse an already-set config
    //   value.
    ContactForm::load('feedback')?->setRecipients(['simpletest@example.com'])
      ->save();

    // Update sync directory config to have the same UUIDs so we can compare.
    /** @var \Drupal\Core\Config\StorageInterface $sync */
    $sync = \Drupal::service('config.storage.sync');
    /** @var \Drupal\Core\Config\StorageInterface $active */
    $active = \Drupal::service('config.storage');
    // @todo think about the _core unset some more...
    foreach ($active->listAll() as $name) {
      /** @var mixed[] $active_data */
      $active_data = $active->read($name);
      if ($sync->exists($name)) {
        /** @var mixed[] $sync_data */
        $sync_data = $sync->read($name);
        if (isset($sync_data['uuid'])) {
          $sync_data['uuid'] = $active_data['uuid'];
        }
        if (isset($sync_data['_core'])) {
          unset($sync_data['_core']);
        }
        /** @var array $sync_data */
        $sync->write($name, $sync_data);
      }
      if (isset($active_data['_core'])) {
        unset($active_data['_core']);
        $active->write($name, $active_data);
      }
      // @todo Remove this once https://drupal.org/i/3427564 lands.
      if ($name === 'node.settings') {
        unset($active_data['langcode']);
        $active->write($name, $active_data);
      }
    }

    // Ensure we have truly rebuilt the standard profile using recipes.
    // Uncomment the code below to see the differences in a single file.
    // $this->assertSame($sync->read('node.settings'), $active->read('node.settings'));
    $comparer = $this->configImporter()->getStorageComparer();
    $expected_list = $comparer->getEmptyChangelist();
    // We expect core.extension to be different because standard is no longer
    // installed.
    $expected_list['update'] = ['core.extension'];
    $this->assertSame($expected_list, $comparer->getChangelist());

    parent::testStandard();
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove this override in https://www.drupal.org/node/2941757.
   */
  protected function rebuildContainer(): void {
    // Compare the module list before and after the container is rebuilt, to
    // determine if any modules were installed.
    $modules_before = array_keys($this->container->get('module_handler')->getModuleList());
    parent::rebuildContainer();
    $modules_after = array_keys($this->container->get('module_handler')->getModuleList());

    // If responsive_image was installed, apply the recipe that provides the
    // responsive image styles. We cannot just do this unconditionally, because
    // the parent class explicitly asserts that the image styles don't exist
    // if responsive_image is not installed.
    $installed = array_diff($modules_after, $modules_before);
    if (in_array('responsive_image', $installed, TRUE)) {
      $this->applyRecipe('core/recipes/standard_responsive_images');
    }
  }

}
