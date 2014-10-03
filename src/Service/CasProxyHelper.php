<?php

namespace Drupal\cas\Service;

use Drupal\Core\Http\Client;
use GuzzleHttp\Exception\ClientException;
use Drupal\Component\Utility\Urlhelper;
use Drupal\cas\Exception\CasValidateException;
use GuzzleHttp\Cookie\CookieJar;

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
    $params['pgt'] = $_SESSION['cas_pgt']->pgt;
    $params['targetService'] = $target_service;
    return $url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Proxy authenticates to a target service.
   *
   * Returns cookies from the proxied service in a 
   * CookieJar object for use when later accessing resources.
   *
   * @param string $target_service
   *   The service to be proxied.
   * @return mixed
   *   \GuzzleHttp\Cookie\CookieJar if proxy auth success, FALSE otherwise.
   */
  public function proxyAuthenticate($target_service) {
    if (!($this->casHelper->isProxy() && isset($_SESSION['cas_pgt']))) {
      // We can't perform proxy authentication in this state.
      return FALSE;
    }
    else {
      $cas_url = $this->getServerProxyURL($target_service);
      // Need to actually make the request and parse it.
      try {
        $response = $this->httpClient->get($cas_url);
      }
      catch (ClientException $e) {
        return FALSE;
      }
      $proxy_ticket = $this->parseProxyTicket($response->getBody());
      if (!$proxy_ticket) {
        return FALSE;
      }
      $params['ticket'] = $proxy_ticket;
      $service_url = $target_service . "?" . UrlHelper::buildQuery($params);
      $cookieJar = new CookieJar();
      try {
        $data = $this->httpClient->get($service_url, ['cookies' => $cookieJar]);
      }
      catch (ClientException $e) {
        return FALSE;
      }
      return $cookieJar;
    }
  }

  /**
   * Parse proxy ticket from CAS Server response.
   *
   * @param string $xml
   *   XML response from CAS Server.
   * @return mixed
   *   A proxy ticket to be used with the target service, FALSE on failure.
   */
  private function parseProxyTicket($xml) {
    $dom = new \DomDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";
    if ($dom->loadXML($xml) === FALSE) {
      return FALSE;
    }
    $failure_elements = $dom->getElementsByTagName("proxyFailure");
    if ($failure_elements->length > 0) {
      // Something went wrong with proxy ticket validation.
      return FALSE;
    }
    $success_elements = $dom->getElementsByTagName("proxySuccess");
    if ($success_elements->length === 0) {
      // Malformed response from CAS Server.
      return FALSE;
    }
    $success_element = $success_elements->item(0);
    $proxy_ticket = $success_element->getElementsByTagName("proxyTicket");
    if ($proxy_ticket->length === 0) {
      // Malformed ticket.
      return FALSE;
    }
    return $proxy_ticket->item(0)->nodeValue;
  }
}
