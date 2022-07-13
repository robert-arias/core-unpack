<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\Config\ConfigInstaller;
use Drupal\Core\Config\Entity\ConfigDependencyManager;
use Drupal\Core\Config\StorageInterface;

/**
 * Extends the ConfigInstaller service for recipes.
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeConfigInstaller extends ConfigInstaller {

  /**
   * {@inheritdoc}
   */
  public function installRecipeConfig(ConfigConfigurator $recipe_config) {
    $storage = $recipe_config->getConfigStorage();

    // Build the list of possible configuration to create.
    $list = $storage->listAll();

    $enabled_extensions = $this->getEnabledExtensions();
    $existing_config = $this->getActiveStorages()->listAll();

    // Filter the list of configuration to only include configuration that
    // should be created.
    $list = array_filter($list, function ($config_name) use ($existing_config) {
      // Only list configuration that:
      // - does not already exist
      return !in_array($config_name, $existing_config);
    });

    // If there is nothing to do.
    if (empty($list)) {
      return;
    }

    $all_config = array_merge($existing_config, $list);
    $all_config = array_combine($all_config, $all_config);
    $config_to_create = $storage->readMultiple($list);

    // Sort $config_to_create in the order of the least dependent first.
    $dependency_manager = new ConfigDependencyManager();
    $dependency_manager->setData($config_to_create);
    $config_to_create = array_merge(array_flip($dependency_manager->sortAll()), $config_to_create);

    foreach ($config_to_create as $config_name => $data) {
      if (!$this->validateDependencies($config_name, $data, $enabled_extensions, $all_config)) {
        throw new RecipeUnmetDependenciesException($config_name, sprintf("The configuration '%s' has unmet dependencies", $config_name));
      }
    }

    // Create the optional configuration if there is any left after filtering.
    if (!empty($config_to_create)) {
      $this->createConfiguration(StorageInterface::DEFAULT_COLLECTION, $config_to_create);
    }
  }

}
