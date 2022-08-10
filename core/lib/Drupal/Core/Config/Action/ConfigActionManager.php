<?php

namespace Drupal\Core\Config\Action;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * @defgroup config_action_api Config Action API
 * @{
 * Information about the classes and interfaces that make up the Config Action
 * API.
 *
 * Configuration actions are plugins that manipulate simple configuration or
 * configuration entities. The configuration action plugin manager can apply
 * configuration actions. For example, the API is leveraged by recipes to create
 * roles if they do not exist already and grant permissions to those roles.
 *
 * To define a configuration action in a module you need to:
 * - Define a Config Action plugin by creating a new class that implements the
 *   \Drupal\Core\Config\Action\ConfigActionPluginInterface, in namespace
 *   Plugin\ConfigAction under your module namespace. For more information about
 *   creating plugins, see the @link plugin_api Plugin API topic. @endlink
 * - Config action plugins use the annotations defined by
 *  \Drupal\Core\Config\Action\Annotation\ConfigAction. See the
 *   @link annotation Annotations topic @endlink for more information about
 *   annotations.
 *
 * Further information and examples:
 * - \Drupal\Core\Config\Action\Plugin\ConfigAction\EntityMethod derives
 *   configuration actions from config entity methods which have the
 *   \Drupal\Core\Config\Action\Attribute\ActionMethod attribute.
 * - \Drupal\Core\Config\Action\Plugin\ConfigAction\EntityCreate allows you to
 *   create configuration entities if they do not exist.
 * - \Drupal\Core\Config\Action\Plugin\ConfigAction\SimpleConfigUpdate allows
 *   you to update simple configuration using a config action.
 * @}
 */
class ConfigActionManager extends DefaultPluginManager {

  /**
   * Constructs a new \Drupal\Core\Config\Action\ConfigActionManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, protected readonly ConfigManagerInterface $configManager) {
    assert($namespaces instanceof \ArrayAccess, '$namespaces can be accessed like an array');
    // Enable this namespace to be searched for plugins.
    $namespaces[__NAMESPACE__] = 'core/lib/Drupal/Core/Config/Action';

    parent::__construct('Plugin/ConfigAction', $namespaces, $module_handler, 'Drupal\Core\Config\Action\ConfigActionPluginInterface', 'Drupal\Core\Config\Action\Annotation\ConfigAction');

    $this->alterInfo('config_action');
    $this->setCacheBackend($cache_backend, 'config_action');
  }

  /**
   * Applies a config action.
   *
   * @param string $action_id
   *   The ID of the action to apply. This can be a complete configuration
   *   action plugin ID or a shorthand action ID that is available for the
   *   entity type of the provided configuration name.
   * @param string $configName
   *   The configuration name.
   * @param mixed $data
   *   The data for the action.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown when the config action cannot be found.
   * @throws \Drupal\Core\Config\Action\ConfigActionException
   *   Thrown when the config action fails to apply.
   */
  public function applyAction(string $action_id, string $configName, mixed $data): void {
    if (!$this->hasDefinition($action_id)) {
      // Get the full plugin ID from the shorthand map, if it is available.
      $entity_type = $this->configManager->getEntityTypeIdByName($configName);
      if ($entity_type) {
        $action_id = $this->getShorthandActionIdsForEntityType($entity_type)[$action_id] ?? $action_id;
      }
    }
    /** @var \Drupal\Core\Config\Action\ConfigActionPluginInterface $action */
    $action = $this->createInstance($action_id);
    $action->apply($configName, $data);
  }

  /**
   * Gets a map of shorthand action IDs to plugin IDs for an entity type.
   *
   * @param string $entityType
   *   The entity type ID to get the map for.
   *
   * @return string[]
   *   An array of plugin IDs keyed by shorthand action ID for the provided
   *   entity type.
   */
  protected function getShorthandActionIdsForEntityType(string $entityType): array {
    $map = [];
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      if (in_array($entityType, $definition['entity_types'], TRUE) || in_array('*', $definition['entity_types'], TRUE)) {
        $regex = '/' . PluginBase::DERIVATIVE_SEPARATOR . '([^' . PluginBase::DERIVATIVE_SEPARATOR . ']*)$/';
        $action_id = preg_match($regex, $plugin_id, $matches) ? $matches[1] : $plugin_id;
        if (isset($map[$action_id])) {
          throw new DuplicateConfigActionIdException(sprintf('The plugins \'%s\' and \'%s\' both resolve to the same shorthand action ID for the \'%s\' entity type', $plugin_id, $map[$action_id], $entityType));
        }
        $map[$action_id] = $plugin_id;
      }
    }
    return $map;
  }

}
