<?php

namespace Drutiny\algm\Audit;

use Drutiny\algm\Utils\MarkdownTableGenerator;
use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;

/**
 * Look for available security modules updates.
 * @Token(
 *  name = "updates",
 *  type = "array",
 *  description = "Description of security module updates available."
 * )
 */
class D7SecurityModuleUpdates extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {

    try {
      $modules = $sandbox->exec('drush pm-updatestatus --security-only --full --format=json');
    }
    catch (Exception $e) {
      throw new \Exception("Drush 8 command failed");
      return Audit::ERROR;
    }

    if ($modules === '') {
      $sandbox->setParameter('updates', 'No security modules to update.');
      return Audit::SUCCESS;
    }

    $modules = json_decode($modules, TRUE);
    if ($modules === null) {
      return AUDIT::ERROR;
    }

    $results = array_map(function($module) {
      return([
        'name' => isset($module['name']) ? $module['name'] : '',
        'existing_version' => isset($module['existing_version']) ? $module['existing_version'] : '',
        'latest_version' => isset($module['latest_version']) ? $module['latest_version'] : '',
        'recommended' => isset($module['recommended']) ? $module['recommended'] : '',
        'status_msg' => isset($module['status_msg']) ? $module['status_msg'] : '',
        'link' => isset($module['link']) ? $module['link'] : '',
      ]);
    }, $modules);

    $columns = ['Name', 'Current Version', 'Recommended', 'Status', 'Link'];
    $rows = [];
    foreach ($results as $key => $m) {
      $rows[] = [ $m["name"], $m["existing_version"], $m["recommended"], $m['status_msg'], $m['link'] ];
    }

    $md_table = new MarkdownTableGenerator($columns, $rows);
    $rendered_table_markdown = $md_table->render();

    $sandbox->setParameter('updates', $rendered_table_markdown);

    return Audit::FAIL;
  }
}
