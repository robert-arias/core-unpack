<?php

namespace Drupal\Composer\Plugin\Unpack\Unpackers;

/**
 * Interface for dependency unpackers.
 */
interface UnpackerInterface {

  /**
   * Get the ID of the unpacker.
   *
   * @return string
   *   The ID of the unpacker.
   */
  public function getUnpackerId(): string;

  /**
   * Unpack the package dependencies.
   */
  public function unpackDependencies(): void;

  /**
   * Whether the unpacker should remove itself from the root composer.json.
   *
   * @return bool
   *   TRUE if the unpacker should remove itself from the root composer.json.
   */
  public function removeSelf(): bool;

  /**
   * Whether the unpacker should unpack dev dependencies.
   *
   * @return bool
   *   TRUE if the unpacker should unpack dev dependencies.
   */
  public function shouldUnpackDevDependencies(): bool;

  /**
   * Whether the unpacker should unpack patches.
   *
   * @return bool
   *   TRUE if the unpacker should unpack patches.
   */
  public function shouldUnpackPatches(): bool;

  /**
   * Whether the package can be unpacked recursively.
   *
   * A package can be unpacked recursively if within its dependencies there are
   * packages of the same type of the unpacker.
   *
   * @return bool
   *   TRUE if the package can be unpacked recursively.
   */
  public function unpackRecursively(): bool;

}
