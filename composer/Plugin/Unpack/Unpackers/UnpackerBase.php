<?php

namespace Drupal\Composer\Plugin\Unpack\Unpackers;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\Link;
use Composer\Package\PackageInterface;
use Drupal\Composer\Plugin\Unpack\RootComposer;
use Drupal\Composer\Plugin\Unpack\UnpackCollection;
use Drupal\Composer\Plugin\Unpack\UnpackManager;
use Drupal\Composer\Plugin\Unpack\UnpackOptions;

abstract class UnpackerBase implements UnpackerInterface {

  /**
   * The unpack options for this unpacker.
   *
   * @var \Drupal\Composer\Plugin\Unpack\UnpackOptions
   */
  protected UnpackOptions $unpackOptions;

  /**
   * UnpackerBase constructor.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to unpack.
   * @param \Composer\Composer $composer
   *   The composer service.
   * @param \Composer\IO\IOInterface $io
   *   The IO service.
   * @param \Drupal\Composer\Plugin\Unpack\RootComposer $rootComposer
   *   The root composer with the root dependencies to be manipulated.
   * @param \Drupal\Composer\Plugin\Unpack\UnpackCollection $unpackCollection
   *   The unpack collection.
   */
  public function __construct(
    protected PackageInterface $package,
    protected Composer $composer,
    protected IOInterface $io,
    protected RootComposer $rootComposer,
    protected UnpackCollection $unpackCollection,
  ) {
    $this->unpackOptions = UnpackManager::getUnpackOptions($this->package, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function unpackDependencies(): void {
    $this->addPackageDependencies($this->package->getRequires());

    if ($this->shouldUnpackDevDependencies()) {
      $this->addPackageDependencies($this->package->getDevRequires(), dev: TRUE);
    }

    if ($this->shouldUnpackPatches()) {
      // TODO: Implement patches.
    }

    $this->updateRootDependencies();
  }

  /**
   * Add package dependencies into the unpack collection.
   *
   * If the dependency is the same as the current package, we skip it. If the
   * dependency is of the same type as the current package being unpacked, we
   * add it into the package queue so that it will be unpacked as well (this
   * depends on the UnpackerInterface::unpackRecursively() method). If the
   * dependency is not any of the above, we add it into the dependency array.
   *
   * @param array<string, \Composer\Package\Link> $package_dependencies
   *   The package dependencies.
   * @param bool $dev
   *   The flag to indicate if the dependencies are dev dependencies.
   */
  public function addPackageDependencies(array $package_dependencies, bool $dev = FALSE): void {
    foreach ($package_dependencies as $package_dependency) {
      if ($package_dependency->getTarget() === $this->package->getName()) {
        // This dependency is the same as the current package, so let's skip it.
        continue;
      }

      $dependency_package = $this->getDependencyPackage($package_dependency);

      if ($dependency_package && $this->unpackCollection->isUnpacked($dependency_package, $this->getUnpackerId())) {
        // This dependency is already unpacked or enqueued to be unpacked.
        continue;
      }

      if ($dependency_package &&
          $dependency_package->getType() === $this->package->getType() &&
          $this->unpackRecursively()
      ) {
        // This dependency is of the same type as the current package being
        // unpacked. This  means that this dependency should be unpacked as
        // well, so let's add it into the package queue.
        $this->unpackCollection->enqueuePackage($dependency_package);
      }
      else {
        $this->unpackCollection->addPackageDependencies(
          $package_dependency->getTarget(),
          $package_dependency->getPrettyConstraint(),
          $dev
        );
      }
    }
  }

  /**
   * Update the root composer dependencies.
   */
  public function updateRootDependencies(): void {
    try {
      $this->updateComposerJson();
      $this->updateComposerLock();
      $this->unpackCollection->addUnpackedPackage($this->package, $this->getUnpackerId());
    }
    catch (\Exception $e) {
      $this->io->writeError($e->getMessage());
    }
  }

  /**
   * Update the composer.json file.
   *
   * This method will add all the package dependencies to the composer.json file
   * and also remove the package itself from the root composer.json.
   *
   * @throws \RuntimeException
   *   If the composer.json could not be updated.
   */
  public function updateComposerJson(): void {
    $composer_json = $this->rootComposer->getComposerContent();
    $composer_manipulator = $this->rootComposer->getComposerManipulator();

    try {
      while ($package_dependency = $this->unpackCollection->popPackageDependencies()) {
        $dependency_name = $package_dependency['name'];
        $type = $package_dependency['dev'] ? 'require-dev' : 'require';

        if (isset($composer_json['require'][$dependency_name])) {
          continue;
        }

        if (isset($composer_json['require-dev'][$dependency_name])) {
          if ($package_dependency['dev']) {
            continue;
          }

          $composer_manipulator->removeSubNode('require-dev', $dependency_name);
        }

        if (!$composer_manipulator->addLink(
            $type,
            $dependency_name,
            $package_dependency['version'],
            sortPackages: TRUE
        )) {
          throw new \RuntimeException(sprintf('Could not unpack dependency %s to %s',
            $dependency_name,
            $this->rootComposer->getComposerJsonPath()
          ));
        }
      }

      if ($this->removeSelf()) {
        $composer_manipulator->removeSubNode('require', $this->package->getName());
      }

    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  /**
   * Update the composer.lock file.
   *
   * This method will remove the package itself from the composer.lock file.
   */
  public function updateComposerLock(): void {
    $composer_locker_content = $this->rootComposer->getComposerLockedContent();

    if ($this->removeSelf()) {
      $max = max([
        count($composer_locker_content['packages']),
        count($composer_locker_content['packages-dev']),
      ]) - 1;

      while ($max >= 0) {
        if (isset($composer_locker_content['packages'][$max]) &&
           $composer_locker_content['packages'][$max]['name'] === $this->package->getName()
        ) {
          $this->rootComposer->removeFromComposerLock('packages', $max);
          break;
        }

        if (isset($composer_locker_content['packages-dev'][$max]) &&
           $composer_locker_content['packages-dev'][$max]['name'] === $this->package->getName()
        ) {
          $this->rootComposer->removeFromComposerLock('packages-dev', $max);
          break;
        }

        $max--;
      }
    }
  }

  /**
   * Get the package object from a link dependency.
   *
   * @param \Composer\Package\Link $dependency
   *   The link dependency.
   *
   * @return \Composer\Package\PackageInterface|null
   *   The package object.
   */
  protected function getDependencyPackage(Link $dependency): ?PackageInterface {
    return $this->composer->getRepositoryManager()
      ->getLocalRepository()
      ->findPackage($dependency->getTarget(), $dependency->getConstraint());
  }

  /**
   * {@inheritdoc}
   */
  public function shouldUnpackDevDependencies(): bool {
    return $this->unpackOptions->unpackDevDependencies();
  }

  /**
   * {@inheritdoc}
   */
  public function shouldUnpackPatches(): bool {
    return $this->unpackOptions->unpackPatches();
  }

  /**
   * {@inheritdoc}
   */
  public function removeSelf(): bool {
    return $this->unpackOptions->removeSelf();
  }

  /**
   * {@inheritdoc}
   */
  public function unpackRecursively(): bool {
    return FALSE;
  }

}
