<?php

namespace Drupal\FunctionalTests\Core\Recipe;

use Drupal\Core\Config\Checkpoint\Checkpoint;
use Drupal\node\Entity\NodeType;
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

  public function testRecipeCommand(): void {
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('node'), 'The node module is not installed');
    $this->assertCheckpointsExist([]);

    $process = $this->runRecipeCommand('core/tests/fixtures/recipes/install_node_with_config');
    $this->assertSame(0, $process->getExitCode());
    $this->assertSame('', $process->getErrorOutput());
    $this->assertStringContainsString('Install node with config applied successfully', $process->getOutput());
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('node'), 'The node module is installed');
    $this->assertCheckpointsExist(["Backup before the 'Install node with config' recipe."]);

    // Ensure recipes can be applied without affecting pre-existing checkpoints.
    $process = $this->runRecipeCommand('core/tests/fixtures/recipes/install_two_modules');
    $this->assertSame(0, $process->getExitCode());
    $this->assertSame('', $process->getErrorOutput());
    $this->assertStringContainsString('Install two modules applied successfully', $process->getOutput());
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('node'), 'The node module is installed');
    $this->assertCheckpointsExist([
      "Backup before the 'Install node with config' recipe.",
      "Backup before the 'Install two modules' recipe.",
    ]);

    // Ensure recipes that fail have an exception message.
    NodeType::load('test')?->delete();
    $process = $this->runRecipeCommand('core/tests/fixtures/recipes/unmet_config_dependencies');
    $this->assertSame(1, $process->getExitCode());
    $this->assertStringContainsString("The configuration 'node.type.test' has unmet dependencies", $process->getErrorOutput());
    $this->assertCheckpointsExist([
      "Backup before the 'Install node with config' recipe.",
      "Backup before the 'Install two modules' recipe.",
      "Backup before the 'Unmet config dependencies' recipe.",
    ]);
  }

  /**
   * Asserts that the current set of checkpoints matches the given labels.
   *
   * @param string[] $expected_labels
   *   The labels of every checkpoint that is expected to exist currently, in
   *   the expected order.
   */
  private function assertCheckpointsExist(array $expected_labels): void {
    $checkpoints = \Drupal::service('config.checkpoints');
    $labels = array_map(fn (Checkpoint $c) => $c->label, iterator_to_array($checkpoints));
    $this->assertSame($expected_labels, array_values($labels));
  }

  /**
   * Runs the `drupal recipe` command.
   *
   * @param string ...$arguments
   *   Additional arguments to pass to the command.
   *
   * @return \Symfony\Component\Process\Process
   *   The command process, after it has run.
   */
  private function runRecipeCommand(string ...$arguments): Process {
    array_unshift($arguments, (new PhpExecutableFinder())->find(), 'core/scripts/drupal', 'recipe');

    $process = (new Process($arguments))
      ->setWorkingDirectory($this->getDrupalRoot())
      ->setEnv([
        'DRUPAL_DEV_SITE_PATH' => $this->siteDirectory,
      ])
      ->setTimeout(500);

    $process->run();
    // Applying a recipe:
    // - creates new checkpoints, hence the "state" service in the test runner
    //   is outdated
    // - may install modules, which would cause the entire container in the test
    //   runner to be outdated.
    // Hence the entire environment must be rebuilt for assertions to target the
    // actual post-recipe-application result.
    // @see \Drupal\Core\Config\Checkpoint\LinearHistory::__construct()
    $this->rebuildAll();
    return $process;
  }

}
