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
class BrokenLinks extends Audit {

  /**
   * Check that target is actually a DrushTarget
   *
   * @param Sandbox $sandbox
   * @return void
   */
  protected function requireDrushTarget(Sandbox $sandbox){
    // TODO: think again of this requirement as it is not
    // always true that drush will be a dependency
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

    $sdata = $sandbox->drush(['format' => 'json'])->status();
    $uri = $sdata['uri'];

    try {
      $command = "vendor/bin/fink $uri --max-distance={$depth} --client-timeout=25000 --client-max-header-size=24576 --max-external-distance=0 --output=out.json --stdout";
      $str = shell_exec($command);
    }
    catch (\Exception $e) {
      throw new \Exception("Fink command failed: " . $e->getMessage());
    }

    $results = [];
    $results['informational responses'] = 0;
    $results['successful_responses'] = 0;
    $results['redirects'] = 0;
    $results['client_errors'] = 0;
    $results['server_errors'] = 0;

    if (!$str) {
      return Audit::ERROR;
    }

    // Used for markdown generator
    $rows = [];
    $issue =0 ;
    $arr = explode(PHP_EOL, $str);
    foreach ($arr as $item) {
      $r = json_decode($item, TRUE);
      $status = (int) $r['status'];
      // Informational responses (100–199)
      if ($this->statusRange($status,100,199)) {
        $results['informational responses'] += 1;
        continue;
      }
      // Successful responses (200–299)
      if ($this->statusRange($status,200,299)) {
        $results['successful_responses'] += 1;
        continue;
      }
      // Redirects (300–399)
      if ($this->statusRange($status,300,399)) {
        $results['redirects'] += 1;
        continue;
      }
      // Client errors (400–499)
      if ($this->statusRange($status,400,499)) {
        $issue++;
        $results['client_errors'] += 1;
        $rows[] = [
          'issue' => $issue,
          'status' => (string) $r["status"],
          'uri' => (string) $r["url"],
          'referrer' => (string) $r["referrer"],
          'timestamp' => (string) $r["timestamp"]
        ];
        continue;
      }
      // Server errors (500–599)
      if ($this->statusRange($status,500,599)) {
        $issue++;
        $results['server_errors'] += 1;
        $rows[] = [
          'issue' => $issue,
          'status' => (string) $r["status"],
          'uri' => (string) $r["url"],
          'referrer' => (string) $r["referrer"],
          'timestamp' => (string) $r["timestamp"]
        ];
        continue;
      }
    }

    // TODO: Which http code status will be errors and which warnings?
    $converted = array_map('intval', $results);
    $output = "Total number of links checked: " . array_sum($converted) . PHP_EOL . PHP_EOL;
    // $output .= http_build_query($results,'',PHP_EOL);
    if ($results['client_errors'] || $results['server_errors']) {
      foreach ($rows as $row) {
        $output .= "Issue: \t\t" . trim($row['issue']) . PHP_EOL;
        $output .= "Status: \t" . trim($row['status']) . PHP_EOL;
        $output .= "URI: \t\t" . trim($row['uri']) . PHP_EOL;
        $output .= "Referrer: \t" . trim($row['referrer']) . PHP_EOL;
        $output .= "Timestamp: \t" . trim($row['timestamp']) . PHP_EOL. PHP_EOL;
      }
      $sandbox->setParameter('status', trim($output));
      return Audit::FAIL;
    }
    $sandbox->setParameter('status', trim($output));

    return Audit::SUCCESS;
  }

}
