<?php

namespace Drupal\Core\Recipe;

/**
 * @internal
 *   This API is experimental.
 */
final class ContentConfigurator {

  /**
   * @param array $content
   *   Content options for a recipe.
   */
  public function __construct(public readonly array $content) {
    // @todo https://www.drupal.org/project/distributions_recipes/issues/3292287
  }

}
