<?php

namespace Drupal\Core\Recipe;

/**
 * Exception thrown when a recipe has configuration with unmet dependencies.
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeUnmetDependenciesException extends \RuntimeException {

  /**
   * Constructs a RecipeUnmetDependenciesException.
   *
   * @param string $configName
   *   The configuration name that has missing dependencies.
   * @param string $message
   *   [optional] The Exception message to throw.
   * @param int $code
   *   [optional] The Exception code.
   * @param null|\Throwable $previous
   *   [optional] The previous throwable used for the exception chaining.
   */
  public function __construct(public readonly string $configName, string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }

}
