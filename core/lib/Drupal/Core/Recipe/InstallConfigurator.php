<?php

namespace Drupal\Core\Recipe;

use Drupal\Component\Assertion\Inspector;

class InstallConfigurator {

  /**
   * @param string[] $extensions
   *   A list of extensions for a recipe to install.
   */
  public function __construct(public readonly array $extensions) {
    assert(Inspector::assertAllStrings($extensions), 'Extension names must be strings.');
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292281
  }

}
