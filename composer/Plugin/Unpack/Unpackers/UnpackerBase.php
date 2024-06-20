<?php

namespace Drupal\Composer\Plugin\Unpack\Unpackers;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Link;
use Composer\Package\Locker;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
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
   * @param \Composer\Composer $composer
   *   The composer service.
   * @param \Composer\IO\IOInterface $io
   *   The IO service.
   * @param \Drupal\Composer\Plugin\Unpack\UnpackCollection $unpackCollection
   *   The unpack collection.
   */
  public function __construct(
    protected PackageInterface $package,
    protected Composer $composer,
    protected IOInterface $io,
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

  public function updateRootDependencies(): void {
    try {
      $this->updateComposerJson();
      $this->updateComposerLock();
    }
    catch (\Exception $e) {
      $this->io->writeError($e->getMessage());
    }
  }

  public function updateComposerJson(): void {
    $root_composer_path = $this->getComposerJsonPath();
    $composer_content = file_get_contents($root_composer_path);
    $composer_json = json_decode($composer_content, TRUE);
    $composer_manipulator = new JsonManipulator($composer_content);

    try {
      while ($package_dependency = $this->unpackCollection->popPackageDependencies()) {
        $dependency_name = $package_dependency['name'];
        $type = $package_dependency['dev'] ? 'require-dev' :  'require';

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
          throw new \RuntimeException(sprintf('Could not unpack dependency %s to %s', $dependency_name, $root_composer_path));
        }
      }

      if ($this->removeSelf()) {
        $composer_manipulator->removeSubNode('require', $this->package->getName());
      }

      file_put_contents($root_composer_path, $composer_manipulator->getContents());
    }
    catch (\Exception $e) {
      throw $e;
    }
  }

  public function updateComposerLock(): void {
    $composer_locker_content = $this->composer->getLocker()->getLockData();

    if ($this->removeSelf()) {
      foreach ($composer_locker_content['packages'] as $index => $package) {
        if ($package['name'] === $this->package->getName()) {
          unset($composer_locker_content['packages'][$index]);
          break;
        }
      }

      foreach ($composer_locker_content['packages-dev'] as $index => $package) {
        if ($package['name'] === $this->package->getName()) {
          unset($composer_locker_content['packages-dev'][$index]);
          break;
        }
      }
    }

    $composer_content = file_get_contents($this->getComposerJsonPath());
    $composer_locker_content['packages'] = array_values($composer_locker_content['packages']);
    $composer_locker_content['packages-dev'] = array_values($composer_locker_content['packages-dev']);
    $composer_locker_content['content-hash'] = Locker::getContentHash($composer_content);
    $lock_file = new JsonFile($this->getComposerLockPath(), io: $this->io);
    $lock_file->write($composer_locker_content);

    // Forcefully remove files under vendor.
    if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0', '>')) {
      $locker = new Locker($this->io, $lock_file, $this->composer->getInstallationManager(), $composer_content);
    } else {
      $locker = new Locker($this->io, $lock_file, $this->composer->getRepositoryManager(), $this->composer->getInstallationManager(), $composer_content);
    }

    $this->composer->setLocker($locker);
  }

  protected function getComposerJsonPath(): string {
    return Factory::getComposerFile();
  }

  protected function getComposerLockPath(): string {
    $composer_path = $this->getComposerJsonPath();
    return substr($composer_path, 0, -4) . 'lock';
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
  protected function addPackageDependencies(array $package_dependencies, bool $dev = FALSE): void {
    foreach ($package_dependencies as $package_dependency) {
      if ($package_dependency->getTarget() === $this->package->getName()) {
        // This dependency is the same as the current package, so let's skip it.
        continue;
      }

      if (($dependency_package = $this->getDependencyPackage($package_dependency)) &&
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

  protected function getDependencyPackage(Link $dependency): ?PackageInterface {
    return $this->composer->getRepositoryManager()
      ->getLocalRepository()
      ->findPackage($dependency->getTarget(), $dependency->getConstraint());
  }

  /**
   * {@inheritdoc}
   */
  public function unpackPatches(): void {
    // TODO.
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