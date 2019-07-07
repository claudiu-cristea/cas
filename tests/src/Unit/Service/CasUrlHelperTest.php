<?php

namespace Drupal\Tests\cas\Unit\Service;

use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasUrlHelper;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\cas\Service\CasUrlHelper
 * @group cas
 */
class CasUrlHelperTest extends UnitTestCase {

  /**
   * @covers ::handleReturnToParameter
   */
  public function testHandleReturnToParameter() {
    $cas_url_helper = new CasUrlHelper(
      $this->prophesize(CasHelper::class)->reveal()
    );

    $request = new Request(['returnto' => 'node/1']);

    $this->assertFalse($request->query->has('destination'));
    $this->assertSame('node/1', $request->query->get('returnto'));

    $cas_url_helper->handleReturnToParameter($request);

    // Check that the 'returnto' has been copied to 'destination'.
    $this->assertSame('node/1', $request->query->get('destination'));
    $this->assertSame('node/1', $request->query->get('returnto'));
  }

}
