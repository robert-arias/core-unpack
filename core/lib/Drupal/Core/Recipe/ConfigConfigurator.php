<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\Config\StorageInterface;

/**
 * @internal
 *   This API is experimental.
 */
final class ConfigConfigurator {

  public readonly ?string $recipeConfigDirectory;

  /**
   * @param array $config
   *   Config options for a recipe.
   * @param string $recipe_directory
   *   The path to the recipe.
   * @param \Drupal\Core\Config\StorageInterface $active_configuration
   *   The active configuration storage.
   */
  public function __construct(public readonly array $config, string $recipe_directory, StorageInterface $active_configuration) {
    $this->recipeConfigDirectory = is_dir($recipe_directory . '/config') ? $recipe_directory . '/config' : NULL;
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292284
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292286
    $recipe_storage = $this->getConfigStorage();
    foreach ($recipe_storage->listAll() as $config_name) {
      if ($active_data = $active_configuration->read($config_name)) {
        // @todo investigate if there is any generic code in core for this.
        unset($active_data['uuid'], $active_data['_core']);
        if (empty($active_data['dependencies'])) {
          unset($active_data['dependencies']);
        }
        if ($active_data !== $recipe_storage->read($config_name)) {
          throw new RecipePreExistingConfigException($config_name, sprintf("The configuration '%s' exists already and does not match the recipe's configuration", $config_name));
        }
      }
    }
  }

  /**
   * Gets a config storage object for reading config from the recipe.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The  config storage object for reading config from the recipe.
   */
  public function getConfigStorage(): StorageInterface {
    return $this->recipeConfigDirectory ? new FileStorage($this->recipeConfigDirectory) : new NullStorage();
  }

}
