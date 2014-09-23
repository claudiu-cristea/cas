<?php

/**
 * @file
 * Contains \Drupal\cas\Form\CASSettings.
 */

namespace Drupal\cas\Form;

use Drupal\Component\Plugin\Factory\FactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CasSettings extends ConfigFormBase {

  /**
   * @var \Drupal\system\Plugin\Condition\RequestPath
   */
  protected $gatewayPaths;

  /**
   * @var \Drupal\system\Plugin\Condition\RequestPath
   */
  protected $forcedLoginPaths;

  /**
   * Constructs a \Drupal\cas\Form\CasSettings object.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param FactoryInterface $plugin_factory
   *   The condition plugin factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FactoryInterface $plugin_factory) {
    parent::__construct($config_factory);
    $this->gatewayPaths = $plugin_factory->createInstance('request_path');
    $this->forcedLoginPaths = $plugin_factory->createInstance('request_path');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.condition')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'cas_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cas.settings');

    $form['server'] = array(
      '#type' => 'details',
      '#title' => $this->t('CAS Server'),
      '#open' => TRUE,
      '#tree' => TRUE,
    );
    $form['server']['version'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Version'),
      '#options' => array(
        '1.0' => $this->t('1.0'),
        '2.0' => $this->t('2.0 or higher'),
        'S1' => $this->t('SAML Version 1.1'),
      ),
      '#default_value' => $config->get('server.version'),
    );
    $form['server']['hostname'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#description' => $this->t('Hostname or IP Address of the CAS server.'),
      '#size' => 30,
      '#default_value' => $config->get('server.hostname'),
    );
    $form['server']['port'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#size' => 5,
      '#description' => $this->t('443 is the standard SSL port. 8443 is the standard non-root port for Tomcat.'),
      '#default_value' => $config->get('server.port'),
    );
    $form['server']['path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('URI'),
      '#description' => $this->t('If CAS is not at the root of the host, include a URI (e.g., /cas).'),
      '#size' => 30,
      '#default_value' => $config->get('server.path'),
    );
    $form['server']['cert'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Certificate Authority PEM Certificate'),
      '#description' => $this->t('The PEM certificate of the Certificate Authority that issued the certificate of the CAS server. If omitted, the certificate authority will not be verified.'),
      '#default_value' => $config->get('server.cert'),
    );


    $form['gateway'] = array(
      '#type' => 'details',
      '#title' => $this->t('Gateweay Feature'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#description' => $this->t(
        'This implements the <a href="@cas-gateway">Gateway feature</a> of the CAS Protocol. ' .
        'When enabled, Drupal will check if an anonymous user is logged into your CAS server before ' .
        'serving a page request. If they have an active CAS session, they will be automatically ' .
        'logged into the Drupal site. This is done by quickly redirecting them to the CAS server to perform the ' .
        'active session check, and then redirecting them back to page they initially requested.',
        array('@cas-gateway' => 'https://wiki.jasig.org/display/CAS/gateway')
      ),
    );
    $form['gateway']['check_frequency'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Check Frequency'),
      '#default_value' => $config->get('gateway.check_frequency'),
      '#options' => array(
        CAS_CHECK_NEVER => 'Disable gateway feature',
        CAS_CHECK_ONCE => 'Once per browser session',
        CAS_CHECK_ALWAYS => 'Every page load (not recommended)',
      ),
    );
    $this->gatewayPaths->setConfiguration($config->get('gateway.paths'));
    $form['gateway']['paths'] = $this->gatewayPaths->buildConfigurationForm(array(), $form_state);

    $form['forced_login'] = array(
      '#type' => 'details',
      '#title' => $this->t('Forced Login'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#description' => $this->t(
        'Anonymous users will be forced to login through CAS when enabled. ' .
        'This differs from the "gateway feature" in that it will REQUIRE that a user be logged in to their CAS ' .
        'account, instead of just checking if they already are.'
      ),
    );
    $form['forced_login']['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('When enabled, every path will force a CAS login, unless specific pages are listed.'),
      '#default_value' => $config->get('forced_login.enabled'),
    );
    $this->forcedLoginPaths->setConfiguration($config->get('forced_login.paths'));
    $form['forced_login']['paths'] = $this->forcedLoginPaths->buildConfigurationForm(array(), $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $condition_values = new FormState(array(
      'values' => &$form_state['values']['gateway']['paths'],
    ));
    $this->gatewayPaths->validateConfigurationForm($form, $condition_values);

    $condition_values = new FormState(array(
      'values' => &$form_state['values']['forced_login']['paths'],
    ));
    $this->forcedLoginPaths->validateConfigurationForm($form, $condition_values);
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('cas.settings');

    $server_data = $form_state['values']['server'];
    $config
      ->set('server.version', $server_data['version'])
      ->set('server.hostname', $server_data['hostname'])
      ->set('server.port', $server_data['port'])
      ->set('server.path', $server_data['path'])
      ->set('server.cert', $server_data['cert']);

    $gateway_data = $form_state['values']['gateway'];
    $condition_values = new FormState(array(
      'values' => &$gateway_data['paths'],
    ));
    $this->gatewayPaths->submitConfigurationForm($form, $condition_values);
    $config
      ->set('gateway.check_frequency', $gateway_data['check_frequency'])
      ->set('gateway.paths', $this->gatewayPaths->getConfiguration());

    $forced_login_data = $form_state['values']['forced_login'];
    $condition_values = new FormState(array(
      'values' => &$forced_login_data['paths'],
    ));
    $this->forcedLoginPaths->submitConfigurationForm($form, $condition_values);
    $config
      ->set('forced_login.enabled', $forced_login_data['enabled'])
      ->set('forced_login.paths', $this->forcedLoginPaths->getConfiguration());

    $config->save();
    parent::submitForm($form, $form_state);
  }
}
