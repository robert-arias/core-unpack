<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\DependencyInjection\Dumper;

use Drupal\Component\Utility\Crypt;
use Drupal\TestTools\Extension\DeprecationBridge\ExpectDeprecationTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophet;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @coversDefaultClass \Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper
 * @group DependencyInjection
 */
class OptimizedPhpArrayDumperTest extends TestCase {

  use ExpectDeprecationTrait;
  use ProphecyTrait;

  /**
   * The container builder instance.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $containerBuilder;

  /**
   * The definition for the container to build in tests.
   *
   * @var array
   */
  protected $containerDefinition;

  /**
   * Whether the dumper uses the machine-optimized format or not.
   *
   * @var bool
   */
  protected $machineFormat = TRUE;

  /**
   * Stores the dumper class to use.
   *
   * @var string
   */
  protected $dumperClass = '\Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper';

  /**
   * The dumper instance.
   *
   * @var \Symfony\Component\DependencyInjection\Dumper\DumperInterface
   */
  protected $dumper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Setup a mock container builder.
    $this->containerBuilder = $this->prophesize('\Symfony\Component\DependencyInjection\ContainerBuilder');
    $this->containerBuilder->getAliases()->willReturn([]);
    $this->containerBuilder->getParameterBag()->willReturn(new ParameterBag());
    $this->containerBuilder->getDefinitions()->willReturn([]);
    $this->containerBuilder->isCompiled()->willReturn(TRUE);

    $definition = [];
    $definition['aliases'] = [];
    $definition['parameters'] = [];
    $definition['services'] = [];
    $definition['frozen'] = TRUE;
    $definition['machine_format'] = $this->machineFormat;

    $this->containerDefinition = $definition;

