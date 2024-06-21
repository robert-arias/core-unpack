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

  /**
   * The unpack collection.
   *
   * @var \Drupal\Composer\Plugin\Unpack\UnpackCollection
   */
  protected UnpackCollection $unpackCollection;

  /**
   * The unpacker factory.
   *
   * @var \Drupal\Composer\Plugin\Unpack\UnpackerFactory
   */
  protected UnpackerFactory $unpackerFactory;

  /**
   * The root composer with the root dependencies to be manipulated.
   *
   * @var \Drupal\Composer\Plugin\Unpack\RootComposer
   */
  protected RootComposer $rootComposer;

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
    protected IOInterface $io,
  ) {
    $this->unpackCollection = new UnpackCollection();
    $this->rootComposer = new RootComposer($this->composer, $this->io);
    $this->unpackerFactory = new UnpackerFactory(
      $this->composer,
      $this->io,
      $this->unpackCollection,
      $this->rootComposer,
    );
  }

  /**
   * Register a package for unpacking.
   *
   * If the package is unpackable, it will be added into the package queue in
   * the UnpackCollection.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   */
  public function registerPackage(PackageEvent $event): void {
    $package = self::getPackage($event);
    if ($this->unpackerFactory->isUnpackable($package)) {
      $this->unpackCollection->enqueuePackage($package);
    }
  }

  /**
   * Unpack the packages in the queue.
   */
  public function unpack(): void {
    try {
      while ($package = $this->unpackCollection->popPackageQueue()) {
        $unpacker = $this->unpackerFactory->create($package);
        if ($unpacker) {
          $unpacker->unpackDependencies();
        }
      }

      $this->rootComposer->updateComposer();
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
