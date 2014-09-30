<?php

namespace Drupal\cas\Service;

use Drupal\Core\Http\Client;
use GuzzleHttp\Exception\ClientException;
use Drupal\Component\Utility\Urlhelper;

class CasProxyHelper {

  /**
   * @var \Drupal\Core\Http\Client
   */
  protected $httpClient;

  /**
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Constructor.
   *
   * @param Client $http_client
   *   The HTTP Client library.
   * @param CasHelper $cas_helper
   *   The CAS Helper service.
   */
  public function __construct(Client $http_client, CasHelper $cas_helper) {
    $this->httpClient = $http_client;
    $this->casHelper = $cas_helper;
  }

  /**
   * Format a CAS Server proxy ticket request URL.
   *
   * @param string $target_service
   *   The service to be proxied.
   * @return string
   *   The fully formatted URL.
   */
  private function getServerProxyURL($target_service) {
    $url = $this->casHelper->getServerBaseUrl() . 'proxy';
    $params = array();
    $params['pgt'] = $_SESSION['cas_pgt'];
    $params['targetService'] = $target_service;
    return $url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Proxy authenticates to a target service.
   *
   * @param string $target_service
   *   The service to be proxied.
   */
  public function proxyAuthenticate($target_service) {
    if (!($this->casHelper->isProxy() && isset($_SESSION['cas_pgt'])) {
      // We can't perform proxy authentication in this state.
      // @TODO: We should throw some exception here.
    }
    else {
      $url = $this->getServerProxyURL($target_service);
      // Need to actually make the request and parse it.
    }
  }
}
