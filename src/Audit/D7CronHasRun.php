<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Driver\DrushFormatException;
use Drutiny\Annotation\Param;

/**
 *  Cron Has run since TIME.
 * @Param(
 *  name = "cron_max_interval",
 *  type = "integer",
 *  description = "The maximum interval between "
 * )
 */
class D7CronHasRun extends Audit {

  /**
   * @param \Drutiny\Sandbox\Sandbox $sandbox
   *
   * @return bool
   */
  public function audit(Sandbox $sandbox) {
    try {
      $vars = $sandbox->drush([
        'format' => 'json',
      ])->variableGet();

      if (!isset($vars['cron_last'])) {
        return FALSE;
      }
      $sandbox->setParameter('cron_last', date('l jS \of F Y h:i:s A', $vars['cron_last']));

      $time_diff = time() - $vars['cron_last'];

      if ($time_diff > $sandbox->getParameter('cron_max_interval')) {
        return FALSE;
      }
      return TRUE;

    } catch (DrushFormatException $e) {
      return Audit::ERROR;
    }

  }

}
