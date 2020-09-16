<?php

namespace Drutiny\algm\Audit;

use Drutiny\algm\Utils\MarkdownTableGenerator;
use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;
use Exception;

/**
 * Look for available security modules updates for Drupal 8.
 * @Token(
 *  name = "updates",
 *  type = "array",
 *  description = "Description of security module updates available."
 * )
 */
class D8SecurityModuleUpdates extends Audit {

  public function getDrushVersion($sandbox) {
    $drush_version = trim($sandbox->exec('drush version | grep "Drush" | sed -ne \'s/[^0-9]*\(\([0-9]\.\)\{0,4\}[0-9][^.]\).*/\1/p\''));

    if ($drush_version === '') {
      return Audit::ERROR;
    }

    return $drush_version;
  }

  public function isDrush8($drush_version) {
    return substr($drush_version, 0, 1 ) === "8";
  }

  public function isDrush9($drush_version) {
    return substr($drush_version, 0, 1 ) === "9";
  }

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {

    // Detect Drush version
    $drush_version = $this->getDrushVersion($sandbox);
    $modules = [];


    if ($this->isDrush8($drush_version)) {
      try {
        $modules = $sandbox->exec('drush pm-updatestatus --security-only --full --format=json');

        if ($modules === '') {
          $sandbox->setParameter('updates', 'No security modules to update.');
          return Audit::SUCCESS;
        }
      }
      catch (Exception $e) {
        throw new \Exception("Drush 8 command failed");
        return Audit::ERROR;
      }
    }

    if ($this->isDrush9($drush_version)) {
      try {
        $modules = $sandbox->exec('drush pm:security --format=json 2> /dev/null | cat $1');
      }
      catch (Exception $e) {
        throw new \Exception("Drush 9 command failed");
        return Audit::ERROR;
      }
    }

    if ($modules === '') {
      $sandbox->setParameter('updates', 'No security modules to update.');
      return Audit::SUCCESS;
    }

    $modules = json_decode($modules, TRUE);
    if ($modules === null) {
      return AUDIT::ERROR;
    }

    if (substr($drush_version, 0, 1 ) === "8") {
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
    }

    if (substr($drush_version, 0, 1 ) === "9") {
      $results = array_map(function($module) {
        return([
          'name' => isset($module['name']) ? $module['name'] : '',
          'version' => isset($module['version']) ? $module['version'] : '',
        ]);
      }, $modules);

      $columns = ['Name', 'Current Version'];
      $rows = [];
      foreach ($results as $key => $m) {
        $rows[] = [ $m["name"], $m["version"] ];
      }
    }

    $md_table = new MarkdownTableGenerator($columns, $rows);
    $rendered_table_markdown = $md_table->render();

    $sandbox->setParameter('updates', $rendered_table_markdown);

    return Audit::FAIL;
  }
}
