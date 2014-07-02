<?php

namespace Drupal\cas\Controller;

use Drupal\Core\Controller\ControllerBase;

class ForcedLoginController extends ControllerBase {

  /**
   * Handles a page request for our forced login route.
   *
   * Note that we have an event subscriber that listens for this
   * same route and will intervene, so this controller will never
   * actually be hit. But if it does for some reason, we just
   * recover by redirecting to the homepage.
   */
  public function forcedLogin() {
    return $this->redirect('<front>');
  }

}
