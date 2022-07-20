<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;

/**
 * Applies recipe.
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeCommand extends Command {

  /**
   * The class loader.
   *
   * @var object
   */
  protected $classLoader;

  /**
   * Constructs a new ServerCommand command.
   *
   * @param object $class_loader
   *   The class loader.
   */
  public function __construct($class_loader) {
    parent::__construct('recipe');
    $this->classLoader = $class_loader;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setDescription('Applies a recipe to a site.')
      ->addArgument('path', InputArgument::REQUIRED, 'The path to the recipe\'s folder to apply');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $recipe_path = $input->getArgument('path');
    if (!is_string($recipe_path) || !is_dir($recipe_path)) {
      $io->error(sprintf('The supplied path %s is not a directory', $recipe_path));
    }

    // Recipes have to be applied to installed sites.
    $this->boot();

    $recipe = Recipe::createFromDirectory($recipe_path);
    RecipeRunner::processRecipe($recipe);
    $io->success(sprintf('%s applied successfully', $recipe->name));
    return 0;
  }

  /**
   * Boots up a Drupal environment.
   *
   * @return \Drupal\Core\DrupalKernelInterface
   *   The Drupal kernel.
   *
   * @throws \Exception
   *   Exception thrown if kernel does not boot.
   */
  protected function boot() {
    $kernel = new DrupalKernel('prod', $this->classLoader, FALSE);
    $kernel::bootEnvironment();
    $kernel->setSitePath($this->getSitePath());
    Settings::initialize($kernel->getAppRoot(), $kernel->getSitePath(), $this->classLoader);
    $kernel->boot();
    $kernel->preHandle(Request::createFromGlobals());

    return $kernel;
  }

  /**
   * Gets the site path.
   *
   * Defaults to 'sites/default'. For testing purposes this can be overridden
   * using the DRUPAL_DEV_SITE_PATH environment variable.
   *
   * @return string
   *   The site path to use.
   */
  protected function getSitePath() {
    return getenv('DRUPAL_DEV_SITE_PATH') ?: 'sites/default';
  }

}
