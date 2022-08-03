<?php

namespace Drupal\Core\Config\Action;

interface ConfigActionPluginInterface {

  /**
   * Applies the config action.
   *
   * @throws ConfigActionException
   */
  public function apply(string $configName, mixed $value): void;

}
