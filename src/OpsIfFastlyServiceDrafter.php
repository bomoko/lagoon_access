<?php


namespace OpsFs;


/**
 * Class OpsIfFastlyServiceDrafter
 * This class is the primary wrapper for dealing with Drafting new services
 * Specifically, it should be used for setting up and tearing down OPS instances
 *
 * Essentially it provides guide rails for setup and teardown of the objects
 * required by OPS
 *
 * @package OpsFs
 */

class OpsIfFastlyServiceDrafter {

  protected $OpsIfFastly;

  public function __construct(OpsIfFastly $fastly, $serviceName) {
    $this->OpsIfFastly = $fastly;
  }

  // Should this be moved into a second class? ... perhaps
  public function stageNewVersionOfService() {
    if ($this->editingService == TRUE) {
      throw new Exception("You are already editing a service - can not clone a new service");
    }

    $latestServiceVersion = $this->getLatestServiceVersion();
    if (is_null($latestServiceVersion)) {
      throw new Exception("Cannot stage a new version of the latest service, there has been no service published");
    }
    if ($latestServiceVersion->number != $this->getLatestActiveServiceVersion()->number) {
      throw new Exception("It seems that the latest version of the service isn't the active version - this means either that there has been a service rollback, or that the service is being edited. Contact Fastly administrator");
    }


    $endpoint = sprintf("%s/service/%s/version/%s/clone",
      $this->fastlyApiBase,
      $this->serviceId,
      $latestServiceVersion->number
    );

    $clonedService = $this->opsFsCommsInstance->doPut($endpoint);

    $this->editingVersion = $clonedService;
    $this->editingService = TRUE;

    return $clonedService->number;
  }



}