<?php

namespace Drupal\cas\Service;

use Drupal\Core\Http\Client;
use GuzzleHttp\Exception\ClientException;
use Drupal\Component\Utility\Urlhelper;
use GuzzleHttp\Cookie\CookieJar;
use Drupal\cas\Exception\CasProxyException;

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
   *
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
   *
   * @return \GuzzleHttp\Cookie\CookieJar
   *   A CookieJar object (array storage) containing cookies from the
   *   proxied service.
   */
  public function proxyAuthenticate($target_service) {
    // Check to see if we have proxied this application already.
    if (isset($_SESSION['cas_proxy_helper'][$target_service])) {
      $cookies = array();
      foreach($_SESSION['cas_proxy_helper'][$target_service] as $cookie) {
        $cookies[$cookie['Name']] = $cookie['Value'];
      }
      $domain = $cookie['Domain'];
      $jar = CookieJar::fromArray($cookies, $domain);
      return $jar;
    }

    if (!($this->casHelper->isProxy() && isset($_SESSION['cas_pgt']))) {
      // We can't perform proxy authentication in this state.
      throw new CasProxyException("Session state not sufficient for proxying.");
    }
    else {
      $cas_url = $this->getServerProxyURL($target_service);
      // Need to actually make the request and parse it.
      try {
        $response = $this->httpClient->get($cas_url);
      }
      catch (ClientException $e) {
        throw new CasProxyException($e->getMessage());
      }
      $proxy_ticket = $this->parseProxyTicket($response->getBody());
      $params['ticket'] = $proxy_ticket;
      $service_url = $target_service . "?" . UrlHelper::buildQuery($params);
      $cookie_jar = new CookieJar();
      try {
        $data = $this->httpClient->get($service_url, ['cookies' => $cookie_jar]);
      }
      catch (ClientException $e) {
        throw new CasProxyException($e->getMessage());
      }
      // Set the jar in session storage for later reuse.
      $_SESSION['cas_proxy_helper'][$target_service] = $cookie_jar->toArray();
      return $cookie_jar;
    }
  }

  /**
   * Parse proxy ticket from CAS Server response.
   *
   * @param string $xml
   *   XML response from CAS Server.
   *
   * @return mixed
   *   A proxy ticket to be used with the target service, FALSE on failure.
   */
  private function parseProxyTicket($xml) {
    $dom = new \DomDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";
    if (@$dom->loadXML($xml) === FALSE) {
      throw new CasProxyException("CAS Server returned non-XML response.");
    }
    $failure_elements = $dom->getElementsByTagName("proxyFailure");
    if ($failure_elements->length > 0) {
      // Something went wrong with proxy ticket validation.
      throw new CasProxyException("CAS Server rejected proxy request.");
    }
    $success_elements = $dom->getElementsByTagName("proxySuccess");
    if ($success_elements->length === 0) {
      // Malformed response from CAS Server.
      throw new CasProxyException("CAS Server returned malformed response.");
    }
    $success_element = $success_elements->item(0);
    $proxy_ticket = $success_element->getElementsByTagName("proxyTicket");
    if ($proxy_ticket->length === 0) {
      // Malformed ticket.
      throw new CasProxyException("CAS Server provided invalid or malformed ticket.");
    }
    return $proxy_ticket->item(0)->nodeValue;
  }
}
