<?php

namespace Drupal\Core\Recipe;

use Drupal\Component\Assertion\Inspector;

/**
 * Exception thrown when recipes contain or depend on missing extensions.
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeMissingExtensionsException extends \RuntimeException {

  /**
   * Constructs a RecipeMissingExtensionsException.
   *
   * @param array $extensions
   *   The list of missing extensions.
   * @param string $message
   *   [optional] The Exception message to throw.
   * @param int $code
   *   [optional] The Exception code.
   * @param null|\Throwable $previous
   *   [optional] The previous throwable used for the exception chaining.
   */
  public function __construct(public readonly array $extensions, string $message = "", int $code = 0, ?\Throwable $previous = NULL) {
    assert(Inspector::assertAllStrings($extensions), 'Extension names must be strings.');
    parent::__construct($message, $code, $previous);
  }

}
