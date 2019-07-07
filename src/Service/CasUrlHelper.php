<?php

namespace Drupal\cas\Service;

use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides URL reusable methods.
 */
class CasUrlHelper {

  /**
   * The CAS helper service.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Constructs a new 'cas.url_helper' service instance.
   *
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS helper service.
   */
  public function __construct(CasHelper $cas_helper) {
    $this->casHelper = $cas_helper;
  }

  /**
   * Converts a "returnto" query param to a "destination" query param.
   *
   * The original service URL for CAS server may contain a "returnto" query
   * parameter that was placed there to redirect a user to specific page after
   * logging in with CAS.
   *
   * Drupal has a built in mechanism for doing this, by instead using a
   * "destination" parameter in the URL. Anytime there's a RedirectResponse
   * returned, RedirectResponseSubscriber looks for the destination param and
   * will redirect a user there instead.
   *
   * We cannot use this built in method when constructing the service URL,
   * because when we redirect to the CAS server for login, Drupal would see
   * our destination parameter in the URL and redirect there instead of CAS.
   *
   * However, when we redirect the user after a login success/failure, we can
   * then convert it back to a "destination" parameter and let Drupal do it's
   * thing when redirecting.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Symfony request object.
   */
  public function handleReturnToParameter(Request $request) {
    if ($request->query->has('returnto')) {
      $this->casHelper->log(LogLevel::DEBUG, "Converting query parameter 'returnto' to 'destination'.");
      $request->query->set('destination', $request->query->get('returnto'));
    }
  }

}
