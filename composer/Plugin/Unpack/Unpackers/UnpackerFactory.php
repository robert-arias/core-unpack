<?php

namespace Drupal\Composer\Plugin\Unpack\Unpackers;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Drupal\Composer\Plugin\Unpack\RootComposer;
use Drupal\Composer\Plugin\Unpack\UnpackCollection;

/**
 * Unpacker factory for dependency unpackers.
 */
class UnpackerFactory {

  /**
   * UnpackerFactory constructor.
   *
   * @param \Composer\Composer $composer
   *   The composer service.
   * @param \Composer\IO\IOInterface $io
   *   The IO service.
   * @param \Drupal\Composer\Plugin\Unpack\UnpackCollection $unpackCollection
   *   The list of packages that have been unpacked.
   * @param \Drupal\Composer\Plugin\Unpack\RootComposer $rootComposer
   *   The root composer with the root dependencies to be manipulated.
   */
  public function __construct(
    protected Composer $composer,
    protected IOInterface $io,
    protected UnpackCollection $unpackCollection,
    protected RootComposer $rootComposer,
  ) {}

  /**
   * Get an unpacker from a given package.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to unpack.
   *
   * @return \Drupal\Composer\Plugin\Unpack\UnpackerInterface|null
   *   The unpacker or NULL if the package is not unpackable.
   */
  public function create(PackageInterface $package): ?UnpackerInterface {
    $unpacker = match ($package->getType()) {
      RecipeUnpacker::ID => new RecipeUnpacker($package, $this->composer, $this->io, $this->rootComposer, $this->unpackCollection),
      default => NULL,
    };

    return $unpacker;
  }

  /**
   * Check if a package is unpackable.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to unpack.
   *
   * @return bool
   *   TRUE if the package is unpackable, FALSE otherwise.
   */
  public function isUnpackable(PackageInterface $package): bool {
    return $this->create($package) !== NULL;
  }

}
