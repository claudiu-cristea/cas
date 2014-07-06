<?php

/**
 * @file
 * Contains \Drupal\cas\Form\CASSettings.
 */

namespace Drupal\cas\Form;

use Drupal\Core\Form\ConfigFormBase;

class CASSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'cas_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $account = \Drupal::currentUser();
    $config = $this->config('cas.settings');
    $form['server'] = array(
      '#type' => 'details',
      '#title' => $this->t('CAS Server'),
      '#open' => TRUE,
    );
    $form['server']['version'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Version'),
      '#description' => $this->t(''),
      '#options' => array(
        '1.0' => $this->t('1.0'),
        '2.0' => $this->t('2.0 or higher'),
        'S1' => $this->t('SAML Version 1.1'),
      ),
      '#default_value' => $config->get('version'),
    );
    $form['server']['server'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Hostname'),
      '#description' => $this->t('Hostname or IP Address of the CAS server.'),
      '#size' => 30,
      '#default_value' => $config->get('server'),
    );
    $form['server']['port'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#size' => 5,
      '#description' => $this->t('443 is the standard SSL port. 8443 is the standard non-root port for Tomcat.'),
      '#default_value' => $config->get('port'),
    );
    $form['server']['uri'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('URI'),
      '#description' => $this->t('If CAS is not at the root of the host, include a URI (e.g., /cas).'),
      '#size' => 30,
      '#default_value' => $config->get('uri'),
    );
    $form['server']['cert'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Certificate Authority PEM Certificate'),
      '#description' => $this->t('The PEM certificate of the Certificate Authority that issued the certificate of the CAS server. If omitted, the certificate authority will not be verified.'),
      '#default_value' => $config->get('cert'),
    );
    $form['server']['debugfile'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('CAS debugging output file'),
      '#default_value' => $config->get('debugfile'),
      '#maxlength' => 255,
      '#description' => "<p>" . $this->t("A file system path and filename where the CAS debug log will be written. May be either a full path or a path relative to the Drupal installation root. The directory and file must be writable by Drupal.") . "</p><p>" . $this->t("Leave blank to disable logging.") . "</p> <p><em>" . $this->t("Debugging should not be enabled on production systems.") . "</em></p>",
    );
    $form['server']['force_https'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Force HTTPS'),
      '#description' => $this->t(''),
      '#options' => array(TRUE => $this->t('Yes'), FALSE => $this->t('No')),
      '#default_value' => $config->get('force_https'),
    );

    $form['login'] = array(
      '#type' => 'details',
      '#title' => $this->t('Login form'),
      '#open' => FALSE,
    );
    $form['login']['login_form'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Add CAS link to login forms'),
      '#default_value' => $config->get('login_form'),
      '#options' => array(
        CAS_NO_LINK => $this->t('Do not add link to login forms'),
        CAS_ADD_LINK => $this->t('Add link to login forms'),
        CAS_MAKE_DEFAULT => $this->t('Make CAS login default on login forms'),
      ),
    );
    $form['login']['login_invite'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('CAS login invitation'),
      '#default_value' => $config->get('login_invite'),
      '#description' => $this->t('Message users will see to invite them to log in with CAS credentials.'),
    );
    $form['login']['login_drupal_invite'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Drupal login invitation'),
      '#default_value' => $config->get('login_drupal_invite'),
      '#description' => $this->t('Message users will see to invite them to log in with Drupal credentials.'),
    );
    $form['login']['login_redir_message'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Redirection notification message'),
      '#default_value' => $config->get('login_redir_message'),
      '#description' => $this->t('Message users see at the top of the CAS login form to warn them that they are being redirected to the CAS server.'),
    );
    $form['login']['login_message'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Successful login message'),
      '#default_value' => $config->get('login_message'),
      '#description' => $this->t("Message displayed when a user logs in successfully. <em>%cas_username</em> will be replaced with the user's name."),
    );

    $form['account'] = array(
      '#type' => 'details',
      '#title' => $this->t('User Accounts'),
      '#open' => FALSE,
    );
    $form['account']['user_register'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create Drupal accounts'),
      '#default_value' => $config->get('user_register'),
      '#description' => $this->t('Whether a Drupal account is automatically created the first time a CAS user logs into the site. If disabled, you will need to pre-register Drupal accounts for authorized users.'),
    );
    $form['account']['domain'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('E-mail address'),
      '#field_prefix' => $this->t('username') . '@',
      '#default_value' => $config->get('domain'),
      '#size' => 30,
      '#maxlength' => 255,
      '#description' => $this->t("If provided, automatically generate each new user's e-mail address. If omitted, the e-mail field will not be populated. Other modules may be used to populate the e-mail field from CAS attributes or LDAP servers."),
    );

    $options = array();
    $roles = user_roles(TRUE);
    foreach ($roles as $role) {
      $options[$role->id] = $role->label;
    }
    $checkbox_authenticated = array(
      '#type' => 'checkbox',
      '#title' => $options[DRUPAL_AUTHENTICATED_RID],
      '#default_value' => TRUE,
      '#disabled' => TRUE,
    );
    unset($options[DRUPAL_AUTHENTICATED_RID]);
    $form['account']['auto_assigned_role'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles'),
      '#description' => $this->t('The selected roles will be automatically assigned to each CAS user on login. Use this to automatically give CAS users additional privileges or to identify CAS users to other modules.'),
      '#default_value' => $config->get('auto_assigned_role'),
      '#options' => $options,
      '#access' => $account->hasPermission('administer permissions'),
      DRUPAL_AUTHENTICATED_RID => $checkbox_authenticated,
    );
    $form['account']['hide_email'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Users cannot change email address'),
      '#default_value' => $config->get('hide_email'),
      '#description' => $this->t('Hide email address field on the edit user form.'),
    );
    $form['account']['hide_password'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Users cannot change password'),
      '#default_value' => $config->get('hide_password'),
      '#description' => $this->t('Hide password field on the edit user form. This also removes the requirement to enter your current password before changing your e-mail address.'),
    );

    $form['pages'] = array(
      '#type' => 'details',
      '#title' => $this->t('Redirection'),
      '#open' => FALSE,
    );
    $form['pages']['check_frequency'] = array(
      '#type' => 'select',
      '#title' => $this->t('Check with the CAS server to see if the user is already logged in?'),
      '#default_value' => $config->get('check_frequency'),
      '#options' => array(
        CAS_CHECK_NEVER => 'Never',
        CAS_CHECK_ONCE => 'Once, but not again until login',
        CAS_CHECK_ALWAYS => 'Always',
      ),
      '#description' => $this->t('This implmements the <a href="@cas-gateway">Gateway feature</a> of the CAS Protocol. Enabling this may prevent logging out of Drupal without also logging out of CAS.', array('@cas-gateway' => 'https://wiki.jasig.org/display/CAS/gateway')),
    );
    $form['pages']['access'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Require CAS login for'),
      '#default_value' => $config->get('access'),
      '#options' => array(
        CAS_REQUIRE_SPECIFIC => $this->t('specific pages'),
        CAS_REQUIRE_ALL_EXCEPT => $this->t('all pages except specific pages'),
      ),
    );
    $form['pages']['pages'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Specific pages'),
      '#default_value' => $config->get('pages'),
      '#cols' => 40,
      '#rows' => 4,
      '#description' => $this->t("Enter one page per line as Drupal paths. The '*' character is a wildcard. Example paths are '<em>blog</em>' for the blog page and <em>blog/*</em> for every personal blog. '<em>&lt;front&gt;</em> is the front page."),
    );
    $form['pages']['exclude'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Excluded Pages'),
      '#default_value' => $config->get('exclude'),
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t("Indicates which pages will be ignored (no login checks). The '*' character is a wildcard. Example paths are '<em>blog</em>' for the blog page and <em>blog/*</em> for every personal blog. '<em>&lt;front&gt;</em> is the front page."),
    );

    $form['destinations'] = array(
      '#type' => 'details',
      '#title' => $this->t('Login/Logout destinations'),
      '#open' => FALSE,
    );
    $form['destinations']['first_login_destination'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Initial login destination'),
      '#default_value' => $config->get('first_login_destination'),
      '#size' => 40,
      '#maxlength' => 255,
      '#description' => $this->t("Drupal path or URL. Enter a destination if you want the user to be redirected to this page on their first CAS login. An example path is <em>blog</em> for the blog page, <em>&lt;front&gt;</em> for the front page, or <em>user</em> for the user's page."),
    );
    $form['destinations']['logout_destination'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Logout destination'),
      '#default_value' => $config->get('logout_destination'),
      '#size' => 40,
      '#maxlength' => 255,
      '#description' => $this->t("Drupal path or URL. Enter a destination if you want a user to be directed to this page after logging out of CAS, or leave blank to direct users back to the previous page. An example path is <em>blog</em> for the blog page or <em>&lt;front&gt;</em> for the front page."),
    );
    $form['destinations']['changePasswordURL'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Change password URL'),
      '#default_value' => $config->get('changePasswordURL'),
      '#maxlength' => 255,
      '#description' => $this->t('The URL users should use for changing their password. Leave blank to use the standard Drupal page.'),
    );
    $form['destinations']['registerURL'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Registration URL'),
      '#default_value' => $config->get('registerURL'),
      '#maxlength' => 255,
      '#description' => $this->t('The URL users should use for changing registering. Leave blank to use the standard Drupal page.'),
    );

    $form['advanced'] = array(
      '#type' => 'details',
      '#title' => $this->t('Miscellaneous & Experimental Settings'),
      '#open' => FALSE,
    );
    $form['advanced']['proxy'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Initialize CAS as proxy'),
      '#default_value' => $config->get('proxy'),
      '#description' => $this->t('Initialize phpCAS as a proxy rather than a client. The proxy ticket returned by the CAS server allows access to external services as the CAS user.'),
    );
    $form['advanced']['pgtformat'] = array(
      '#type' => 'radios',
      '#title' => $this->t('CAS PGT storage file format'),
      '#default_value' => $config->get('pgtformat'),
      '#options' => array('plain' => $this->t('Plain Text'), 'xml' => t('XML')),
      // '#after_build' => array('cas_pgtformat_version_check'),
    );
    $form['advanced']['pgtpath'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('CAS PGT storage path'),
      '#default_value' => $config->get('pgtpath'),
      '#maxlength' => 255,
      '#description' => $this->t("Only needed if 'Use CAS proxy initializer' is configured. Leave empty for default."),
    );
    $form['advanced']['proxy_list'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('CAS proxy list'),
      '#description' => $this->t("If CAS client could be proxied, indicate each proxy server absolute url per line. If not provided, phpCAS will exclude by default all tickets provided by proxy. Each proxy server url could be a plain url or a regular expression. IMPORTANT: regular expression must be a slash. For example : https://proxy.example.com/ AND/OR regular expression : /^https:\/\/app[0-9]\.example\.com\/rest\//."),
      '#default_value' => $config->get('proxy_list'),
      // '#after_build' => array('cas_proxy_list_version_check'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->config('cas.settings')
          ->set('version', $form_state['values']['version'])
          ->set('server', $form_state['values']['server'])
          ->set('port', $form_state['values']['port'])
          ->set('uri', $form_state['values']['uri'])
          ->set('cert', $form_state['values']['cert'])
          ->set('debugfile', $form_state['values']['debugfile'])
          ->set('force_https', $form_state['values']['force_https'])
          ->set('login_form', $form_state['values']['login_form'])
          ->set('login_invite', $form_state['values']['login_invite'])
          ->set('login_drupal_invite', $form_state['values']['login_drupal_invite'])
          ->set('login_redir_message', $form_state['values']['login_redir_message'])
          ->set('login_message', $form_state['values']['login_message'])
          ->set('proxy', $form_state['values']['proxy'])
          ->set('pgtformat', $form_state['values']['pgtformat'])
          ->set('pgtpath', $form_state['values']['pgtpath'])
          ->set('proxy_list', $form_state['values']['proxy_list'])
          ->set('check_frequency', $form_state['values']['check_frequency'])
          ->set('access', $form_state['values']['access'])
          ->set('pages', $form_state['values']['pages'])
          ->set('exclude', $form_state['values']['exclude'])
          ->set('first_login_destination', $form_state['values']['first_login_destination'])
          ->set('logout_destination', $form_state['values']['logout_destination'])
          ->set('changePasswordURL', $form_state['values']['changePasswordURL'])
          ->set('registerURL', $form_state['values']['registerURL'])
          ->set('user_register', $form_state['values']['user_register'])
          ->set('domain', $form_state['values']['domain'])
          ->set('auto_assigned_role', $form_state['values']['auto_assigned_role'])
          ->set('hide_email', $form_state['values']['hide_email'])
          ->set('hide_password', $form_state['values']['hide_password'])
        ->save();
    parent::submitForm($form, $form_state);
  }
}
