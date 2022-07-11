<?php

namespace Drupal\Core\Recipe;

class ConfigConfigurator {

  /**
   * @param array $config
   *   Config options for a recipe.
   */
  public function __construct(public readonly array $config) {
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292282
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292284
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292286
  }

}
