<?php

namespace Drupal\cas\Event;

use Drupal\cas\CasPropertyBag;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Allows third-party code to inject user interaction into the flow.
 *
 * After a successful CAS login and validation, some third-party modules may
 * require to get some user input just before local logging in or before
 * registering a new local account.
 *
 * Potential use cases:
 * - There's a new version of site's 'Terms & Conditions' page. On the first
 *   login after the new version has been published, the user should accept the
 *   new terms or he/she cannot login.
 * - There's no local account and the site is configured with 'auto register'. A
 *   third-party module wants to allow the user to login with the local
 *   credentials so that the CAS account and the Drupal account get paired in
 *   the {authmap} table.
 *
 * Third-party modules that want to build this kind of interaction should listen
 * to \Drupal\cas\Service\CasHelper::EVENT_PRE_USER_LOAD_REDIRECT event and set
 * an HTTP redirect response, using self::setRedirectResponse() provided by this
 * class. After fulfilling their logic, they're responsible to complete the
 * process by explicitly calling \Drupal\cas\Service\CasUserManager::login(),
 * showing a status message to user and executing the final redirect.
 */
class CasPreUserLoadRedirectEvent extends Event {

  /**
   * The CAS property bag.
   *
   * @var \Drupal\cas\CasPropertyBag
   */
  protected $casPropertyBag;

  /**
   * The CAS authentication ticket.
   *
   * @var string
   */
  protected $ticket;

  /**
   * Subscribers may trigger an HTTP redirect.
   *
   * @var \Symfony\Component\HttpFoundation\RedirectResponse|null
   */
  protected $redirectResponse;

  /**
   * Constructs a new event instance.
   *
   * @param string $ticket
   *   The CAS authentication ticket.
   * @param \Drupal\cas\CasPropertyBag $cas_property_bag
   *   The CasPropertyBag of the current login cycle.
   */
  public function __construct($ticket, CasPropertyBag $cas_property_bag) {
    $this->ticket = $ticket;
    $this->casPropertyBag = $cas_property_bag;
  }

  /**
   * Returns the CAS property bag.
   *
   * @return \Drupal\cas\CasPropertyBag
   *   The the CAS property bag.
   */
  public function getCasPropertyBag() {
    return $this->casPropertyBag;
  }

  /**
   * Returns the CAS authentication ticket.
   *
   * @return string
   *   The CAS authentication ticket.
   */
  public function getTicket() {
    return $this->ticket;
  }

  /**
   * Sets an HTTP redirect response.
   *
   * Subscribers may decide to trigger a redirect just after the attempt to
   * find a local Drupal user account.
   *
   * @param \Symfony\Component\HttpFoundation\RedirectResponse $redirect_response
   *   The HTTP redirect response to be set along with the event.
   *
   * @return $this
   */
  public function setRedirectResponse(RedirectResponse $redirect_response) {
    $this->redirectResponse = $redirect_response;
    return $this;
  }

  /**
   * Returns the HTTP redirect response.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   The HTTP redirect response or NULL, if none was set.
   */
  public function getRedirectResponse() {
    return $this->redirectResponse;
  }

}
