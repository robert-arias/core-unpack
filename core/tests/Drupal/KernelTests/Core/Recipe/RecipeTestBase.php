<?php

namespace Drupal\KernelTests\Core\Recipe;

use Drupal\KernelTests\KernelTestBase;
use org\bovigo\vfs\vfsStream;

/**
 * Adds the recipes fixtures directory to vfs and installs required modules.
 */
class RecipeTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem() {
    parent::setUpFilesystem();
    // Create a recipe directory off root for simpler test messages.
    $this->vfsRoot->addChild(vfsStream::newDirectory('recipes'));
    vfsStream::copyFromFileSystem(__DIR__ . '/../../../../fixtures/recipes', $this->vfsRoot->getChild('recipes'));
  }

}
