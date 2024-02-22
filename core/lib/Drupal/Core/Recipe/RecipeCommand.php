<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\Config\Checkpoint\Checkpoint;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\StorageComparer;
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

    if (PHP_VERSION_ID < 80100) {
      $io->error('Recipes require PHP 8.1');
      return 1;
    }

    $recipe_path = $input->getArgument('path');
    if (!is_string($recipe_path) || !is_dir($recipe_path)) {
      $io->error(sprintf('The supplied path %s is not a directory', $recipe_path));
    }
    // Recipes can only be applied to an already-installed site.
    $container = $this->boot()->getContainer();

    $recipe = Recipe::createFromDirectory($recipe_path);
    $backup_checkpoint = $container->get('config.storage.checkpoint')
      ->checkpoint("Backup before the '$recipe->name' recipe.");
    try {
      RecipeRunner::processRecipe($recipe);
      $io->success(sprintf('%s applied successfully', $recipe->name));
      return 0;
    }
    catch (InvalidConfigException $e) {
      $this->rollBackToCheckpoint($backup_checkpoint);
      throw $e;
    }
  }

  /**
   * Rolls config back to a particular checkpoint.
   *
   * @param \Drupal\Core\Config\Checkpoint\Checkpoint $checkpoint
   *   The checkpoint to roll back to.
   */
  private function rollBackToCheckpoint(Checkpoint $checkpoint): void {
    $container = \Drupal::getContainer();

    /** @var \Drupal\Core\Config\Checkpoint\CheckpointStorageInterface $checkpoint_storage */
    $checkpoint_storage = $container->get('config.storage.checkpoint');
    $checkpoint_storage->setCheckpointToReadFrom($checkpoint);

    $storage_comparer = new StorageComparer($checkpoint_storage, $container->get('config.storage'));
    $storage_comparer->reset();

    $config_importer = new ConfigImporter(
      $storage_comparer,
      $container->get('event_dispatcher'),
      $container->get('config.manager'),
      $container->get('lock'),
      $container->get('config.typed'),
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('theme_handler'),
      $container->get('string_translation'),
      $container->get('extension.list.module'),
      $container->get('extension.list.theme'),
    );
    $config_importer->import();
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
