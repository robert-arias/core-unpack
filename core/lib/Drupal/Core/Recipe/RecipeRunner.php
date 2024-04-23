<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DefaultContent\Existing;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\DefaultContent\Finder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies a recipe.
 *
 * This class is currently static and use \Drupal::service() in order to put off
 * having to solve issues caused by container rebuilds during module install and
 * configuration import.
 *
 * @internal
 *   This API is experimental.
 *
 * @todo https://www.drupal.org/i/3439717 Determine if there is a better to
 *   inject and re-inject services.
 */
final class RecipeRunner {

  /**
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe to apply.
   */
  public static function processRecipe(Recipe $recipe): void {
    static::processRecipes($recipe->recipes);
    static::processInstall($recipe->install, $recipe->config->getConfigStorage());
    static::processConfiguration($recipe->config);
    static::processContent($recipe->content);

    $event = new RecipeAppliedEvent($recipe);
    \Drupal::service(EventDispatcherInterface::class)->dispatch($event);
  }

  /**
   * Applies any recipes listed by the recipe.
   *
   * @param \Drupal\Core\Recipe\RecipeConfigurator $recipes
   *   The list of recipes to apply.
   */
  protected static function processRecipes(RecipeConfigurator $recipes): void {
    foreach ($recipes->recipes as $recipe) {
      static::processRecipe($recipe);
    }
  }

  /**
   * Installs the extensions.
   *
   * @param \Drupal\Core\Recipe\InstallConfigurator $install
   *   The list of extensions to install.
   * @param \Drupal\Core\Config\StorageInterface $recipeConfigStorage
   *   The recipe's configuration storage. Used to override extension provided
   *   configuration.
   */
  protected static function processInstall(InstallConfigurator $install, StorageInterface $recipeConfigStorage): void {
    foreach ($install->modules as $name) {
      // Disable configuration entity install but use the config directory from
      // the module.
      \Drupal::service('config.installer')->setSyncing(TRUE);
      $default_install_path = \Drupal::service('extension.list.module')->get($name)->getPath() . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
      // Allow the recipe to override simple configuration from the module.
      $storage = new RecipeOverrideConfigStorage(
        $recipeConfigStorage,
        new FileStorage($default_install_path, StorageInterface::DEFAULT_COLLECTION)
      );
      \Drupal::service('config.installer')->setSourceStorage($storage);

      \Drupal::service('module_installer')->install([$name]);
      \Drupal::service('config.installer')->setSyncing(FALSE);
    }

    // Themes can depend on modules so have to be installed after modules.
    foreach ($install->themes as $name) {
      // Disable configuration entity install.
      \Drupal::service('config.installer')->setSyncing(TRUE);
      $default_install_path = \Drupal::service('extension.list.theme')->get($name)->getPath() . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
      // Allow the recipe to override simple configuration from the theme.
      $storage = new RecipeOverrideConfigStorage(
        $recipeConfigStorage,
        new FileStorage($default_install_path, StorageInterface::DEFAULT_COLLECTION)
      );
      \Drupal::service('config.installer')->setSourceStorage($storage);

      \Drupal::service('theme_installer')->install([$name]);
      \Drupal::service('config.installer')->setSyncing(FALSE);
    }
  }

  /**
   * Creates configuration and applies configuration actions.
   *
   * @param \Drupal\Core\Recipe\ConfigConfigurator $config
   *   The config configurator from the recipe.
   */
  protected static function processConfiguration(ConfigConfigurator $config): void {
    $config_installer = new RecipeConfigInstaller(
      \Drupal::service('config.factory'),
      \Drupal::service('config.storage'),
      \Drupal::service('config.typed'),
      \Drupal::service('config.manager'),
      \Drupal::service('event_dispatcher'),
      NULL,
      \Drupal::service('extension.path.resolver'));

    // Create configuration that is either supplied by the recipe or listed in
    // the config.import section that does not exist.
    $config_installer->installRecipeConfig($config);

    if (!empty($config->config['actions'])) {
      // Process the actions.
      /** @var \Drupal\Core\Config\Action\ConfigActionManager $config_action_manager */
      $config_action_manager = \Drupal::service('plugin.manager.config_action');
      foreach ($config->config['actions'] as $config_name => $actions) {
        foreach ($actions as $action_id => $data) {
          $config_action_manager->applyAction($action_id, $config_name, $data);
        }
      }
    }
  }

  protected static function processContent(Finder $content): void {
    /** @var \Drupal\Core\DefaultContent\Importer $importer */
    $importer = \Drupal::service(Importer::class);
    $importer->setLogger(\Drupal::logger('recipe'));
    $importer->importContent($content, Existing::Skip);
  }

}
