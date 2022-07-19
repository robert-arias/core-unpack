<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\Serialization\Yaml;

/**
 * @internal
 *   This API is experimental.
 */
final class Recipe {

  const COMPOSER_PROJECT_TYPE = 'drupal-recipe';

  public function __construct(
    public readonly string $name,
    public readonly string $description,
    public readonly string $type,
    public readonly RecipeConfigurator $recipes,
    public readonly InstallConfigurator $install,
    public readonly ConfigConfigurator $config,
    public readonly ContentConfigurator $content
  ) {
  }

  /**
   * Creates a recipe object from the provided path.
   *
   * @param string $path
   *   The path to a recipe.
   *
   * @return static
   *   The Recipe object.
   */
  public static function createFromDirectory(string $path): static {
    if (!is_readable($path . '/recipe.yml')) {
      throw new RecipeFileException("There is no $path/recipe.yml file");
    }

    $recipe_data = Yaml::decode(file_get_contents($path . '/recipe.yml')) + [
      'description' => '',
      'type' => '',
      'recipes' => [],
      'install' => [],
      'config' => [],
      'content' => [],
    ];

    if (!isset($recipe_data['name'])) {
      throw new RecipeFileException("The $path/recipe.yml has no name value.");
    }

    $recipe_discovery = new RecipeDiscovery([dirname($path)]);
    $recipes = new RecipeConfigurator($recipe_data['recipes'], $recipe_discovery);
    $install = new InstallConfigurator($recipe_data['install'], \Drupal::service('extension.list.module'), \Drupal::service('extension.list.theme'));
    $config = new ConfigConfigurator($recipe_data['config'], $path, \Drupal::service('config.storage'));
    $content = new ContentConfigurator($recipe_data['content']);
    return new static($recipe_data['name'], $recipe_data['description'], $recipe_data['type'], $recipes, $install, $config, $content);
  }

}
