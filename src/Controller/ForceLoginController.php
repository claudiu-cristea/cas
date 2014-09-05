<?php

namespace Drupal\cas\Controller;

use Drupal\cas\CasClient;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ForceLoginController implements ContainerInjectionInterface {

  /**
   * @var \Drupal\cas\CasClient
   */
  protected $casClient;

  /**
   * Constructor.
   *
   * @param CasClient $cas_client
   *   The configured phpCAS client.
   */
  public function __construct(CasClient $cas_client) {
    $this->casClient = $cas_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('cas.client'));
  }

  /**
   * Handles a page request for our forced login route.
   */
  public function forceLogin() {
    $this->casClient->configureClient();
    \phpCAS::forceAuthentication();

    // TODO. It's possible that the user is already logged in. If so, throw
    // an exception.
    // It's also possible that phpCAS logged a user in already and set a
    // session var containing their username. Maybe we should just unset it?
  }
}
