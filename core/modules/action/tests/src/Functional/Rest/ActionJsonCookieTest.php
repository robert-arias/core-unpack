<?php

declare(strict_types=1);

namespace Drupal\Tests\action\Functional\Rest;

use Drupal\Tests\rest\Functional\CookieResourceTestTrait;

/**
 * @group action
 * @group legacy
 */
class ActionJsonCookieTest extends ActionResourceTestBase {

  use CookieResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'cookie';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
