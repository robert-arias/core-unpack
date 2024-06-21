<?php

namespace Drupal\Composer\Plugin\Unpack\Unpackers;

class RecipeUnpacker extends UnpackerBase implements UnpackerInterface {

  /**
   * The recipe's composer type.
   *
   * @var string
   */
  const ID = 'drupal-recipe';

  /**
   * {@inheritdoc}
   */
  public function getUnpackerId(): string {
    return self::ID;
  }

  /**
   * {@inheritdoc}
   */
  public function unpackRecursively(): bool {
    return TRUE;
  }

}
