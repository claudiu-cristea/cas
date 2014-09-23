<?php

namespace Drupal\cas;

use Drupal\cas\Exception\CasValidateException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Cas {

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * @var \Drupal\Core\Http\Client
   */
  protected $httpClient;

  /**
   * Constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param Client $http_client
   *   The HTTP Client library.
   */
  public function __construct(ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator, Client $http_client) {
    $this->configFactory = $config_factory;
    $this->urlGenerator = $url_generator;
    $this->httpClient = $http_client;

    $this->settings = $config_factory->get('cas.settings');
  }

  /**
   * Return the login URL to the CAS server.
   *
   * @param bool $gateway
   *   TRUE if this should be a gateway request.
   */
  public function getLoginUrl($gateway = FALSE) {
    $url = $this->getBaseUrl() . 'login?service=' . $this->getServiceUrl();
    if ($gateway) {
      $url .= '&gateway=true';
    }
    return $url;
  }

  /**
   * Validate the service ticket parameter present in the request.
   *
   * This method will return the username of the user if valid, and raise an
   * exception if the ticket is not found or not valid.
   *
   * @param Request $request
   *   The request that contains the validation data.
   */
  public function validateTicket(Request $request) {
    switch ($this->settings->get('server.version')) {
      case "1.0":
        return $this->validateVersion1($request);

      case "2.0":
        return $this->validateVersion2($request);
    }
  }

  /**
   * Validation of a service ticket for Verison 1 of the CAS protocol.
   *
   * @param Request $request
   *   The request that contains the validation data.
   */
  private function validateVersion1(Request $request) {
    $ticket = $request->get('ticket');
    $validate_url = $this->getBaseUrl() . 'validate';
    try {
      $response = $this->httpClient->get($validate_url, array(
        'query' => array(
          'service' => $this->getServiceUrl(),
          'ticket' => $ticket,
        ),
      ));
      $body = $response->getBody()->__toString();

      // Split the response data on new line character, but we don't know what
      // the new line character could be.
      $data = explode("\n", str_replace("\n\n", "\n", str_replace("\r", "\n", $body)));
      if ($data[0] == 'yes' && count($data) > 1) {
        $username = $data[1];
        return $username;
      }
      else {
        throw new CasValidateException("Invalid response from CAS server.");
      }
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
      $code = $response->getStatusCode();
      if ($code == 422) {
        throw new CasValidateException("Invalid response from CAS server.");
      }
    }
  }

  /**
   * Validation of a service ticket for Verison 2 of the CAS protocol.
   *
   * @param Request $request
   *   The request that contains the validation data.
   */
  private function validateVersion2(Request $request) {
    // TODO.
  }

  /**
   * Construct the base URL to the CAS server.
   *
   * @return string
   *   The base URL.
   */
  private function getBaseUrl() {
    $server_path = $this->settings->get('server.path');
    $server_base = $this->settings->get('server.hostname');
    return sprintf('https://%s%s', $server_base, $server_path);
  }

  /**
   * Return the service URL.
   */
  private function getServiceUrl() {
    return $this->urlGenerator->generate('cas.service', array(), TRUE);
  }
}
