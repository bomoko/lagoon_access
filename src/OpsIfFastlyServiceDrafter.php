<?php


namespace Drupal\ops_if;

use \Exception;


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

  protected $editingService;

  protected $serviceName;

  private $editingVersionDetails;

  public static function doStandardACLRegistration(
    OpsIfFastly $fastly,
    $serviceName
  ) {
    $drafter = new OpsIfFastlyServiceDrafter($fastly, $serviceName);
    $drafter->stageNewVersionOfService();
//    var_dump($drafter->registerACLs($serviceName . '_long_lived'));
    var_dump($drafter->registerACLs($serviceName));
    //    $drafter->registerVCL(
    //      $serviceName,
    //      'sub vcl_recv {
    //    error 403 "Forbidden";
    //}'
    //    );
    $drafter->publishNewVersion();
  }

  public function __construct(OpsIfFastly $fastly, $serviceName) {
    $this->OpsIfFastly = $fastly;
    $this->serviceName = $serviceName;
  }

  // Should this be moved into a second class? ... perhaps
  public function stageNewVersionOfService() {
    if ($this->editingService == TRUE) {
      throw new Exception(
        "You are already editing a service - can not clone a new service"
      );
    }

    $latestServiceVersion = $this->OpsIfFastly->getLatestServiceVersion();
    if (is_null($latestServiceVersion)) {
      throw new Exception(
        "Cannot stage a new version of the latest service, there has been no service published"
      );
    }
    if ($latestServiceVersion->number != $this->OpsIfFastly->getLatestActiveServiceVersion(
      )->number) {
      throw new Exception(
        "It seems that the latest version of the service isn't the active version - this means either that there has been a service rollback, or that the service is being edited. Contact Fastly administrator"
      );
    }


    $endpoint = sprintf(
      "/service/%s/version/%s/clone",
      $latestServiceVersion->service_id,
      $latestServiceVersion->number
    );

    $clonedService = $this->OpsIfFastly->getOpsCommInstance()->doPut($endpoint);

    $this->editingVersionDetails = $clonedService;
    $this->editingService = TRUE;

    return $clonedService->number;
  }

  public function registerVCL($name, $vcl) {
    return $this->OpsIfFastly->addVclSnippetToVersion(
      $name,
      $vcl,
      OpsIfFastly::VCL_PRIORITY
    );
  }

  public function registerACLs($name) {
    return $this->OpsIfFastly->addAclToVersion($name);
  }


  public function publishNewVersion() {
    $endpoint = sprintf(
      "/service/%s/version/%s/activate",
      $this->editingVersionDetails->service_id,
      $this->editingVersionDetails->number
    );
    $this->OpsIfFastly->getOpsCommInstance()->doPut($endpoint);
  }


}
