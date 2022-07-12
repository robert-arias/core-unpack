<?php

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeFileException;
use Drupal\Core\Recipe\RecipeMissingExtensionsException;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \Drupal\Core\Recipe\Recipe
 * @group Recipe
 * @runInSeparateProcess
 */
class RecipeTest extends RecipeTestBase {

  public function providerTestCreateFromDirectory() {
    return [
      'no extensions' => ['no_extensions', 'drupal_recipe/no_extensions' , 'Testing', []],
      // Filter is installed because it is a dependency and it is not yet installed.
      'install_two_modules' => ['install_two_modules', 'drupal_recipe/install_two_modules' , 'Content type', ['filter', 'text', 'node']],
    ];
  }

  /**
   * @dataProvider providerTestCreateFromDirectory
   */
  public function testCreateFromDirectory2(string $recipe_name, string $expected_name, string $expected_type, array $expected_modules): void {
    $recipe = Recipe::createFromDirectory(vfsStream::url('root/recipes/' . $recipe_name));
    $this->assertSame($expected_name, $recipe->name);
    $this->assertSame($expected_type, $recipe->type);
    $this->assertSame($expected_modules, $recipe->install->modules);
  }

  public function testCreateFromDirectoryNoRecipe(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('There is no vfs://root/recipes/no_recipe/recipe.yml file');
    Recipe::createFromDirectory(vfsStream::url('root/recipes/no_recipe'));
  }

  public function testCreateFromDirectoryNoComposer(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('There is no vfs://root/recipes/no_composer/composer.json file');
    Recipe::createFromDirectory(vfsStream::url('root/recipes/no_composer'));
  }

  public function testCreateFromDirectoryWrongComposerType(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('The composer project type must be: drupal-recipe');
    Recipe::createFromDirectory(vfsStream::url('root/recipes/wrong_type'));
  }

  public function testCreateFromDirectoryMissingExtensions(): void {
    $this->enableModules(['module_test']);

    // Create a missing fake dependency.
    // dblog will depend on Config, which depends on a non-existing module Foo.
    // Nothing should be installed.
    \Drupal::state()->set('module_test.dependency', 'missing dependency');

    try {
      Recipe::createFromDirectory(vfsStream::url('root/recipes/missing_extensions'));
      $this->fail('Expected exception not thrown');
    }
    catch (RecipeMissingExtensionsException $e) {
      $this->assertSame(['does_not_exist_one', 'does_not_exist_two', 'foo'], $e->extensions);
    }
  }

}
