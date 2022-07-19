<?php

namespace Drupal\Core\Recipe;

use Drupal\Component\Assertion\Inspector;

/**
 * @internal
 *   This API is experimental.
 */
final class RecipeDiscovery {

  /**
   * Constructs a recipe discovery object.
   *
   * @param array $paths
   *   An array of paths where to search for recipes. The path will be searched
   *   folders containing a recipe.yml. There will be no traversal further into
   *   the directory structure.
   */
  public function __construct(protected readonly array $paths) {
    assert(Inspector::assertAllStrings($paths), 'Paths must be strings.');
  }

  /**
   * Constructs a RecipeDiscovery object.
   *
   * @param string $name
   *   The machine name of the recipe to find.
   *
   * @return \Drupal\Core\Recipe\Recipe
   *   The recipe object.
   *
   * @throws \Drupal\Core\Recipe\UnknownRecipeException
   *   Thrown when the recipe cannot be found.
   */
  public function getRecipe(string $name): Recipe {
    foreach ($this->paths as $path) {
      if (file_exists($path . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'recipe.yml')) {
        return Recipe::createFromDirectory($path . DIRECTORY_SEPARATOR . $name);
      }
    }
    throw new UnknownRecipeException($name, $this->paths, sprintf("Can not find the %s recipe, search paths: %s", $name, implode(', ', $this->paths)));
  }

}
