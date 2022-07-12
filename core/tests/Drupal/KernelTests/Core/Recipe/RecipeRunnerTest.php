<?php

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\views\Entity\View;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \Drupal\Core\Recipe\RecipeRunner
 * @group Recipe
 */
class RecipeRunnerTest extends RecipeTestBase {

  public function testModuleInstall() {
    // Test the state prior to applying the recipe.
    $this->assertFalse($this->container->get('module_handler')->moduleExists('filter'), 'The filter module is not installed');
    $this->assertFalse($this->container->get('module_handler')->moduleExists('text'), 'The text module is not installed');
    $this->assertFalse($this->container->get('module_handler')->moduleExists('node'), 'The node module is not installed');
    $this->assertFalse($this->container->get('config.storage')->exists('node.settings'), 'The node.settings configuration does not exist');

    $recipe = Recipe::createFromDirectory(vfsStream::url('root/recipes/install_two_modules'));
    RecipeRunner::processRecipe($recipe);

    // Test the state after applying the recipe.
    $this->assertTrue($this->container->get('module_handler')->moduleExists('filter'), 'The filter module is installed');
    $this->assertTrue($this->container->get('module_handler')->moduleExists('text'), 'The text module is installed');
    $this->assertTrue($this->container->get('module_handler')->moduleExists('node'), 'The node module is installed');
    $this->assertTrue($this->container->get('config.storage')->exists('node.settings'), 'The node.settings configuration has been created');
  }

  public function testModuleAndThemeInstall() {
    $recipe = Recipe::createFromDirectory(vfsStream::url('root/recipes/base_theme_and_views'));
    RecipeRunner::processRecipe($recipe);

    // Test the state after applying the recipe.
    $this->assertTrue($this->container->get('module_handler')->moduleExists('views'), 'The views module is installed');
    $this->assertTrue($this->container->get('module_handler')->moduleExists('node'), 'The node module is installed');
    $this->assertTrue($this->container->get('theme_handler')->themeExists('test_basetheme'), 'The test_basetheme theme is installed');
    $this->assertTrue($this->container->get('theme_handler')->themeExists('test_subtheme'), 'The test_subtheme theme is installed');
    $this->assertTrue($this->container->get('theme_handler')->themeExists('test_subsubtheme'), 'The test_subsubtheme theme is installed');
    $this->assertTrue($this->container->get('config.storage')->exists('node.settings'), 'The node.settings configuration has been created');
    $this->assertFalse($this->container->get('config.storage')->exists('views.view.archive'), 'The views.view.archive configuration has not been created');
    $this->assertEmpty(View::loadMultiple(), "No views exist");
  }

  public function testThemeModuleDependenciesInstall() {
    $recipe = Recipe::createFromDirectory(vfsStream::url('root/recipes/theme_with_module_dependencies'));
    RecipeRunner::processRecipe($recipe);

    // Test the state after applying the recipe.
    $this->assertTrue($this->container->get('module_handler')->moduleExists('test_module_required_by_theme'), 'The test_module_required_by_theme module is installed');
    $this->assertTrue($this->container->get('module_handler')->moduleExists('test_another_module_required_by_theme'), 'The test_another_module_required_by_theme module is installed');
    $this->assertTrue($this->container->get('theme_handler')->themeExists('test_theme_depending_on_modules'), 'The test_theme_depending_on_modules theme is installed');
  }

}
