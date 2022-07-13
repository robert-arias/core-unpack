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
   */
  public function __construct(public readonly array $config, string $recipe_directory) {
    $this->recipeConfigDirectory = is_dir($recipe_directory . '/config') ? $recipe_directory . '/config' : NULL;
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292282
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292284
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292286
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
