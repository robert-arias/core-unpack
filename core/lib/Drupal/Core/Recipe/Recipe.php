<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\Serialization\Yaml;

class Recipe {

  const COMPOSER_PROJECT_TYPE = 'drupal-recipe';

  public function __construct(
    public readonly string $name,
    public readonly string $description,
    public readonly string $type,
    public readonly InstallConfigurator $install,
    public readonly ConfigConfigurator $config,
    public readonly ContentConfigurator $content
  ) {
  }

  public static function createFromDirectory(string $path): static {
    if (!is_readable($path . '/recipe.yml')) {
      throw new RecipeFileException("There is no $path/recipe.yml file");
    }
    if (!is_readable($path . '/composer.json')) {
      throw new RecipeFileException("There is no $path/composer.json file");
    }

    $recipe_data = Yaml::decode(file_get_contents($path . '/recipe.yml')) + [
      'type' => '',
      'install' => [],
      'config' => [],
      'content' => [],
    ];

    $composer_data = json_decode(file_get_contents($path . '/composer.json'), TRUE) + [
      'type' => '',
      'name' => '',
      'description' => '',
    ];

    if (!isset($composer_data['type']) || $composer_data['type'] !== static::COMPOSER_PROJECT_TYPE) {
      throw new RecipeFileException("The composer project type must be: " . static::COMPOSER_PROJECT_TYPE);
    }
    $install = new InstallConfigurator($recipe_data['install'], \Drupal::service('extension.list.module'), \Drupal::service('extension.list.theme'));
    $config = new ConfigConfigurator($recipe_data['config'], $path);
    $content = new ContentConfigurator($recipe_data['content']);
    return new static($composer_data['name'], $composer_data['description'], $recipe_data['type'], $install, $config, $content);
  }

}
