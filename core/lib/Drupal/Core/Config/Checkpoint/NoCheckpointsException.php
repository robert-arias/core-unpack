<?php

namespace Drupal\Core\Config\Checkpoint;

/**
 * Thrown when using the checkpoint storage with no checkpoints.
 */
final class NoCheckpointsException extends \RuntimeException {

  /**
   * {@inheritdoc}
   */
  protected $message = 'This storage cannot be read because there are no checkpoints';

}
