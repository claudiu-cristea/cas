<?php

namespace Drupal\cas\Service;

use Drupal\cas\Exception\CasValidateException;
use Drupal\Core\Http\Client;
use GuzzleHttp\Exception\ClientException;

class CasValidator {

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
   * Validate the service ticket parameter present in the request.
   *
   * This method will return the username of the user if valid, and raise an
   * exception if the ticket is not found or not valid.
   *
   * @param string $version
   *   The protocol version of the CAS server.
   * @param string $ticket
   *   The CAS authentication ticket to validate.
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   */
  public function validateTicket($version, $ticket, $service_params = array()) {
    try {
      $validate_url = $this->casHelper->getServerValidateUrl($ticket, $service_params);
      $options = array();
      $cert = $this->casHelper->getCertificateAuthorityPem();
      if (!empty($cert)) {
        $options['verify'] = $cert;
      }
      else {
        $options['verify'] = FALSE;
      }
      $response = $this->httpClient->get($validate_url, $options);
    }
    catch (ClientException $e) {
      throw new CasValidateException("Error with request to validate ticket: " . $e->getMessage());
    }

    $response_data = $response->getBody()->__toString();
    switch ($version) {
      case "1.0":
        return $this->validateVersion1($response_data);

      case "2.0":
        return $this->validateVersion2($response_data);
    }
  }

  /**
   * Validation of a service ticket for Verison 1 of the CAS protocol.
   *
   * @param string $data
   *   The raw validation response data from CAS server.
   */
  private function validateVersion1($data) {
    if (preg_match('/^no\n/', $data)) {
      throw new CasValidateException("Ticket did not pass validation.");
    }
    elseif (!preg_match('/^yes\n/', $data)) {
      throw new CasValidateException("Malformed response from CAS server.");
    }

    // Ticket is valid, need to extract the username.
    $arr = preg_split('/\n/', $data);
    return array('username' => trim($arr[1]));
  }

  /**
   * Validation of a service ticket for Verison 2 of the CAS protocol.
   *
   * @param string $data
   *   The raw validation response data from CAS server.
   * @param bool $proxy_client
   *   TRUE if the client is to be initialized as a proxy, FALSE otherwise.
   */
  private function validateVersion2($data) {
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";

    if ($dom->loadXML($data) === FALSE) {
      throw new CasValidateException("XML from CAS server is not valid.");
    }

    $failure_elements = $dom->getElementsByTagName('authenticationFailure');
    if ($failure_elements->length > 0) {
      // Failed validation, extract the message and toss exception.
      $failure_element = $failure_elements->item(0);
      $error_code = $failure_element->getAttribute('code');
      $error_msg = $failure_element->nodeValue;
      throw new CasValidateException("Error Code " . $error_code . ": " . $error_msg);
    }

    $success_elements = $dom->getElementsByTagName("authenticationSuccess");
    if ($success_elements->length === 0) {
      // All reponses should have either an authenticationFailure
      // or authenticationSuccess node.
      throw new CasValidateException("XML from CAS server is not valid.");
    }

    // There should only be one success element, grab it and extract username.
    $success_element = $success_elements->item(0);
    $user_element = $success_element->getElementsByTagName("user");
    if ($user_element->length == 0) {
      throw new CasValidateException("No user found in ticket validation response.");
    }

    $info = array();
    if ($this->casHelper->isProxy()) {
      // Extract the PGTIOU from the XML. Place it into $info['proxy'].
      $pgt_element = $success_element->getElementsByTagName("proxyGrantingTicket");
      if ($pgt_element->length == 0) {
        throw new CasValidateException("Proxy intialized, but no PGTIOU provided in response.");
      }
      $info['pgt'] = $pgt_element->item(0)->nodeValue;
    }
    else {
      $info['pgt'] = NULL;
    }
    $info['username'] = $user_element->item(0)->nodeValue;
    return $info;
  }
}
