<?php

namespace Drupal\Core\Config\Action\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\Action\Exists;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ConfigAction(
 *   id = "entity_method",
 *   deriver = "\Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver\EntityMethodDeriver",
 * )
 *
 * @internal
 *   This API is experimental.
 */
final class EntityMethod implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a EntityMethod object.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param string $method
   *   The method to call on the config entity.
   * @param \Drupal\Core\Config\Action\Exists $exists
   *   Determines behavior of action depending on entity existence.
   */
  public function __construct(
    protected readonly ConfigManagerInterface $configManager,
    protected readonly string $method,
    protected readonly Exists $exists
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    assert(is_array($plugin_definition) && isset($plugin_definition['additional']['exists']) && isset($plugin_definition['additional']['method']), '$plugin_definition contains the expected settings');
    return new static($container->get('config.manager'), $plugin_definition['additional']['method'], $plugin_definition['additional']['exists']);
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface|null $entity */
    $entity = $this->configManager->loadConfigEntityByName($configName);
    if ($this->exists->returnEarly($configName, $entity)) {
      return;
    }
    $entity->{$this->method}($value);
    $entity->save();
  }

}
