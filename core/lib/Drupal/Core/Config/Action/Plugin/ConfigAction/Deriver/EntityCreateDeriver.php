<?php

namespace Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\Action\Exists;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @internal
 *   This API is experimental.
 */
class EntityCreateDeriver extends DeriverBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives['ensure_exists'] = $base_plugin_definition + ['additional' => ['exists' => Exists::RETURN_EARLY_IF_EXISTS], 'admin_label' => $this->t('Ensure entity exists')];
    $this->derivatives['create'] = $base_plugin_definition + ['additional' => ['exists' => Exists::ERROR_IF_EXISTS], 'admin_label' => $this->t('Entity create')];
    return $this->derivatives;
  }

}
