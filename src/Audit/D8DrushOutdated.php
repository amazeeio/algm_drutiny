<?php

/**
 * Notes: The comparison follow the principles of Semantic Versioning 2.0.0.
 * We compare the current installed version of Drush in the format MAJOR.MINOR.
 * Examples (versions as of Sep.2020):
 *   #1
 *   Drush installed version: 8.1 , then latest version is 8.4.2
 *   Audit fails in that case. Also v10.3.4 is displayed to the user.
 *
 *   #2
 * - Drush installed version: 9.5.0 , then latest version is 9.5.2
 *   Audit fails in that case. Also v10.3.4 is displayed to the user as well.
 *
 *   #3
 * - Drush installed version: 8.4.2 , then latest version is 8.4.2. Drupal core is at 8.8.
 *   Audit returns a warning (success + warning) to update to Drush 9.
 *
 *   #4
 * - Drush installed version: 7.4.0 , then latest (least) version is 8.4.2.
 *   Audit fails (Drush 7 in core 8 is unsupported).
 *
 *  We do not count alpha, beta, rc and dev releases.
 */

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;
use Drutiny\Annotation\Param;
use Drutiny\Target\DrushTarget;

/**
 * Simple Drush Status test.
 *
 * @Param(
 *  name = "minsupportversion",
 *  description = "The versions bellow that are not supported",
 *  type = "string"
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
class D8DrushOutdated extends Audit {

  const STRING_BEGIN = 0;

  /**
   * Array filter to drops out dev, beta versions etc.
   *
   * @param string $str
   *
   * @return bool
   */
  private function versionsFilter($str) {
    // Using strpos as probably is cheaper to evaluate than preg_match.
    return strpos($str, 'dev') === FALSE
    && strpos($str, 'beta') === FALSE
    && strpos($str, 'alpha') === FALSE
    && strpos($str, 'ver') === FALSE
    && strpos($str, 'rc') === FALSE
    && strpos($str, ':') === FALSE;
  }

  /**
   * Clean strings from comma chars.
   *
   * @param string $line
   *
   * @return string
   */
  private function cleanVersionLines($line) {
    return preg_replace('/,/', '', $line);
  }

  /**
   * Returns the latest version of a subversion
   *
   * @param array $versions
   *  A sorted array using semantic version sort
   * @param array $subversion
   *  A subversion to compare with
   * @return string
   */
  private function extractLatestVersion($versions, $subversion) {
    $latest_version = NULL;
    foreach ($versions as $version) {
      if (strpos(trim($version), $subversion) === 0) {
        $latest_version = $version;
        break;
      }
    }
    return $latest_version;
  }

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
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $min_support_version = $sandbox->getParameter('minsupportversion');

    $config = $sandbox->drush(['format' => 'json'])->status();
    if ($config === NULL) {
      return AUDIT::ERROR;
    }

    $current_drupal = isset($config['drupal-version']) ? $config['drupal-version'] : NULL;
    $current_drush = isset($config['drush-version']) ? $config['drush-version'] : NULL;

    if (!$current_drupal || !$current_drush) {
      return Audit::ERROR;
    }

    $method = 'rsort';
    if (!method_exists("Composer\Semver\Semver", $method)) {
      throw new \Exception("Comparator method not available: $method");
    }

    $comparator_method = 'lessThan';
    if (!method_exists("Composer\Semver\Comparator", $comparator_method)) {
      throw new \Exception("Comparator method not available: $comparator_method");
    }

    try {
      $output = $sandbox->exec('COMPOSER_MEMORY_LIMIT=-1 composer show "drush/drush" --all --format=json');
    }
    catch (\Exception $e) {
      throw new \Exception("Composer command failed: " . $e);
      return Audit::ERROR;
    }

    if ($output===NULL) {
      return Audit::ERROR;
    }

    $output = json_decode($output, TRUE);
    $versions = $output['versions'];
    // Do some filtering and transformation here.
    $versions = array_filter($versions, [$this, 'versionsFilter']);
    $versions = array_map([$this, 'cleanVersionLines'], $versions);
    // Just in case sort.
    $versions = call_user_func("Composer\Semver\Semver::$method", $versions);

    // Drupal core 8.4+ requires Drush 9
    // Drush 8 it is supported but not recommended.
    $notification_drush_9 = FALSE;
    if (!call_user_func("Composer\Semver\Comparator::$comparator_method", $current_drupal, '8.4')
    && strpos($current_drush, '8.') === self::STRING_BEGIN) {
      $notification_drush_9 = TRUE;
    }

    if (call_user_func("Composer\Semver\Comparator::$comparator_method", $current_drush, $min_support_version)) {
      $summary = "You are using an unsupported version ($current_drush) of Drush" . PHP_EOL;
      $summary .= "Advise is to update to at least Drush " . $this->extractLatestVersion($versions,'8') . PHP_EOL;
      $sandbox->setParameter('status', trim($summary));
      return Audit::FAILURE;
    }

    $subver = substr($current_drush, 0, 2);
    $latest_version = $this->extractLatestVersion($versions,$subver);
    if ($latest_version !== $current_drush) {
      $msg = "You are NOT using the latest version of Drush" . PHP_EOL;
    }
    else {
      $msg = "You are using the latest version of Drush" . PHP_EOL;
    }

    $summary = $msg;
    $summary .= "Current\t\t" . $current_drush . PHP_EOL;
    $summary .= "Latest\t\t" . $latest_version . PHP_EOL;
    $other_version = array_shift($versions);
    if ($other_version !== $latest_version) {
      $summary .= "Also available\t" . $other_version . PHP_EOL;
    }

    if ($notification_drush_9) {
      $msg = "While the audit pass, it's strongly recommended to update to Drush 9.";
      $sandbox->setParameter('warning_message', $msg);
    }

    $sandbox->setParameter('status', trim($summary));

    if ($latest_version !== $current_drush) {
      return Audit::FAILURE;
    }

    return $notification_drush_9 ? Audit::WARNING : Audit::SUCCESS;
  }

}
