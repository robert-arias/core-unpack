<?php

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Recipe\RecipeDiscovery;
use Drupal\Core\Recipe\UnknownRecipeException;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Recipe\RecipeDiscovery
 * @group Recipe
 */
class RecipeDiscoveryTest extends KernelTestBase {

  public function providerRecipeDiscovery(): array {
    return [
      ['install_two_modules', 'Install two modules'],
      ['recipe_include', 'Recipe include'],
    ];
  }

  /**
   * Tests that recipe discovery can find recipes.
   *
   * @dataProvider providerRecipeDiscovery
   */
  public function testRecipeDiscovery(string $recipe, string $name): void {
    $discovery = new RecipeDiscovery([]);
    $recipe = $discovery->getRecipe($recipe);
    $this->assertSame($name, $recipe->name);
  }

  public function providerRecipeDiscoveryException(): array {
    return [
      'missing recipe.yml' => ['no_recipe'],
      'no folder' => ['does_not_exist'],
    ];
  }

  /**
   * Tests the exception thrown when recipe discovery cannot find a recipe.
   *
   * @dataProvider providerRecipeDiscoveryException
   */
  public function testRecipeDiscoveryException(string $recipe): void {
    $discovery = new RecipeDiscovery([]);
    try {
      $discovery->getRecipe($recipe);
      $this->fail('Expected exception not thrown');
    }
    catch (UnknownRecipeException $e) {
      $root = $this->getDrupalRoot();
      $this->assertSame($recipe, $e->recipe);
      $this->assertSame([
        $root . '/recipes',
        $root . '/core/recipes',
        $root . '/core/tests/fixtures/recipes',
      ], $e->searchPaths);
      $this->assertSame('Can not find the ' . $recipe . ' recipe, search paths: ' . implode(', ', $e->searchPaths), $e->getMessage());
    }
  }

}
