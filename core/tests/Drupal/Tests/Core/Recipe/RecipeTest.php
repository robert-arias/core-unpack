<?php

namespace Drupal\Tests\Core\Recipe;

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeFileException;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \Drupal\Core\Recipe\Recipe
 * @group Recipe
 */
class RecipeTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    // Create recipes for testing.
    vfsStream::setup('recipes', NULL, [
      'no_composer' => [
        'recipe.yml' => <<<EOF
type: 'Testing'
EOF,
      ],
      'no_recipe' => [
        'composer.json' => <<<EOF
{
  "name": "drupal_recipe/no_extensions",
  "type": "drupal-recipe"
}
EOF,
      ],
      'wrong_type' => [
        'composer.json' => <<<EOF
{
  "name": "drupal_recipe/wrong_type",
  "type": "drupal-module"
}
EOF,
        'recipe.yml' => <<<EOF
type: 'Testing'
EOF,
      ],

      'no_extensions' => [
        'composer.json' => <<<EOF
{
  "name": "drupal_recipe/no_extensions",
  "description": "An example Drupal recipe description with no extensions",
  "type": "drupal-recipe"
}
EOF,
        'recipe.yml' => <<<EOF
type: 'Testing'
EOF,
      ],
      'install_two_modules' => [
        'composer.json' => <<<EOF
{
  "name": "drupal_recipe/install_two_modules",
  "description": "An example Drupal recipe that would install two modules",
  "type": "drupal-recipe"
}
EOF,
        'recipe.yml' => <<<EOF
type: 'Content type'
install:
  - node
  - text
EOF,
      ],

    ]);
  }

  public function providerTestCreateFromDirectory() {
    return [
      'no extensions' => ['no_extensions', 'drupal_recipe/no_extensions' , 'Testing', []],
      'install_two_modules' => ['install_two_modules', 'drupal_recipe/install_two_modules' , 'Content type', ['node', 'text']],
    ];
  }

  /**
   * @dataProvider providerTestCreateFromDirectory
   */
  public function testCreateFromDirectory(string $recipe_name, string $expected_name, string $expected_type, array $expected_extensions): void {
    $recipe = Recipe::createFromDirectory(vfsStream::url('recipes/' . $recipe_name));
    $this->assertSame($expected_name, $recipe->name);
    $this->assertSame($expected_type, $recipe->type);
    $this->assertSame($expected_extensions, $recipe->install->extensions);
  }

  public function testCreateFromDirectoryNoRecipe(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('There is no vfs://recipes/no_recipe/recipe.yml file');
    Recipe::createFromDirectory(vfsStream::url('recipes/no_recipe'));
  }

  public function testCreateFromDirectoryNoComposer(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('There is no vfs://recipes/no_composer/composer.json file');
    Recipe::createFromDirectory(vfsStream::url('recipes/no_composer'));
  }

  public function testCreateFromDirectoryWrongComposerType(): void {
    $this->expectException(RecipeFileException::class);
    $this->expectExceptionMessage('The composer project type must be: drupal-recipe');
    Recipe::createFromDirectory(vfsStream::url('recipes/wrong_type'));
  }

}
