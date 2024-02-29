<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Core\Recipe;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Contains helper methods for interacting with recipes in functional tests.
 */
trait RecipeTestTrait {

  /**
   * Applies a recipe to the site.
   *
   * @param string $path
   *   The path of the recipe to apply. Must be a directory.
   *
   * @return \Symfony\Component\Process\Process
   *   The `drupal recipe` command process, after having run.
   */
  protected function applyRecipe(string $path): Process {
    assert($this instanceof BrowserTestBase);
    $this->assertDirectoryExists($path);

    $arguments = [
      (new PhpExecutableFinder())->find(),
      'core/scripts/drupal',
      'recipe',
      $path,
    ];
    $process = (new Process($arguments))
      ->setWorkingDirectory($this->getDrupalRoot())
      ->setEnv([
        'DRUPAL_DEV_SITE_PATH' => $this->siteDirectory,
        // Ensure that the command boots Drupal into a state where it knows it's
        // a test site.
        // @see drupal_valid_test_ua()
        'HTTP_USER_AGENT' => drupal_generate_test_ua($this->databasePrefix),
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
