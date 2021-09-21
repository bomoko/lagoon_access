<?php

namespace Drupal\ops_if\Commands;

use Drupal\jsonapi\JsonApiResource\Data;
use Drupal\ops_if\OpsIfFastly;
use Drupal\ops_if\OpsIfFastlyDrupalUtilities;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class OpsIfCommands extends DrushCommands {

  /**
   * Ages off IPs older than given number of days
   *
   * @param string $days
   *   Argument provided to the drush command.
   *
   * @command ops_if:clear
   * @aliases oifh
   * @usage ops_if:clear 90
   *   Will remove any ips from Fastly older than 90 days
   */
  public function hello($days = 90, $options = []) {
    $fastlyInterface = OpsIfFastly::GetOpsIfFastlyInstance(
      OpsIfFastlyDrupalUtilities::getApiKey(),
      OpsIfFastlyDrupalUtilities::getServiceId()
    );

    //Let's load all ACLs in the service

    $acls = $fastlyInterface->getAclList();
    $now = new \DateTime();
    $now->add(new \DateInterval('P4M'));

    foreach ($acls as $acl) {
      $ips = $fastlyInterface->getAclMembers($acl->id);
      foreach ($ips as $ip) {
        $createdDate = \DateTime::createFromFormat(
          "Y-m-d\TH:i:s\Z",
          $ip->created_at
        );
        $diff = $createdDate->diff($now);
        if ($diff->days > $days) {
          var_dump("Deleting address");
          $fastlyInterface->deleteAclMember($acl->id, $ip->ip);
          \Drupal::logger('ops_if')->info(
            "Aging off ip '%ip' from ACL '%acl' because it is over %days days old",
            ['%ip' => $ip->ip, '%days' => $days, '%acl' => $acl->name]
          );
        }
      }
    }
  }

}

