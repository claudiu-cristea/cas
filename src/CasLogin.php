<?php

namespace Drupal\cas;

use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CasLogin {
  use UrlGeneratorTrait;

  /**
   * @var \Drupal\cas\CasClient
   */
  protected $casClient;

  /**
   * Constructor.
   *
   * @param CasClient $cas_client
   *   The phpCAS client library.
   */
  public function __construct(CasClient $cas_client, UrlGeneratorInterface $url_generator) {
    $this->casClient = $cas_client;
    $this->urlGenerator = $url_generator;
  }

  /**
   * Attempts to log the authenticated CAS user into Drupal.
   *
   * This method should be used to login a user after they have successfully
   * authenticated with the CAS server. The phpCAS library will have set a
   * session cookie containing their username.
   */
  public function loginToDrupal() {
    $this->casClient->configureClient();

    // Make sure the user is actually authenticated with CAS.
    if (!\phpCAS::isSessionAuthenticated()) {
      throw \Exception("User is NOT authenticated with CAS, but should be.");
    }

    $user = user_load_by_name(\phpCAS::getUser());
    if (!$user) {
      // No user, need to register them if possible, otherwise set message
      // indicating they cannot log in because their account does not exist.
      return new RedirectResponse($this->url('<front>'));
    }
    else {
      user_login_finalize($user);

      // Redirect user to the homepage.
      // TODO: We may want to make this after-login route configurable.
      // At the very least, we need to redirect to the page they were just on.
      return new RedirectResponse($this->url('<front>'));
    }
  }
}
