<?php

namespace Drupal\cas;

use Drupal\cas\Exception\CasValidateException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Drupal\Component\Utility\UrlHelper;

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
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   * @param bool $gateway
   *   TRUE if this should be a gateway request.
   */
  public function getServerLoginUrl($service_params = array(), $gateway = FALSE) {
    $login_url = $this->getBaseServerUrl() . 'login';

    $params = array();
    if ($gateway) {
      $params['gateway'] = TRUE;
    }
    $params['service'] = $this->getServiceUrl($service_params);

    return $login_url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Get the validate URL for the CAS server.
   *
   * @return string
   *   The validation URL.
   */
  public function getServerValidateUrl() {
    return $this->getBaseServerUrl() . 'validate';
  }

  /**
   * Validate the service ticket parameter present in the request.
   *
   * This method will return the username of the user if valid, and raise an
   * exception if the ticket is not found or not valid.
   *
   * @param string $ticket
   *   The CAS authentication ticket to validate.
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   */
  public function validateTicket($ticket, $service_params = array()) {
    switch ($this->settings->get('server.version')) {
      case "1.0":
        return $this->validateVersion1($ticket, $service_params);

      case "2.0":
        return $this->validateVersion2($ticket, $service_params);
    }
  }

  /**
   * Validation of a service ticket for Verison 1 of the CAS protocol.
   *
   * @param string $ticket
   *   The CAS authentication ticket to validate.
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   */
  private function validateVersion1($ticket, $service_params) {
    try {
      $response = $this->httpClient->get($this->getServerValidateUrl(), array(
        'query' => array(
          'service' => $this->getServiceUrl($service_params),
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
   * @param string $ticket
   *   The CAS authentication ticket to validate.
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   */
  private function validateVersion2($ticket, $service_params) {
    // TODO.
  }

  /**
   * Construct the base URL to the CAS server.
   *
   * @return string
   *   The base URL.
   */
  private function getBaseServerUrl() {
    // TODO, make sure we always end with a slash.
    $server_path = $this->settings->get('server.path');
    $server_base = $this->settings->get('server.hostname');
    return sprintf('https://%s%s', $server_base, $server_path);
  }

  /**
   * Return the service URL.
   *
   * @param array $service_params
   *   An array of query string parameters to append to the service URL.
   */
  private function getServiceUrl($service_params = array()) {
    return $this->urlGenerator->generate('cas.validate', $service_params, TRUE);
  }
}
