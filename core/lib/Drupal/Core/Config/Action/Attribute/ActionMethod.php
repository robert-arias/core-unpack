<?php

namespace Drupal\Core\Config\Action\Attribute;

use Drupal\Core\Config\Action\Exists;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @internal
 *   This API is experimental.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ActionMethod {

  /**
   * @param \Drupal\Core\Config\Action\Exists $exists
   *   Determines behavior of action depending on entity existence.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $adminLabel
   *   The admin label for the user interface.
   */
  public function __construct(public readonly Exists $exists = Exists::ERROR_IF_NOT_EXISTS, public readonly TranslatableMarkup|string $adminLabel = '') {
  }

}
