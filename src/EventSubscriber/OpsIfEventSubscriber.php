<?php

namespace Drupal\ops_if\EventSubscriber;

use http\Env\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Drupal\Core\Session\AccountProxy;
use Drupal\ops_if\OpsIfFastly;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\ops_if\EventSubscriber
 */
class OpsIfEventSubscriber implements EventSubscriberInterface {

  const OPS_TRIGGER_ROUTES = ['user.page', 'user.login'];

  /**
   * The account proxy service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  protected $fastlyOpsIfKey = NULL;

  protected $fastlyServiceId = NULL;

  /** @var OpsIfFastly */
  protected $fastlyInterface = NULL;

  /**
   * Constructs a new ProtectedPagesSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The account proxy service.
   */
  public function __construct(AccountProxy $currentUser) {
    $this->currentUser = $currentUser;
  }


  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::RESPONSE => 'pageResponse',
    ];
  }

  /**
   * Here we pull the API key for Fastly
   *
   * @return string
   */
  protected function getApiKey() {
    //TODO: work out how the heck does this work
    if (is_null($this->fastlyOpsIfKey)) {
      $this->fastlyOpsIfKey = 'b_mfw3KLYB6GXKYlpFJhykfGCMxz90zO';//getenv("OPS_IF_KEY");
      //Should this die?
    }
    return $this->fastlyOpsIfKey;
  }

  /**
   * Here we grab the service ID
   */
  protected function getFastlyServiceId() {
    if (is_null($this->fastlyServiceId)) {
      $this->fastlyServiceId = '2xYxtmbFJaZFClDPa1eod5';//getenv("OPS_IF_SERVICE_ID");
    }
    return $this->fastlyServiceId;
  }

  protected function getFastlyInterface() {
    if (is_null($this->fastlyInterface)) {
      $this->fastlyInterface = OpsIfFastly::GetOpsIfFastlyInstance(
        $this->getApiKey(),
        $this->getFastlyServiceId(),
        "" //TODO: kill this with fire
      );
    }
    return $this->fastlyInterface;
  }

  protected function getStandardAclName() {
    return 'dynamic';
  }

  protected function getAclIdForName($name) {
    $aclList = $this->getAclList();
    var_dump($aclList);
    foreach ($aclList as $item) {
      if($item->name == $name) {
        return $item->id;
      }
    }
  }

  protected function getAclList() {
    $this->getFastlyInterface();
    return $this->fastlyInterface->getAclList();
  }

  protected function addIpToACL() {
    $request = \Drupal::request();
    $currentIp = $request->getClientIp();

    $aclId = $this->getAclIdForName($this->getStandardAclName());
    //check If IP is currently in ACL
    $aclList = $this->fastlyInterface->getAclMembers($aclId);

    var_dump($aclList); die();

    $resp = $this->fastlyInterface->addAclMember($currentIp, $aclId);
  }

  /**
   * React to a page response
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Config crud event.
   */
  public function pageResponse(FilterResponseEvent $event) {
    if ($this->currentUser->hasPermission('access protected lagoon routes') &&
      in_array(
        \Drupal::routeMatch()
          ->getRouteName(),
        self::OPS_TRIGGER_ROUTES
      )) {
      \Drupal::messenger()->addStatus('Here we can send up the IP ...');
      $this->addIpToACL();
    }
    else {
      \Drupal::messenger()
        ->addStatus('User has not the permission or route is wrong');
    }
  }

}
