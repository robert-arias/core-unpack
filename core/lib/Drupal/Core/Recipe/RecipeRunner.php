<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;

/**
 * Applies a recipe.
 *
 * This class currently static and use \Drupal::service() in order to put off
 * having to solve issues caused by container rebuilds during module install and
 * configuration import.
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeRunner {

  /**
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe to apply.
   */
  public static function processRecipe(Recipe $recipe): void {
    static::processInstall($recipe->install, $recipe->config->getConfigStorage());
    static::processConfiguration($recipe->config);
    static::processContent($recipe->content);
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
    // @todo sort out this monstrosity.
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

    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292286
  }

  protected static function processContent(ContentConfigurator $content): void {
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292287
  }

}
