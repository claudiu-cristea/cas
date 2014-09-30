<?php

namespace Drupal\cas\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ProxyCallbackController implements ContainerInjectionInterface {
    /**
     * @var \Drupal\Core\Database\Connection
     */
    protected $connection;

    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * Constructor.
     *
     * @param Connection $database_connection
     *   The database service.
     * @param RequestStack $request_stack
     *   The Symfony request stack.
     */
    public function __construct(Connection $database_connection, RequestStack $request_stack) {
      $this->connection = $database_connection;
      $this->requestStack = $request_stack;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
      // This needs to get the necessary __construct requirements from
      // the container.
      return new static($container->get('database'), $container->get('request_stack'));
    }
    public function callback() {
      // This needs to store the incoming PGTIOU and pgtId parameters so that
      // the incoming response from the CAS server can be looked up.
      // @TODO: Check that request is coming from configured CAS server to avoid
      // filling up the table with bogus pgt values.
      $request = $this->requestStack->getCurrentRequest();
      // Check for both a pgtIou and pgtId parameter. If either is not present,
      // inform CAS Server of an error.
      if (!($request->query->get('pgtId') && $request->query->get('pgtIou'))) {
        return new Response('Missing necessary parameters', 400);
      }
      else {
        // Store the pgtIou and pgtId in the database for later use.
        $pgtId = $request->query->get('pgtId');
        $pgtIou = $request->query->get('pgtIou');
        $result = $this->connection->insert('cas_pgt_storage')
          ->fields(array('pgt_iou', 'pgt'), array($pgtIou, $pgtId))
          ->execute();
        // PGT stored properly, tell CAS Server to proceed.
        return new Response('OK', 200);
      }
    }
}
