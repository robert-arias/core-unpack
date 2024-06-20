<?php

namespace Drupal\Composer\Plugin\Unpack;

/**
 * Per-project options from the 'extras' section of the composer.json file.
 *
 * Projects that implement dependency unpacking plugin can further configure it.
 * This data is pulled from the 'drupal-unpack' portion of the extras section.
 *
 * @code
 *  "extras": {
 *    "drupal-unpack": {
 *      "drupal-recipes": {
 *        "unpack-dev-dependencies": true,
 *        "unpack-patches": true
 *      }
 *    }
 *  }
 * @endcode
 *
 * @internal
 */
class UnpackOptions {

  /**
   * The ID of the extra section in the top-level composer.json file.
   *
   * @var string
   */
  const ID = 'drupal-unpack';

  /**
   * The raw data from the 'extras' section of the top-level composer.json file.
   *
   * @var array
   */
  protected array $options = [];

  /**
   * UnpackOptions constructor.
   *
   * @param array $options
   *   The unpack options taken from the 'drupal-unpack' section.
   */
  protected function __construct(array $options) {
    $this->options = $options + [
      'unpack-dev-dependencies' => FALSE,
      'unpack-patches' => FALSE,
      'remove-self' => TRUE,
    ];
  }

  /**
   * Creates an unpack options object.
   *
   * @param array $extras
   *   The contents of the 'extras' section.
   *
   * @return self
   *   The unpack options object representing the provided unpack options
   */
  public static function create(array $extras, string $unpacker_type) {
    $options = $extras[self::ID][$unpacker_type] ?? [];
    return new self($options);
  }

  /**
   * Gets the unpack options for this project.
   *
   * @return array
   *   The unpack options array.
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Determines whether dev dependencies should be unpacked.
   *
   * @return bool
   *   TRUE if dev dependencies should be unpacked.
   */
  public function unpackDevDependencies() {
    return $this->options['unpack-dev-dependencies'];
  }

  /**
   * Determines whether patches should be unpacked.
   *
   * @return bool
   *   TRUE if patches should be unpacked.
   */
  public function unpackPatches() {
    return $this->options['unpack-patches'];
  }

  /**
   * Determines whether the self package should be removed.
   *
   * @return bool
   *   TRUE if the self package should be removed.
   */
  public function removeSelf() {
    return $this->options['remove-self'];
  }

}
