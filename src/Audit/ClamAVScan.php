<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Param;

/**
 * Scans the repo for malcious files using ClamAV.
 * @Param(
 *  name = "scan_directory",
 *  type = "string",
 *  description = "The directory of that will be recursively scanned.",
 *  default = ".",
 * )
 */
class ClamAVScan extends Audit {

  /**
   * Check if ClamAV is available before proceeding.
   * This will be called before audit().
   *
   * Must return TRUE to continue audit.
   */
  protected function requireClamAVInstalled(Sandbox $sandbox)
  {
    $version = $sandbox->exec('clamscan -V');
    return strpos($version, "ClamAV") !== false;
  }

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $is_infected = FALSE;
    $infected_files_count = 0;

    $directory_to_scan = $sandbox->getParameter('scan_directory');

    try {
      # Run clamscan and pipe true to suppress early exit error caused if infected files are found.
      $output = $sandbox->exec('clamscan -r --bell -i --suppress-ok-results --exclude-dir=/vendor --max-filesize=10M --stdout '.$directory_to_scan.' || true');

      $searchfor = 'Infected files';
      $pattern = "/^.*$searchfor.*\$/m";
      if (preg_match_all($pattern, $output, $matches)) {
         $implode = implode("\n", $matches[0]);
         $infected_files_result = explode(':', $implode);

         if ($infected_files_result[1] > 0) {
           $is_infected = TRUE;
           $infected_files_count = $infected_files_result[1];
         }
      }
    }
    catch (Exception $e) {
      return Audit::ERROR;
    }

    if (!$is_infected) {
      return Audit::PASS;
    }

    $result = [
        'is_infected' => $is_infected,
        'infected_files_count' => $infected_files_count
    ];

    $sandbox->setParameter('report', $result);
    return Audit::FAIL;
  }
}
