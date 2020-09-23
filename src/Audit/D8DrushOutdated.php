<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;

/**
 * Simple Drush Status test.
 *
 * @Param(
 *  name = "minsupportversion",
 *  description = "The versions bellow that are not supported",
 *  type = "string"
 * )
 *
 * @Token(
 *  name = "status",
 *  type = "string",
 *  description = "Compares the installed version of drush with the latest available version"
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
   *
   */
  protected function requireContext(Sandbox $sandbox) {
    // TODO: put any checks for required context here.
    return TRUE;
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
      $output = $sandbox->exec('COMPOSER_MEMORY_LIMIT=-1 composer show "drush/drush" --all');
    }
    catch (\Exception $e) {
      throw new \Exception("Composer command failed: " . $e);
      return Audit::ERROR;
    }

    $lines = explode(PHP_EOL, $output);
    $lines = array_map('trim', $lines);
    foreach ($lines as $line) {
      if (strpos($line, 'versions :') !== FALSE) {
        $versions = preg_split('/\s+/', $line);
      }
    }

    // Do some filtering and transformation here.
    $versions = array_filter($versions, [$this, 'versionsFilter']);
    $versions = array_map([$this, 'cleanVersionLines'], $versions);
    // Just in case sort.
    $versions = call_user_func("Composer\Semver\Semver::$method", $versions);

    $ver = substr($current_drush, 0, 2);

    // Drupal core 8.4+ requires Drush 9
    // Drush 8 it is supported but not recommended.
    $notification_drush_9 = FALSE;
    if (!call_user_func("Composer\Semver\Comparator::$comparator_method", $current_drupal, '8.4')
    && strpos($current_drush, '8.') === self::STRING_BEGIN) {
      $notification_drush_9 = TRUE;
    }

    if (call_user_func("Composer\Semver\Comparator::$comparator_method", $current_drush, $min_support_version)) {
      $summary = "You are using an unsupported version ($current_drush) of Drush" . PHP_EOL;
      $sandbox->setParameter('status', trim($summary));
      return Audit::FAILURE;
    }

    foreach ($versions as $version) {
      if (strpos(trim($version), $ver) === 0) {
        $latest_version = $version;
        break;
      }
    }
    reset($versions);

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
      $summary .= PHP_EOL . "It's strongly recommended to update to Drush 9." . PHP_EOL;
    }

    $sandbox->setParameter('status', trim($summary));

    if ($latest_version !== $current_drush) {
      return Audit::FAILURE;
    }

    return Audit::SUCCESS;
  }

}
