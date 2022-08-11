<?php

namespace Drupal\KernelTests\Core\Config\Action;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\DuplicateConfigActionIdException;
use Drupal\Core\Config\Action\EntityMethodException;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the config action system.
 *
 * @group config
 */
class ConfigActionTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['config_test'];

  /**
   * @see \Drupal\Core\Config\Action\Plugin\ConfigAction\EntityCreate
   */
  public function testEntityCreate(): void {
    $this->assertCount(0, \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple(), 'There are no config_test entities');
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('entity_create:ensure_exists', 'config_test.dynamic.action_test', ['label' => 'Action test']);
    /** @var \Drupal\config_test\Entity\ConfigTest[] $config_test_entities */
    $config_test_entities = \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple();
    $this->assertCount(1, \Drupal::entityTypeManager()->getStorage('config_test')->loadMultiple(), 'There is 1 config_test entity');
    $this->assertSame('Action test', $config_test_entities['action_test']->label());
    $this->assertTrue(Uuid::isValid((string) $config_test_entities['action_test']->uuid()), 'Config entity assigned a valid UUID');

    // Calling ensure exists action again will not error.
    $manager->applyAction('entity_create:ensure_exists', 'config_test.dynamic.action_test', ['label' => 'Action test']);

    try {
      $manager->applyAction('entity_create:create', 'config_test.dynamic.action_test', ['label' => 'Action test']);
      $this->fail('Expected exception not thrown');
    }
    catch (ConfigActionException $e) {
      $this->assertSame('Entity config_test.dynamic.action_test exists', $e->getMessage());
    }
  }

  /**
   * @see \Drupal\Core\Config\Action\Plugin\ConfigAction\EntityMethod
   */
  public function testEntityMethod(): void {
    $this->installConfig('config_test');
    $storage = \Drupal::entityTypeManager()->getStorage('config_test');

    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Default', $config_test_entity->getProtectedProperty());

    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    // Call a method action.
    $manager->applyAction('entity_method:config_test.dynamic:setProtectedProperty', 'config_test.dynamic.dotted.default', 'Test value');
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Test value', $config_test_entity->getProtectedProperty());

    $manager->applyAction('entity_method:config_test.dynamic:setProtectedProperty', 'config_test.dynamic.dotted.default', 'Test value 2');
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Test value 2', $config_test_entity->getProtectedProperty());

    $manager->applyAction('entity_method:config_test.dynamic:concatProtectedProperty', 'config_test.dynamic.dotted.default', ['Test value ', '3']);
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Test value 3', $config_test_entity->getProtectedProperty());

    $manager->applyAction('entity_method:config_test.dynamic:concatProtectedPropertyOptional', 'config_test.dynamic.dotted.default', ['Test value ', '4']);
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Test value 4', $config_test_entity->getProtectedProperty());

    // Test calling an action that has 2 arguments but one is optional with an
    // array value.
    $manager->applyAction('entity_method:config_test.dynamic:concatProtectedPropertyOptional', 'config_test.dynamic.dotted.default', ['Test value 5']);
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Test value 5', $config_test_entity->getProtectedProperty());

    // Test calling an action that has 2 arguments but one is optional with a
    // non array value.
    $manager->applyAction('entity_method:config_test.dynamic:concatProtectedPropertyOptional', 'config_test.dynamic.dotted.default', 'Test value 6');
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Test value 6', $config_test_entity->getProtectedProperty());

    // Test calling an action that expects no arguments.
    $manager->applyAction('entity_method:config_test.dynamic:defaultProtectedProperty', 'config_test.dynamic.dotted.default', []);
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame('Set by method', $config_test_entity->getProtectedProperty());

    $manager->applyAction('entity_method:config_test.dynamic:addToArray', 'config_test.dynamic.dotted.default', 'foo');
    $manager->applyAction('entity_method:config_test.dynamic:addToArray', 'config_test.dynamic.dotted.default', 'bar');
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame(['foo', 'bar'], $config_test_entity->getArrayProperty());

    $manager->applyAction('entity_method:config_test.dynamic:addToArray', 'config_test.dynamic.dotted.default', ['a', 'b', 'c']);
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame(['foo', 'bar', ['a', 'b', 'c']], $config_test_entity->getArrayProperty());

    $manager->applyAction('entity_method:config_test.dynamic:setArray', 'config_test.dynamic.dotted.default', ['a', 'b', 'c']);
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame(['a', 'b', 'c'], $config_test_entity->getArrayProperty());

    $manager->applyAction('entity_method:config_test.dynamic:setArray', 'config_test.dynamic.dotted.default', [['a', 'b', 'c'], ['a']]);
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('dotted.default');
    $this->assertSame([['a', 'b', 'c'], ['a']], $config_test_entity->getArrayProperty());

    $config_test_entity->delete();
    try {
      $manager->applyAction('entity_method:config_test.dynamic:setProtectedProperty', 'config_test.dynamic.dotted.default', 'Test value');
      $this->fail('Expected exception not thrown');
    }
    catch (ConfigActionException $e) {
      $this->assertSame('Entity config_test.dynamic.dotted.default does not exist', $e->getMessage());
    }
  }

  /**
   * @see \Drupal\Core\Config\Action\Plugin\ConfigAction\EntityMethod
   */
  public function testEntityMethodException(): void {
    $this->installConfig('config_test');
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    $this->expectException(EntityMethodException::class);
    $this->expectExceptionMessage('Entity method config action \'entity_method:config_test.dynamic:concatProtectedProperty\' requires an array value. The number of parameters or required parameters for Drupal\config_test\Entity\ConfigTest::concatProtectedProperty() is not 1');
    $manager->applyAction('entity_method:config_test.dynamic:concatProtectedProperty', 'config_test.dynamic.dotted.default', 'Test value');
  }

  /**
   * @see \Drupal\Core\Config\Action\Plugin\ConfigAction\SimpleConfigUpdate
   */
  public function testSimpleConfigUpdate(): void {
    $this->installConfig('config_test');
    $this->assertSame('bar', $this->config('config_test.system')->get('foo'));

    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    // Call the simple config update action.
    $manager->applyAction('simple_config_update', 'config_test.system', ['foo' => 'Yay!']);
    $this->assertSame('Yay!', $this->config('config_test.system')->get('foo'));

    try {
      $manager->applyAction('simple_config_update', 'config_test.system', 'Test');
      $this->fail('Expected exception not thrown');
    }
    catch (ConfigActionException $e) {
      $this->assertSame('Config config_test.system can not be updated because $value is not an array', $e->getMessage());
    }

    $this->config('config_test.system')->delete();
    try {
      $manager->applyAction('simple_config_update', 'config_test.system', ['foo' => 'Yay!']);
      $this->fail('Expected exception not thrown');
    }
    catch (ConfigActionException $e) {
      $this->assertSame('Config config_test.system does not exist so can not be updated', $e->getMessage());
    }
  }

  /**
   * @see \Drupal\Core\Config\Action\ConfigActionManager::getShorthandActionIdsForEntityType()
   */
  public function testShorthandActionIds(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('config_test');
    $this->assertCount(0, $storage->loadMultiple(), 'There are no config_test entities');
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('ensure_exists', 'config_test.dynamic.action_test', ['label' => 'Action test', 'protected_property' => '']);
    /** @var \Drupal\config_test\Entity\ConfigTest[] $config_test_entities */
    $config_test_entities = $storage->loadMultiple();
    $this->assertCount(1, $config_test_entities, 'There is 1 config_test entity');
    $this->assertSame('Action test', $config_test_entities['action_test']->label());

    $this->assertSame('', $config_test_entities['action_test']->getProtectedProperty());

    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    // Call a method action.
    $manager->applyAction('setProtectedProperty', 'config_test.dynamic.action_test', 'Test value');
    /** @var \Drupal\config_test\Entity\ConfigTest $config_test_entity */
    $config_test_entity = $storage->load('action_test');
    $this->assertSame('Test value', $config_test_entity->getProtectedProperty());
  }

  /**
   * @see \Drupal\Core\Config\Action\ConfigActionManager::getShorthandActionIdsForEntityType()
   */
  public function testDuplicateShorthandActionIds(): void {
    $this->enableModules(['config_action_duplicate_test']);
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    $this->expectException(DuplicateConfigActionIdException::class);
    $this->expectExceptionMessage("The plugins 'entity_method:config_test.dynamic:setProtectedProperty' and 'config_action_duplicate_test:config_test.dynamic:setProtectedProperty' both resolve to the same shorthand action ID for the 'config_test' entity type");
    $manager->applyAction('ensure_exists', 'config_test.dynamic.action_test', ['label' => 'Action test', 'protected_property' => '']);

  }

  /**
   * @see \Drupal\Core\Config\Action\ConfigActionManager
   */
  public function testMissingAction(): void {
    $this->expectException(PluginNotFoundException::class);
    $this->expectErrorMessageMatches('/^The "does_not_exist" plugin does not exist/');
    $this->container->get('plugin.manager.config_action')->applyAction('does_not_exist', 'config_test.system', ['foo' => 'Yay!']);
  }

}
