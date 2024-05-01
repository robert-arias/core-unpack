<?php

declare(strict_types=1);

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
    $discovery = new RecipeDiscovery('core/tests/fixtures/recipes');
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
    $discovery = new RecipeDiscovery('core/tests/fixtures/recipes');
    try {
      $discovery->getRecipe($recipe);
      $this->fail('Expected exception not thrown');
    }
    catch (UnknownRecipeException $e) {
      $this->assertSame($recipe, $e->recipe);
      $this->assertSame('core/tests/fixtures/recipes', $e->searchPath);
      $this->assertSame('Can not find the ' . $recipe . ' recipe, search path: ' . $e->searchPath, $e->getMessage());
    }
  }

}
