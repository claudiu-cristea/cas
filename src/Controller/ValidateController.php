<?php

namespace Drupal\cas\Controller;

use Drupal\cas\CasLogin;
use Drupal\cas\Exception\CasValidateException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cas\Cas;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    // In that case, we just redirect back to where they were (if destination
    // was set in service parameter, or just to the homepage).
    if (!$request->get('ticket')) {
      // TODO: Check if destination parameter set, and redirect there instead.
      return new RedirectResponse($this->urlGenerator->generate('<front>'));
    }

    try {
      $username = $this->cas->validateTicket($this->requestStack->getCurrentRequest());
      if ($this->casLogin->loginToDrupal($username)) {
        // TODO: Customize after-login path?
        return new RedirectResponse($this->urlGenerator->generate('<front>'));
      }
      else {
        // TODO: Customize login failure path?
        return new RedirectResponse($this->urlGenerator->generate('<front>'));
      }
    }
    catch (CasValidateException $e) {
      // Validation failed, redirect to homepage and set message.
      drupal_set_message(t('Error validating user.'), 'error');
      return new RedirectResponse($this->urlGenerator->generate('<front>'));
    }
  }
}
