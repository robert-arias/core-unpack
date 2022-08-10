<?php

namespace Drupal\Core\Config\Action\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
  public string $id;

  /**
   * The administrative label of the config action.
   *
   * @var \Drupal\Core\Annotation\Translation|\Drupal\Core\StringTranslation\TranslatableMarkup|string
   *
   * @ingroup plugin_translatable
   */
  public Translation|TranslatableMarkup|string $admin_label = '';

  /**
   * Allows action shorthand IDs for the listed config entity types.
   *
   * If '*' is present in the array then it can apply to all entity types. An
   * empty array means that shorthand action IDs are not available for this
   * plugin.
   *
   * @see \Drupal\Core\Config\Action\ConfigActionManager::convertActionToPluginId()
   *
   * @var string[]
   */
  public array $entity_types = [];

}
