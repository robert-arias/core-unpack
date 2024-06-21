<?php

namespace Drupal\Composer\Plugin\Unpack;

use Composer\Composer;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin for handling dependency unpacking.
 *
 * @internal
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected Composer $composer;

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected IOInterface $io;

  /**
   * The handler for dependency unpacking.
   *
   * @var \Drupal\Composer\Plugin\Unpack\UnpackManager|null
   */
  protected ?UnpackManager $manager = NULL;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io): void {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io): void {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io): void {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PackageEvents::POST_PACKAGE_INSTALL => 'postPackage',
      ScriptEvents::POST_AUTOLOAD_DUMP => 'postCmd',
    ];
  }

  /**
   * Post package event behavior.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   */
  public function postPackage(PackageEvent $event): void {
    $this->manager()->registerPackage($event);
  }

  /**
   * Post autoload event callback.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function postCmd(Event $event): void {
    $this->manager()->unpack();
  }

  /**
   * Get the unpack manager.
   *
   * @return \Drupal\Composer\Plugin\Unpack\UnpackManager
   *   The unpack manager.
   */
  public function manager(): UnpackManager {
    if (!$this->manager) {
      $this->manager = new UnpackManager($this->composer, $this->io);
    }

    return $this->manager;
  }

}
