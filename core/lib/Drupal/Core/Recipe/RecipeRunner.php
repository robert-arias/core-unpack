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
    static::processInstall($recipe->install);
    static::processConfiguration($recipe->config);
    static::processContent($recipe->content);
  }

  /**
   * Installs the extensions.
   *
   * @param \Drupal\Core\Recipe\InstallConfigurator $install
   *   The list of extensions to install.
   */
  protected static function processInstall(InstallConfigurator $install): void {
    foreach ($install->modules as $name) {
      // Disable configuration entity install but use the config directory from
      // the module.
      \Drupal::service('config.installer')->setSyncing(TRUE);
      $default_install_path = \Drupal::service('extension.list.module')->get($name)->getPath() . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
      $storage = new FileStorage($default_install_path, StorageInterface::DEFAULT_COLLECTION);
      \Drupal::service('config.installer')->setSourceStorage($storage);

      \Drupal::service('module_installer')->install([$name]);
      \Drupal::service('config.installer')->setSyncing(FALSE);
    }

    // Themes can depend on modules so have to be installed after modules.
    foreach ($install->themes as $name) {
      // Disable configuration entity install.
      \Drupal::service('config.installer')->setSyncing(TRUE);
      $default_install_path = \Drupal::service('extension.list.theme')->get($name)->getPath() . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
      $storage = new FileStorage($default_install_path, StorageInterface::DEFAULT_COLLECTION);
      \Drupal::service('config.installer')->setSourceStorage($storage);

      \Drupal::service('theme_installer')->install([$name]);
      \Drupal::service('config.installer')->setSyncing(FALSE);
    }
  }

  protected static function processConfiguration(ConfigConfigurator $config): void {
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292282
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292284
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292286
  }

  protected static function processContent(ContentConfigurator $content): void {
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292287
  }

}
