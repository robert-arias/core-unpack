<?php

namespace Drupal\Composer\Plugin\Unpack\Unpackers;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Drupal\Composer\Plugin\Unpack\UnpackCollection;

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
   */
  public function __construct(
    protected Composer $composer,
    protected IOInterface $io,
    protected UnpackCollection $unpackCollection,
  ) {}

  public function create(PackageInterface $package): ?UnpackerInterface {
    $unpacker = match ($package->getType()) {
      RecipeUnpacker::ID => new RecipeUnpacker($package, $this->composer, $this->io, $this->unpackCollection),
      default => NULL,
    };

    return $unpacker;
  }

}
