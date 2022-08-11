<?php

namespace Drupal\Core\Config\Action;

interface ConfigActionPluginInterface {

  /**
   * Applies the config action.
   *
   * @param string $configName
   *   The name of the config to apply the action to.
   * @param mixed $value
   *   The value for the action to use.
   *
   * @throws ConfigActionException
   */
  public function apply(string $configName, mixed $value): void;

}
