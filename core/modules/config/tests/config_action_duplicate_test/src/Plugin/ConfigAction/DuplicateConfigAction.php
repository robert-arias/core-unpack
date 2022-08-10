<?php

namespace Drupal\config_duplicate_action_test\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionPluginInterface;

/**
 * @ConfigAction(
 *   id = "config_action_duplicate_test:config_test.dynamic:setProtectedProperty",
 *   admin_label = @Translation("A duplicate config action"),
 *   entity_types = {
 *     "config_test"
 *   }
 * )
 */
final class DuplicateConfigAction implements ConfigActionPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // This method wit
    throw new \BadMethodCallException();
  }

}
