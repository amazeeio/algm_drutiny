<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;
use Drutiny\algm\Utils\MarkdownTableGenerator;

/**
 * Uses composer to look for contrib modules with available updates.
 * @Token(
 *  name = "updates",
 *  type = "array",
 *  description = "Description of module updates available."
 * )
 */
class ModuleUpdateStatus extends Audit {

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $output = $sandbox->exec('COMPOSER_MEMORY_LIMIT=-1 composer show "drupal/*" -o --no-cache --format=json 2> /dev/null && echo \'\'');

    $modules = json_decode($output, TRUE);
    if ($modules === null) {
      // If null, then the json cannot be decoded
      return AUDIT::ERROR;
    }

    // Pass check if there are no updates.
    if (empty($modules["installed"])) {
      return Audit::SUCCESS;
    }

    $num_modules = count($modules["installed"]);
    $columns = ['Module', 'Version', 'Status', 'Latest'];
    $rows = [];
    foreach ($modules["installed"] as $key => $module) {
      $rows[] = [ $module["name"], $module["version"], $module["latest-status"], $module["latest"] ];
    }

    $md_table = new MarkdownTableGenerator($columns, $rows);
    $rendered_table_markdown = $md_table->render();

    $sandbox->setParameter('updates', $rendered_table_markdown);

    return Audit::FAIL;
  }
}
