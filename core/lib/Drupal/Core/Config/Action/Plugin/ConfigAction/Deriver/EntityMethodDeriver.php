<?php

namespace Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\Action\Attribute\ActionMethod;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives config action methods from attributed config entity methods.
 *
 * @internal
 *   This API is experimental.
 */
final class EntityMethodDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructs new EntityMethodDeriver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Scan all the config entity classes for attributes.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type instanceof ConfigEntityTypeInterface) {
        $reflectionClass = new \ReflectionClass($entity_type->getClass());
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
          foreach ($method->getAttributes(ActionMethod::class) as $attribute) {
            $derivative = $base_plugin_definition;
            /** @var \Drupal\Core\Config\Action\Attribute\ActionMethod  $action_attribute */
            $action_attribute = $attribute->newInstance();

            $derivative['admin_label'] = $action_attribute->adminLabel ?: $this->t('@entity_type @method', [$entity_type->getLabel(), $method->name]);
            $derivative['constructor_args'] = [
              'method' => $method->name,
              'exists' => $action_attribute->exists,
              'numberOfParams' => $method->getNumberOfParameters(),
              'numberOfRequiredParams' => $method->getNumberOfRequiredParameters(),
            ];
            $derivative['entity_types'] = [$entity_type->id()];
            // Build a config action identifier from the entity type's config
            // prefix  and the method name. For example, the Role entity adds a
            // 'user.role:grantPermission' action.
            $derivative_id = $entity_type->getConfigPrefix() . PluginBase::DERIVATIVE_SEPARATOR . $method->name;
            $this->derivatives[$derivative_id] = $derivative;
          }
        }
      }
    }
    return $this->derivatives;
  }

}
