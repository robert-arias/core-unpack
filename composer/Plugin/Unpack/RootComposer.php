<?php

namespace Drupal\Composer\Plugin\Unpack;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Locker;
use Composer\Plugin\PluginInterface;

/**
 * Provides access to the root composer.json contents.
 *
 * This class should be used as a singleton so that multiple unpackers can
 * access the same root composer.json content.
 */
class RootComposer {

  /**
   * The root composer.json content.
   *
   * @var array<string, mixed>
   */
  protected array $composerContent;

  /**
   * The JSON manipulator for the contents of the root composer.json.
   *
   * @var \Composer\Json\JsonManipulator
   */
  protected JsonManipulator $composerManipulator;

  /**
   * The locked root composer.json content.
   *
   * @var array<string, mixed>
   */
  protected array $composerLockedContent;

  /**
   * RootComposer constructor.
   *
   * @param \Composer\Composer $composer
   *   The composer service.
   * @param \Composer\IO\IOInterface $io
   *   The composer IO service.
   */
  public function __construct(
    protected Composer $composer,
    protected IOInterface $io,
  ) {}

  /**
   * Get the root composer.json content.
   *
   * @return array<string, mixed>
   *   The root composer.json content.
   */
  public function getComposerContent(): array {
    if (!isset($this->composerContent)) {
      $composer_content = self::getRawComposerContent();
      $this->composerContent = json_decode($composer_content, TRUE);
    }
    return $this->composerContent;
  }

  /**
   * Retrieves the JSON manipulator for the contents of the root composer.json.
   *
   * @return \Composer\Json\JsonManipulator
   *   The JSON manipulator.
   */
  public function getComposerManipulator(): JsonManipulator {
    if (!isset($this->composerManipulator)) {
      $composer_content = self::getRawComposerContent();
      $this->composerManipulator = new JsonManipulator($composer_content);
    }
    return $this->composerManipulator;
  }

  /**
   * Get the locked root composer.json content.
   *
   * @return array<string, mixed>
   *   The locked root composer.json content.
   */
  public function getComposerLockedContent(): array {
    if (!isset($this->composerLockedContent)) {
      $this->composerLockedContent = $this->composer->getLocker()->getLockData();
    }
    return $this->composerLockedContent;
  }

  public function removeFromRootComposer(string $key, string $index): void {
    unset($this->composerLockedContent[$key][$index]);
  }

  /**
   * Update the root composer.json and composer.lock files.
   *
   * @throws \RuntimeException
   *   If the root composer could not be updated.
   */
  public function updateComposer(): void {
    $this->updateComposerJson();
    $this->updateComposerLock();
  }

  /**
   * Update the root composer.json file.
   *
   * @throws \RuntimeException
   *   If the root composer could not be updated.
   */
  public function updateComposerJson(): void {
    if (!file_put_contents(self::getComposerJsonPath(), $this->getComposerManipulator()->getContents())) {
      throw new \RuntimeException(sprintf('Could not update root composer.json in %s', self::getComposerJsonPath()));
    }
  }

  /**
   * Update the root composer.lock file.
   */
  public function updateComposerLock(): void {
    $composer_content = self::getRawComposerContent();
    $composer_locker_content = $this->getComposerLockedContent();
    $composer_locker_content['packages'] = array_values($composer_locker_content['packages']);
    $composer_locker_content['packages-dev'] = array_values($composer_locker_content['packages-dev']);
    $composer_locker_content['content-hash'] = Locker::getContentHash($composer_content);
    $lock_file = new JsonFile($this->getComposerLockPath(), io: $this->io);
    $lock_file->write($composer_locker_content);

    // Forcefully remove files under vendor.
    if (version_compare(PluginInterface::PLUGIN_API_VERSION, '2.0.0', '>')) {
      $locker = new Locker($this->io, $lock_file, $this->composer->getInstallationManager(), $composer_content);
    }
    else {
      $locker = new Locker($this->io, $lock_file, $this->composer->getRepositoryManager(), $this->composer->getInstallationManager(), $composer_content);
    }

    $this->composer->setLocker($locker);
  }

  /**
   * Get the path to the Composer root directory.
   *
   * @return string
   *   The absolute path to the Composer root directory.
   */
  public static function getComposerJsonPath(): string {
    return Factory::getComposerFile();
  }

  /**
   * Get the path to the Composer lock file.
   *
   * @return string
   *   The absolute path to the Composer lock file.
   */
  public static function getComposerLockPath(): string {
    $composer_path = self::getComposerJsonPath();
    return substr($composer_path, 0, -4) . 'lock';
  }

  /**
   * Get the raw contents of the root composer.json file.
   *
   * @return string
   *   The raw contents of the root composer.json file.
   */
  public static function getRawComposerContent(): string {
    return file_get_contents(self::getComposerJsonPath());
  }

}
