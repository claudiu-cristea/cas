<?php

namespace Drupal\cas\Controller;

use Drupal\cas\Service\CasHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ForceLoginController.
 */
class ForceLoginController implements ContainerInjectionInterface {
  /**
   * The cas helper to get config settings from.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Used to get query string parameters from the request.
   *
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * Constructor.
   *
   * @param CasHelper $cas_helper
   *   The CAS helper service.
   * @param RequestStack $request_stack
   *   Symfony request stack.
   */
  public function __construct(CasHelper $cas_helper, RequestStack $request_stack) {
    $this->casHelper = $cas_helper;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('cas.helper'), $container->get('request_stack'));
  }

  /**
   * Handles a page request for our forced login route.
   */
  public function forceLogin() {
    // TODO: What if CAS is not configured? need to handle that case.

    $this->casHelper->log('CAS forced login controller hit, redirecting to CAS server for forced authentication.');

    // TODO: We're currently passing ALL existing query string parameters to
    // the service URL, but why? We only need to check if there's a returnto
    // parameter and pass that one along.
    $service_url_query_params = $this->requestStack->getCurrentRequest()->query->all();
    return $this->casHelper->createForcedRedirectResponse($service_url_query_params);
  }

}
