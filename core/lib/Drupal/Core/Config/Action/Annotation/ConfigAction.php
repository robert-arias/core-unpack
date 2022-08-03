<?php

namespace Drupal\Core\Config\Action\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ConfigAction annotation object.
 *
 * @ingroup config_action_api
 *
 * @Annotation
 */
class ConfigAction extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The administrative label of the config action.
   *
   * @var \Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public $admin_label = '';

}
