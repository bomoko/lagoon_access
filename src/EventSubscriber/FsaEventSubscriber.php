<?php

namespace Drupal\fastly_streamline_access\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\fastly_streamline_access\FsaFastlyDrupalUtilities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Drupal\Core\Session\AccountProxy;
use Drupal\fastly_streamline_access\FsaFastly;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\fastly_streamline_access\EventSubscriber
 */
class FsaEventSubscriber implements EventSubscriberInterface {

  const OPS_TRIGGER_ROUTES = ['user.page', 'user.login'];

  /**
   * The account proxy service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /** @var string */
  protected $fastlyFsaKey = NULL;

  /** @var string */
  protected $fastlyServiceId = NULL;

  /** @var FsaFastly */
  protected $fastlyInterface = NULL;

  /** @var ConfigFactoryInterface */
  protected $config;

  /**
   * Constructs a new ProtectedPagesSubscriber.
   *
   * @param \Drupal
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The account proxy service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AccountProxy $currentUser) {
    $this->currentUser = $currentUser;
    $this->config = $configFactory;
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
    if (is_null($this->fastlyFsaKey)) {
      $this->fastlyFsaKey = FsaFastlyDrupalUtilities::getApiKey();
    }
    return $this->fastlyFsaKey;
  }

  /**
   * Here we grab the service ID
   */
  protected function getFastlyServiceId() {
    if (is_null($this->fastlyServiceId)) {
      $this->fastlyServiceId = FsaFastlyDrupalUtilities::getServiceId();
    }
    return $this->fastlyServiceId;
  }

  protected function getFastlyInterface() {
    if (is_null($this->fastlyInterface)) {
      $this->fastlyInterface = FsaFastly::GetFsaFastlyInstance(
        $this->getApiKey(),
        $this->getFastlyServiceId()
      );
    }
    return $this->fastlyInterface;
  }

  protected function getStandardAclName() {
    return $this->config->get('fastly_streamline_access.settings')->get('acl_name');;
  }

  protected function getLongLivedAclName() {
    return $this->getStandardAclName() . '_longlived';
  }

  protected function getAclIdForName($name) {
    $aclList = $this->getAclList();
    foreach ($aclList as $item) {
      if($item->name == $name) {
        return $item->id;
      }
    }
  }

  protected function getAclList() {
    $this->getFastlyInterface();
    if(!isset($this->cache['aclList'])) {
      $this->cache['aclList'] = $this->fastlyInterface->getAclList();
    }
    return $this->cache['aclList'];
  }

  protected function addIpToACL() {
    $request = \Drupal::request();
    $currentIp = $request->getClientIp();

    $aclId = $this->getAclIdForName($this->getStandardAclName());

    //check If IP is currently in ACL
    $aclList = $this->fastlyInterface->getAclMembers($aclId);
    foreach ($aclList as $item) {
      if($item->ip == $currentIp) {
        return; //we've already got the ip address
      }
    }

    $extraData = [
      'user' => $this->currentUser->getAccountName(),
      'site' => \Drupal::config('system.site')->get('name'),
    ];

    // We never add to the Long Lived ACL
    $this->fastlyInterface->addAclMember($aclId, $currentIp, $extraData);
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
      try {
        $this->addIpToACL();
      } catch (\Exception $exception) {
        \Drupal::logger('fastly_streamline_access')->error($exception->getMessage());
      }
    }
  }

}
