<?php


namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;


class DevModulesEnabled extends Audit {

  public function audit(Sandbox $sandbox) {
    $dev_modules = [
      'browser_refresh',
      'coffee',
      'devel',
      'hacked',
      'kint',
      'link_css',
      'rules_ui',
      'stage_file_proxy',
      'reroute_email',
    ];

    $info = array_keys($sandbox->drush([
      'format' => 'json',
      'status' => 'Enabled',
      'type' => 'Module',
    ])->pmList());

    if (empty(array_intersect($info, $dev_modules))) {
      return TRUE;
    }

    $sandbox->setParameter('modules', implode(",", (array_intersect($info, $dev_modules))));
    return FALSE;
  }

}