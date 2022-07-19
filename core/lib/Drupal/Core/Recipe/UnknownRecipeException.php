<?php

namespace Drupal\Core\Recipe;

/**
 * Exception thrown when recipe is can not be found.
 *
 * @internal
 *   This API is experimental.
 */
final class UnknownRecipeException extends \RuntimeException {

  /**
   * @param string $recipe
   *   The recipe's name.
   * @param array $searchPaths
   *   The paths searched for the recipe.
   * @param string $message
   *   (optional) The exception message.
   * @param int $code
   *   (optional) The exception code.
   * @param \Throwable|null $previous
   *   (optional) The previous exception.
   */
  public function __construct(public readonly string $recipe, public readonly array $searchPaths, string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

}
