<?php

namespace Drupal\Composer\Plugin\Unpack;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Drupal\Composer\Plugin\Unpack\Unpackers\UnpackerFactory;
use Drupal\Composer\Plugin\Unpack\Unpackers\UnpackerInterface;

/**
 * Core class to handle operations on dependencies.
 */
class UnpackManager {

  protected UnpackCollection $unpackCollection;

  /**
   * UnpackManager constructor.
   *
   * @param \Composer\Composer $composer
   *   The composer service.
   * @param \Composer\IO\IOInterface $io
   *   The IO service.
   */
  public function __construct(
    protected Composer $composer,
    protected IOInterface $io
  ) {
    $this->unpackCollection = new UnpackCollection();
  }

  public function unpack(PackageEvent $event): void {
    try {
      $package = self::getPackage($event);
      $unpacker_factory = new UnpackerFactory($this->composer, $this->io, $this->unpackCollection);
      $this->unpackCollection->enqueuePackage($package);

      // see PostPackageEventListenerInterface in scaffold plugin.
      // basically, the unpacker can add into the queue in case a dependency
      // needs to be unpacked, for example, a recipe package has another recipe
      // dependency.
      while ($this->unpackCollection->hasUnpackQueue()) {
        $package = $this->unpackCollection->popPackageQueue();
        $unpacker = $unpacker_factory->create($package);
        if ($unpacker) {
          $unpacker->unpackDependencies();
        }
      }
    }
    catch (\Exception $e) {
      $this->io->writeError($e->getMessage());
    }
  }

  public static function getUnpackOptions(PackageInterface $package, UnpackerInterface $unpacker): UnpackOptions {
    return UnpackOptions::create($package->getExtra(), $unpacker->getUnpackerId());
  }

  /**
   * Get the package from a package event.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   *
   * @return \Composer\Package\PackageInterface
   *   The package from the event.
   *
   * @throws \RuntimeException
   *   If the operation is not supported by the manager.
   */
  public static function getPackage(PackageEvent $event): PackageInterface {
    $operation = $event->getOperation();
    if (!($operation instanceof UpdateOperation || $operation instanceof InstallOperation)) {
      throw new \RuntimeException('Unsupported operation type ' . get_class($operation));
    }

    $package = $operation instanceof UpdateOperation ?
      $operation->getTargetPackage() : $operation->getPackage();

    return $package;
  }

}
