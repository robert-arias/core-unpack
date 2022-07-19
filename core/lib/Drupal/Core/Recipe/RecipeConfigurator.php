<?php

namespace Drupal\Core\Recipe;

/**
 * @internal
 *   This API is experimental.
 */
final class RecipeConfigurator {

  public readonly array $recipes;

  /**
   * @param string[] $recipes
   *   A list of recipes for a recipe to apply. The recipes will be applied in
   *   the order listed.
   * @param \Drupal\Core\Recipe\RecipeDiscovery $recipeDiscovery
   *   Recipe discovery.
   */
  public function __construct(array $recipes, RecipeDiscovery $recipeDiscovery) {
    $this->recipes = array_map([$recipeDiscovery, 'getRecipe'], $recipes);
  }

}
