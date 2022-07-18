<?php

namespace Drupal\Core\Recipe;

use Drupal\Core\Config\StorageInterface;

/**
 * Wraps a config storage to allow recipe provided configuration to override it.
 *
 * @internal
 *   This API is experimental.
 */
final class RecipeOverrideConfigStorage implements StorageInterface {

  /**
   * @param \Drupal\Core\Config\StorageInterface $recipeStorage
   *   The recipe's configuration storage.
   * @param \Drupal\Core\Config\StorageInterface $wrappedStorage
   *   The storage to override.
   * @param string $collection
   *   (optional) The collection to store configuration in. Defaults to the
   *   default collection.
   */
  public function __construct(protected readonly StorageInterface $recipeStorage, protected readonly StorageInterface $wrappedStorage, protected readonly string $collection = StorageInterface::DEFAULT_COLLECTION) {
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    return $this->wrappedStorage->exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    if ($this->wrappedStorage->exists($name) && $this->recipeStorage->exists($name)) {
      return $this->recipeStorage->read($name);
    }
    return $this->wrappedStorage->read($name);
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $data = $this->wrappedStorage->readMultiple($names);
    foreach ($data as $name => $value) {
      if ($this->recipeStorage->exists($name)) {
        $data[$name] = $this->recipeStorage->read($name);
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    throw new \BadMethodCallException();
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    throw new \BadMethodCallException();
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    throw new \BadMethodCallException();
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $this->wrappedStorage->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $this->wrappedStorage->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    return $this->wrappedStorage->listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    throw new \BadMethodCallException();
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      $this->recipeStorage->createCollection($collection),
      $this->wrappedStorage->createCollection($collection),
      $collection
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    return $this->wrappedStorage->getAllCollectionNames();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->collection;
  }

}
