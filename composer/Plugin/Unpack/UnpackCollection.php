<?php

namespace Drupal\Composer\Plugin\Unpack;

use Composer\Package\PackageInterface;

class UnpackCollection implements \IteratorAggregate {

  /**
   * The queue of packages to unpack.
   *
   * @var \Composer\Package\PackageInterface[]
   */
  protected array $packagesToUnpack;

  /**
   * The list of packages that have been unpacked.
   *
   * @var array
   */
  protected array $unpackedPackages;

  /**
   * The dependencies of the packages that have been unpacked.
   *
   * @var array
   */
  protected array $allPackageDependencies;

  /**
   * UnpackCollection constructor.
   */
  public function __construct() {
    $this->packagesToUnpack = [];
    $this->unpackedPackages = [];
    $this->allPackageDependencies = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \ArrayIterator {
    return new \ArrayIterator($this);
  }

  /**
   * Add a package to the queue of packages to unpack.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to add to the queue.
   */
  public function enqueuePackage(PackageInterface $package): void {
    if (!isset($this->packagesToUnpack[$package->getPrettyName()])) {
      $this->packagesToUnpack[$package->getPrettyName()] = $package;
    }
  }

  /**
   * Get the queue of packages to unpack.
   *
   * @return \Composer\Package\PackageInterface[]
   *   The queue of packages to unpack.
   */
  public function getPackagesQueue(): array {
    return $this->packagesToUnpack;
  }

  /**
   * Add a package to the list of unpacked packages.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that has been unpacked.
   * @param string $unpacker_id
   *   The id of the unpacker that unpacked the package.
   */
  public function addUnpackedPackage(PackageInterface $package, string $unpacker_id): void {
    $this->unpackedPackages[$unpacker_id][$package->getPrettyName()] = $package;
  }

  /**
   * Check if a package has been unpacked or it's queued for unpacking.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to check.
   * @param string $unpacker_id
   *   The id of the unpacker that unpacked the package.
   *
   * @return bool
   *   TRUE if the package has been unpacked.
   */
  public function isUnpacked(PackageInterface $package, string $unpacker_id): bool {
    return isset($this->unpackedPackages[$unpacker_id][$package->getPrettyName()]) || isset($this->packagesToUnpack[$package->getPrettyName()]);
  }

  public function addPackageDependencies(string $name, string $version, bool $dev = FALSE): void {
    if ($this->dependencyExists($name)) {
      if (version_compare($this->allPackageDependencies[$name], $version, '<')) {
        $this->allPackageDependencies[$name] = $version;
      }
    }
    else {
      $this->allPackageDependencies[$name] = [
        'name' => $name,
        'version' => $version,
        'dev' => $dev,
      ];
    }
  }

  public function popPackageDependencies(): ?array {
    return array_shift($this->allPackageDependencies);
  }

  public function dependencyExists(string $name): bool {
    return isset($this->allPackageDependencies[$name]);
  }

  public function countPackageQueue(): int {
    return count($this->packagesToUnpack);
  }

  public function hasUnpackQueue(): bool {
    return $this->countPackageQueue() > 0;
  }

  public function popPackageQueue(): ?PackageInterface {
    return array_shift($this->packagesToUnpack);
  }

}
