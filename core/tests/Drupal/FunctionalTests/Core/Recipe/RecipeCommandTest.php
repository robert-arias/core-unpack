<?php

namespace Drupal\FunctionalTests\Core\Recipe;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @coversDefaultClass \Drupal\Core\Recipe\RecipeCommand
 * @group Recipe
 *
 * BrowserTestBase is used for a proper Drupal install.
 */
class RecipeCommandTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testRecipeCommand() {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('node'), 'The node module is not installed');
    $php_executable_finder = new PhpExecutableFinder();
    $php = $php_executable_finder->find();

    $recipe_command = [
      $php,
      'core/scripts/drupal',
      'recipe',
      'core/tests/fixtures/recipes/install_node_with_config',
    ];
    $process = new Process($recipe_command, NULL, ['DRUPAL_DEV_SITE_PATH' => $this->siteDirectory]);
    $process->setTimeout(500);
    $status = $process->run();
    $this->assertSame(0, $status);
    $this->assertSame('', $process->getErrorOutput());
    $this->assertStringContainsString('drupal_recipe/install_node_with_config applied successfully', $process->getOutput());

    $this->rebuildAll();
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('node'), 'The node module is installed');

    // Ensure recipes that fail have an exception message.
    $recipe_command = [
      $php,
      'core/scripts/drupal',
      'recipe',
      'core/tests/fixtures/recipes/missing_extensions',
    ];
    $process = new Process($recipe_command, NULL, ['DRUPAL_DEV_SITE_PATH' => $this->siteDirectory]);
    $process->setTimeout(500);
    $status = $process->run();
    $this->assertSame(1, $status);
    $this->assertStringContainsString('Drupal\Core\Recipe\RecipeMissingExtensionsException', $process->getErrorOutput());
  }

}