    // Create the dumper.
    $this->dumper = new $this->dumperClass($this->containerBuilder->reveal());
  }

  /**
   * Tests that an empty container works properly.
   *
   * @covers ::dump
   * @covers ::getArray
   * @covers ::supportsMachineFormat
   */
  public function testDumpForEmptyContainer() {
    $serialized_definition = $this->dumper->dump();
    $this->assertEquals(serialize($this->containerDefinition), $serialized_definition);
  }

  /**
   * Tests that alias processing works properly.
   *
   * @covers ::getAliases
   *
   * @dataProvider getAliasesDataProvider
   */
  public function testGetAliases($aliases, $definition_aliases) {
    $this->containerDefinition['aliases'] = $definition_aliases;
    $this->containerBuilder->getAliases()->willReturn($aliases);
    $this->assertEquals($this->containerDefinition, $this->dumper->getArray(), 'Expected definition matches dump.');
  }

  /**
   * Data provider for testGetAliases().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - aliases as returned by ContainerBuilder.
   *     - aliases as expected in the container definition.
   */
  public static function getAliasesDataProvider() {
    return [
      [[], []],
      [
        ['foo' => 'foo.alias'],
        ['foo' => 'foo.alias'],
      ],
      [
        ['foo' => 'foo.alias', 'foo.alias' => 'foo.alias.alias'],
        ['foo' => 'foo.alias.alias', 'foo.alias' => 'foo.alias.alias'],
      ],
    ];
  }

  /**
   * Tests that parameter processing works properly.
   *
   * @covers ::getParameters
   * @covers ::prepareParameters
   * @covers ::escape
   * @covers ::dumpValue
   * @covers ::getReferenceCall
   *
   * @dataProvider getParametersDataProvider
   */
  public function testGetParameters($parameters, $definition_parameters, $is_frozen) {
    $this->containerDefinition['parameters'] = $definition_parameters;
    $this->containerDefinition['frozen'] = $is_frozen;

    $parameter_bag = new ParameterBag($parameters);
    $this->containerBuilder->getParameterBag()->willReturn($parameter_bag);
    $this->containerBuilder->isCompiled()->willReturn($is_frozen);

    $this->assertEquals($this->containerDefinition, $this->dumper->getArray(), 'Expected definition matches dump.');
  }

  /**
   * Data provider for testGetParameters().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - parameters as returned by ContainerBuilder.
   *     - parameters as expected in the container definition.
   *     - frozen value
   */
  public static function getParametersDataProvider() {
    return [
      [[], [], TRUE],
      [
        ['foo' => 'value_foo'],
        ['foo' => 'value_foo'],
        TRUE,
      ],
      [
        ['foo' => ['llama' => 'yes']],
        ['foo' => ['llama' => 'yes']],
        TRUE,
      ],
      [
        ['foo' => '%llama%', 'llama' => 'yes'],
        ['foo' => '%%llama%%', 'llama' => 'yes'],
        TRUE,
      ],
      [
        ['foo' => '%llama%', 'llama' => 'yes'],
        ['foo' => '%llama%', 'llama' => 'yes'],
        FALSE,
      ],
    ];
  }

  /**
   * Tests that service processing works properly.
   *
   * @covers ::getServiceDefinitions
   * @covers ::getServiceDefinition
   * @covers ::dumpMethodCalls
   * @covers ::dumpCollection
   * @covers ::dumpCallable
   * @covers ::dumpValue
   * @covers ::getPrivateServiceCall
   * @covers ::getReferenceCall
   * @covers ::getServiceCall
   * @covers ::getServiceClosureCall
   * @covers ::getParameterCall
   *
   * @dataProvider getDefinitionsDataProvider
   */
  public function testGetServiceDefinitions($services, $definition_services) {
    $this->containerDefinition['services'] = $definition_services;

    $this->containerBuilder->getDefinitions()->willReturn($services);

    $bar_definition = new Definition('\stdClass');
    $bar_definition->setPublic(TRUE);
    $this->containerBuilder->getDefinition('bar')->willReturn($bar_definition);

    $private_definition = new Definition('\stdClass');
    $private_definition->setPublic(FALSE);

    $this->containerBuilder->getDefinition('private_definition')->willReturn($private_definition);

    $this->assertEquals($this->containerDefinition, $this->dumper->getArray(), 'Expected definition matches dump.');
  }

  /**
   * Data provider for testGetServiceDefinitions().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - parameters as returned by ContainerBuilder.
   *     - parameters as expected in the container definition.
   *     - frozen value
   */
  public static function getDefinitionsDataProvider() {
    $base_service_definition = [
      'class' => '\stdClass',
      'public' => TRUE,
      'file' => FALSE,
      'synthetic' => FALSE,
      'lazy' => FALSE,
      'arguments' => [],
      'arguments_count' => 0,
      'properties' => [],
      'calls' => [],
      'shared' => TRUE,
      'factory' => FALSE,
      'configurator' => FALSE,
    ];

    // Test basic flags.
    $service_definitions[] = [] + $base_service_definition;

    $service_definitions[] = [
      'public' => FALSE,
    ] + $base_service_definition;

    $service_definitions[] = [
      'file' => 'test_include.php',
    ] + $base_service_definition;

    $service_definitions[] = [
      'synthetic' => TRUE,
    ] + $base_service_definition;

    $service_definitions[] = [
      'shared' => FALSE,
    ] + $base_service_definition;

    $service_definitions[] = [
      'lazy' => TRUE,
    ] + $base_service_definition;

    // Test a basic public Reference.
    $service_definitions[] = [
      'arguments' => ['foo', new Reference('bar')],
      'arguments_count' => 2,
      'arguments_expected' => static::getCollection(['foo', static::getServiceCall('bar')]),
    ] + $base_service_definition;

    // Test a public reference that should not throw an Exception.
    $reference = new Reference('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    $service_definitions[] = [
      'arguments' => [$reference],
      'arguments_count' => 1,
      'arguments_expected' => static::getCollection([static::getServiceCall('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE)]),
    ] + $base_service_definition;

    // Test a private shared service, denoted by having a Reference.
    $private_definition = [
      'class' => '\stdClass',
      'public' => FALSE,
      'arguments_count' => 0,
    ];

    $service_definitions[] = [
      'arguments' => ['foo', new Reference('private_definition')],
      'arguments_count' => 2,
      'arguments_expected' => static::getCollection([
        'foo',
        static::getPrivateServiceCall('private_definition', $private_definition, TRUE),
      ]),
    ] + $base_service_definition;

    // Test a service closure.
    $service_definitions[] = [
      'arguments' => [
        'foo',
        [
          'alias-1' => new ServiceClosureArgument(new Reference('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE)),
          'alias-2' => new ServiceClosureArgument(new Reference('bar', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)),
        ],
      ],
      'arguments_count' => 2,
      'arguments_expected' => static::getCollection([
        'foo',
        static::getCollection([
          'alias-1' => static::getServiceClosureCall('bar', ContainerInterface::NULL_ON_INVALID_REFERENCE),
          'alias-2' => static::getServiceClosureCall('bar', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE),
        ]),
      ]),
    ] + $base_service_definition;

    // Test a private non-shared service, denoted by having a Definition.
    $private_definition_object = new Definition('\stdClass');
    $private_definition_object->setPublic(FALSE);

    $service_definitions[] = [
      'arguments' => ['foo', $private_definition_object],
      'arguments_count' => 2,
      'arguments_expected' => static::getCollection([
        'foo',
        static::getPrivateServiceCall(NULL, $private_definition),
      ]),
    ] + $base_service_definition;

    // Test a deep collection without a reference.
    $service_definitions[] = [
      'arguments' => [[['foo']]],
      'arguments_count' => 1,
    ] + $base_service_definition;

    // Test a deep collection with a reference to resolve.
    $service_definitions[] = [
      'arguments' => [[new Reference('bar')]],
      'arguments_count' => 1,
      'arguments_expected' => static::getCollection([static::getCollection([static::getServiceCall('bar')])]),
    ] + $base_service_definition;

    // Test an IteratorArgument collection with a reference to resolve.
    $service_definitions[] = [
      'arguments' => [new IteratorArgument([new Reference('bar')])],
      'arguments_count' => 1,
      'arguments_expected' => static::getCollection([static::getIterator([static::getServiceCall('bar')])]),
    ] + $base_service_definition;

    // Test a collection with a variable to resolve.
    $service_definitions[] = [
      'arguments' => [new Parameter('llama_parameter')],
      'arguments_count' => 1,
      'arguments_expected' => static::getCollection([static::getParameterCall('llama_parameter')]),
    ] + $base_service_definition;

    // Test getMethodCalls.
    $calls = [
      ['method', static::getCollection([])],
      ['method2', static::getCollection([])],
    ];
    $service_definitions[] = [
      'calls' => $calls,
    ] + $base_service_definition;

    $service_definitions[] = [
      'shared' => FALSE,
    ] + $base_service_definition;

    // Test factory.
    $service_definitions[] = [
      'factory' => [new Reference('bar'), 'factoryMethod'],
      'factory_expected' => [static::getServiceCall('bar'), 'factoryMethod'],
    ] + $base_service_definition;

    // Test invalid factory - needed to test deep dumpValue().
    $service_definitions[] = [
      'factory' => [['foo', 'llama'], 'factoryMethod'],
    ] + $base_service_definition;

    // Test properties.
    $service_definitions[] = [
      'properties' => ['_value' => 'llama'],
    ] + $base_service_definition;

    // Test configurator.
    $service_definitions[] = [
      'configurator' => [new Reference('bar'), 'configureService'],
      'configurator_expected' => [static::getServiceCall('bar'), 'configureService'],
    ] + $base_service_definition;

    $services_provided = [];
    $services_provided[] = [
      [],
      [],
    ];

    foreach ($service_definitions as $service_definition) {
      $definition = (new Prophet())->prophesize('\Symfony\Component\DependencyInjection\Definition');
      $definition->getClass()->willReturn($service_definition['class']);
      $definition->isPublic()->willReturn($service_definition['public']);
      $definition->getFile()->willReturn($service_definition['file']);
      $definition->isSynthetic()->willReturn($service_definition['synthetic']);
      $definition->isLazy()->willReturn($service_definition['lazy']);
      $definition->getArguments()->willReturn($service_definition['arguments']);
      $definition->getProperties()->willReturn($service_definition['properties']);
      $definition->getMethodCalls()->willReturn($service_definition['calls']);
      $definition->isShared()->willReturn($service_definition['shared']);
      $definition->getDecoratedService()->willReturn(NULL);
      $definition->getFactory()->willReturn($service_definition['factory']);
      $definition->getConfigurator()->willReturn($service_definition['configurator']);

      // Preserve order.
      $filtered_service_definition = [];
      foreach ($base_service_definition as $key => $value) {
        $filtered_service_definition[$key] = $service_definition[$key];
        unset($service_definition[$key]);

        if ($key == 'class' || $key == 'arguments_count') {
          continue;
        }

        if ($filtered_service_definition[$key] === $base_service_definition[$key]) {
          unset($filtered_service_definition[$key]);
        }
      }

      // Add remaining properties.
      $filtered_service_definition += $service_definition;

      // Allow to set _expected values.
      foreach (['arguments', 'factory', 'configurator'] as $key) {
        $expected = $key . '_expected';
        if (isset($filtered_service_definition[$expected])) {
          $filtered_service_definition[$key] = $filtered_service_definition[$expected];
          unset($filtered_service_definition[$expected]);
        }
      }

      if (isset($filtered_service_definition['public']) && $filtered_service_definition['public'] === FALSE) {
        $services_provided[] = [
          ['foo_service' => $definition->reveal()],
          [],
        ];
        continue;
      }

      $services_provided[] = [
        ['foo_service' => $definition->reveal()],
        ['foo_service' => static::serializeDefinition($filtered_service_definition)],
      ];
    }

    return $services_provided;
  }

  /**
   * Helper function to serialize a definition.
   *
   * Used to override serialization.
   */
  protected static function serializeDefinition(array $service_definition) {
    return serialize($service_definition);
  }

  /**
   * Helper function to return a service definition.
   */
  protected static function getServiceCall($id, $invalid_behavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE) {
    return (object) [
      'type' => 'service',
      'id' => $id,
      'invalidBehavior' => $invalid_behavior,
    ];
  }

  /**
   * Helper function to return a service closure definition.
   */
  protected static function getServiceClosureCall($id, $invalid_behavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE) {
    return (object) [
      'type' => 'service_closure',
      'id' => $id,
      'invalidBehavior' => $invalid_behavior,
    ];
  }

  /**
   * Tests that references to aliases work correctly.
   *
   * @covers ::getReferenceCall
   *
   * @dataProvider publicPrivateDataProvider
   */
  public function testGetServiceDefinitionWithReferenceToAlias($public) {
    $bar_definition = new Definition('\stdClass');
    $bar_definition_php_array = [
      'class' => '\stdClass',
    ];
    if ($public) {
      $bar_definition->setPublic(TRUE);
    }
    else {
      $bar_definition->setPublic(FALSE);
      $bar_definition_php_array['public'] = FALSE;
    }
    $bar_definition_php_array['arguments_count'] = 0;

    $services['bar'] = $bar_definition;

    $aliases['bar.alias'] = 'bar';

    $foo = new Definition('\stdClass');
    $foo->setPublic(TRUE);
    $foo->addArgument(new Reference('bar.alias'));

    $services['foo'] = $foo;

    $this->containerBuilder->getAliases()->willReturn($aliases);
    $this->containerBuilder->getDefinitions()->willReturn($services);
    $this->containerBuilder->getDefinition('bar')->willReturn($bar_definition);
    $dump = $this->dumper->getArray();
    if ($public) {
      $service_definition = static::getServiceCall('bar');
    }
    else {
      $service_definition = static::getPrivateServiceCall('bar', $bar_definition_php_array, TRUE);
    }
    $data = [
      'class' => '\stdClass',
      'arguments' => static::getCollection([
        $service_definition,
      ]),
      'arguments_count' => 1,
    ];
    $this->assertEquals(static::serializeDefinition($data), $dump['services']['foo'], 'Expected definition matches dump.');
  }

  public static function publicPrivateDataProvider() {
    return [
      [TRUE],
      [FALSE],
    ];
  }

  /**
   * Tests that getDecoratedService() is unsupported.
   *
   * Tests that the correct InvalidArgumentException is thrown for
   * getDecoratedService().
   *
   * @covers ::getServiceDefinition
   */
  public function testGetServiceDefinitionForDecoratedService() {
    $bar_definition = new Definition('\stdClass');
    $bar_definition->setPublic(TRUE);
    $bar_definition->setDecoratedService((string) new Reference('foo'));
    $services['bar'] = $bar_definition;

    $this->containerBuilder->getDefinitions()->willReturn($services);
    $this->expectException(InvalidArgumentException::class);
    $this->dumper->getArray();
  }

  /**
   * Tests that the correct RuntimeException is thrown for expressions.
   *
   * @covers ::dumpValue
   */
  public function testGetServiceDefinitionForExpression() {
    $expression = new Expression('');

    $bar_definition = new Definition('\stdClass');
    $bar_definition->setPublic(TRUE);
    $bar_definition->addArgument($expression);
    $services['bar'] = $bar_definition;

    $this->containerBuilder->getDefinitions()->willReturn($services);
    $this->expectException(RuntimeException::class);
    $this->dumper->getArray();
  }

  /**
   * Tests that the correct RuntimeException is thrown for dumping an object.
   *
   * @covers ::dumpValue
   */
  public function testGetServiceDefinitionForObject() {
    $service = new \stdClass();

    $bar_definition = new Definition('\stdClass');
    $bar_definition->setPublic(TRUE);
    $bar_definition->addArgument($service);
    $services['bar'] = $bar_definition;

    $this->containerBuilder->getDefinitions()->willReturn($services);
    $this->expectException(RuntimeException::class);
    $this->dumper->getArray();
  }

  /**
   * Tests that the correct RuntimeException is thrown for dumping a resource.
   *
   * @covers ::dumpValue
   */
  public function testGetServiceDefinitionForResource() {
    $resource = fopen('php://memory', 'r');

    $bar_definition = new Definition('\stdClass');
    $bar_definition->setPublic(TRUE);
    $bar_definition->addArgument($resource);
    $services['bar'] = $bar_definition;

    $this->containerBuilder->getDefinitions()->willReturn($services);
    $this->expectException(RuntimeException::class);
    $this->dumper->getArray();
  }

  /**
   * Tests that service arguments with escaped percents are correctly dumped.
   *
   * @dataProvider percentsEscapeProvider
   */
  public function testPercentsEscape($expected, $argument) {
    $definition = new Definition('\stdClass', [$argument]);
    $definition->setPublic(TRUE);
    $this->containerBuilder->getDefinitions()->willReturn([
      'test' => $definition,
    ]);

    $dump = $this->dumper->getArray();

    $this->assertEquals(static::serializeDefinition([
      'class' => '\stdClass',
      'arguments' => static::getCollection([
        $this->getRaw($expected),
      ]),
      'arguments_count' => 1,
    ]), $dump['services']['test']);
  }

  /**
   * Data provider for testPercentsEscape().
   *
   * @return array[]
   *   Returns data-set elements with:
   *     - expected final value.
   *     - escaped value in service definition.
   */
  public static function percentsEscapeProvider() {
    return [
      ['%foo%', '%%foo%%'],
      ['foo%bar%', 'foo%%bar%%'],
      ['%foo%bar', '%%foo%%bar'],
      ['%', '%'],
      ['%', '%%'],
      ['%%', '%%%'],
      ['%%', '%%%%'],
    ];
  }

  /**
   * Helper function to return a private service definition.
   */
  protected static function getPrivateServiceCall($id, $service_definition, $shared = FALSE) {
    if (!$id) {
      $hash = Crypt::hashBase64(serialize($service_definition));
      $id = 'private__' . $hash;
    }
    return (object) [
      'type' => 'private_service',
      'id' => $id,
      'value' => $service_definition,
      'shared' => $shared,
    ];
  }

  /**
   * Helper function to return a machine-optimized collection.
   */
  protected static function getCollection($collection) {
    return (object) [
      'type' => 'collection',
      'value' => $collection,
    ];
  }

  /**
   * Helper function to return a machine-optimized iterator.
   */
  protected static function getIterator($collection) {
    return (object) [
      'type' => 'iterator',
      'value' => $collection,
    ];
  }

  /**
   * Helper function to return a parameter definition.
   */
  protected static function getParameterCall($name) {
    return (object) [
      'type' => 'parameter',
      'name' => $name,
    ];
  }

  /**
   * Helper function to return a raw value definition.
   */
  protected function getRaw($value) {
    return (object) [
      'type' => 'raw',
      'value' => $value,
    ];
  }

}

/**
 * Defines a dummy ExpressionLanguage component.
 *
 * As Drupal Core does not ship with ExpressionLanguage component we need to
 * define a dummy, else it cannot be tested.
 */
namespace Symfony\Component\ExpressionLanguage;

if (!class_exists('\Symfony\Component\ExpressionLanguage\Expression')) {
  /**
   * Dummy class to ensure non-existent Symfony component can be tested.
   */
  class Expression {

    public function __construct($expression) {
    }

    /**
     * Gets the string representation of the expression.
     */
    public function __toString() {
      return 'dummy_expression';
    }

  }
}
