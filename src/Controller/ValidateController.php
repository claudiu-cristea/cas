<?php

namespace Drupal\cas\Controller;

use Drupal\cas\CasLogin;
use Drupal\cas\Exception\CasValidateException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cas\Cas;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ValidateController implements ContainerInjectionInterface {

  /**
   * @var \Drupal\cas\Cas
   */
  protected $cas;

  /**
   * @var \Drupal\cas\CasLogin
   */
  protected $casLogin;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructor.
   *
   * @param Cas $cas
   *   The CAS service.
   * @param CasLogin $cas_login
   *   The service used to log a CAS user into Drupal.
   * @param UrlGeneratorInterface $url_generator
   *   The URL generator.
   */
  public function __construct(Cas $cas, CasLogin $cas_login, RequestStack $request_stack, UrlGeneratorInterface $url_generator) {
    $this->cas = $cas;
    $this->casLogin = $cas_login;
    $this->requestStack = $request_stack;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('cas.cas'), $container->get('cas.login'), $container->get('request_stack'), $container->get('url_generator'));
  }

  /**
   * Handles a request to validate a CAS service ticket.
   */
  public function validate() {
    $request = $this->requestStack->getCurrentRequest();

    // Check if there is a ticket parameter. If there isn't, we could be
    // returning from a gateway request and the user may not be logged into CAS.
    // Just redirect away from here.
    if (!$request->query->get('ticket')) {
      $this->handleReturnToParameter($request);
      return new RedirectResponse($this->urlGenerator->generate('<front>'));
    }

    try {
      // Our CAS service will need to reconstruct the original service URL
      // when validating the ticket. We always know what the base URL for
      // the service URL (it's this page), but there may be some query params
      // attached as well (like a destination param) that we need to pass in
      // as well. So, detach the ticket param, and pass the rest off.
      $params = $request->query->all();
      $ticket = $params['ticket'];
      unset($params['ticket']);
      $username = $this->cas->validateTicket($ticket, $params);
      if ($this->casLogin->loginToDrupal($username)) {
        $this->handleReturnToParameter($request);
        return new RedirectResponse($this->urlGenerator->generate('<front>'));
      }
      else {
        // TODO: Cas Login failed. Redirect somewhere and set a message maybe?
        // Maybe the CasLogin service should throw exceptions instead, and
        // we use that to set the message to the user and redirect back to
        // the homepage.
        return new RedirectResponse($this->urlGenerator->generate('<front>'));
      }
    }
    catch (CasValidateException $e) {
      // Validation failed, redirect to homepage and set message.
      drupal_set_message(t('Error validating user.'), 'error');
      return new RedirectResponse($this->urlGenerator->generate('<front>'));
    }
  }

  /**
   * Converts a "returnto" query param to a "destination" query param.
   *
   * The original service URL for CAS server may contain a "returnto" query
   * parameter that was placed there to redirect a user to specific page after
   * logging in with CAS.
   *
   * Drupal has a built in mechansim for doing this, by instead using a
   * "desintation" parameter in the URL. Anytime there's a RedirectResponse
   * returned, RedirectResponseSubscriber looks for the destination param and
   * will redirect a user there instead.
   *
   * We cannot use this built in method when constructing the service URL,
   * because when we redirect to the CAS server for login, Drupal would see
   * our destination parameter in the URL and redirect there instead of CAS.
   *
   * However, when we redirect the user after a login success / failure,
   * we can then convert it back to a "destination" parameter and let Drupal
   * do it's thing when redirecting.
   *
   * @param Request $request
   *   The Symfony request object.
   */
  private function handleReturnToParameter(Request $request) {
    if ($request->query->has('returnto')) {
      $request->query->set('destination', $request->query->get('returnto'));
      $request->query->remove('returnto');
    }
  }
}
