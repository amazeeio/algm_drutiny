<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;
use Drutiny\Annotation\Param;
use Drutiny\Target\DrushTarget;

/**
 * Broken links checker.
 *
 * @Param(
 *  name = "depth",
 *  type = "integer",
 *  description = "The maximum depth to check"
 * )
 * @Token(
 *  name = "status",
 *  type = "string",
 *  description = "Compares the installed version of drush with the latest available version"
 * )
 * @Token(
 *  name = "warning_message",
 *  type = "string",
 *  description = "A warning message to show when Audit::WARNING is returned"
 * )
 */
class BrokenLinksSitemap extends Audit {

  /**
   * Check that target is actually a DrushTarget
   *
   * @param Sandbox $sandbox
   * @return void
   */
  protected function requireDrushTarget(Sandbox $sandbox){
    return $sandbox->getTarget() instanceof DrushTarget;
  }

  /**
   * Finds if status is between range.
   *
   * @param integer $int
   * @param integer $min
   * @param integer $max
   * @return void
   */
  protected function statusRange($int,$min,$max){
    return ($min<=$int && $int<=$max);
  }

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $depth = $sandbox->getParameter('depth');
    $directory = $sandbox->getParameter('directory', '%root');
    $stat = $sandbox->drush(['format' => 'json'])->status();
    $directory =  strtr($directory, $stat['%paths']);

    $command = "find . -type f -name 'index.php'";
    $output = $sandbox->exec($command);
    $lines = array_filter(explode(PHP_EOL, $output));

    print_r($lines);
    // TODO: Check if sitemap.xml exists
    $url = 'https://nginx-1000plus-dev.ch.amazee.io';

    $sandbox->setParameter('status', 'Ok');
    return Audit::SUCCESS;
  }

}
