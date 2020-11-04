<?php

namespace Drutiny\algm\Audit;

use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Drutiny\Annotation\Token;
use Drutiny\Target\DrushTarget;

/**
 * Broken links checker.
 *
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
   * Check that curl exists.
   *
   * @return boolean
   */
  protected function requireCurl(){
    if (function_exists('curl_init')){
        return TRUE;
      } else{
        return FALSE;
    }
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
   * Finds if status is between range.
   *
   * @param integer $int
   * @param integer $min
   * @param integer $max
   * @return integer|NULL
   */
  protected function statusRange($int,$min,$max){
    return ($min<=$int && $int<=$max);
  }

  /**
   * Search subdirectories for sitemap.xml files
   *
   * @param Sandbox $sandbox
   * @param [type] $files_to_check
   * @return void
   */
  private function searchDirectoriesForSitemap(Sandbox $sandbox, &$files_to_check, $dir, $uri) {
    // TODO: Search directories for sitemap.xml
    $command = "cd $dir && find . -type f -name 'sitemap.xml'";
    $output = $sandbox->exec($command);
    $lines = array_filter(explode(PHP_EOL, $output));
    foreach ($lines as $line) {
      $files_to_check[] = $uri . '/' . str_replace('./', '',$line);
    }
  }

  /**
   * Check the status code of a url
   *
   * @param string $url
   * @return integer
   */
  private function checkUrl($url) {
    $ch = curl_init ($url);
    // TODO Revise this values
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_exec ($ch);

    if(curl_errno($ch)){
      throw new \Exception(curl_error($ch));
      return NULL;
    }

    $status = curl_getinfo($ch)['http_code'];
    curl_close($ch);
    return $status;
  }

  /**
   * Check links for a certain sitemap file
   *
   * @param [type] $uri
   * @return void
   */
  private function checkSitemap($uri, &$broken_links, &$total_links_count, &$total_execution_time) {
      // Checking this as sometimes the main sitemap.xml
      // is not a file in the system but a dynamic generated one
      $status = $this->checkUrl($uri);
      if ( $status !== 200 || $status === NULL){
        return;
      } else {
        $links = json_decode(json_encode(simplexml_load_file($uri) ), TRUE);
          $items = array_shift($links);
          $total_links_count += count($items);
          $time_start = microtime(true);
          foreach ($items as $item) {
            $link_status = $this->checkUrl($item['loc']);
            if ($link_status !== 200) {
              $broken_links[] = [ 'uri' => $item['loc'], 'status' => $link_status];
            }
          } //.end for
          $time_end = microtime(true);
          $execution_time = ($time_end - $time_start)/1;
          $total_execution_time += round($execution_time,1);
        }
  }

  /**
   * Returns the normalize URI (with https://)
   *
   * @param string $uri
   * @return string
   */
  private function getFullUri($uri) {
    if ( strpos($uri, 'http') !== 0) {
      $uri = 'https://' . $uri;
    }
    return $uri;
  }

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $directory = $sandbox->getParameter('directory', '%root');
    $stat = $sandbox->drush(['format' => 'json'])->status();

    $uri = $this->getFullUri($stat['uri']);

    $directory =  strtr($directory, $stat['%paths']);

    $files_to_check = [];
    $total_links_count = 0;
    $total_execution_time = 0;

    $status = $this->checkUrl($uri . '/sitemap.xml');
    if ($status === 200) {
      $files_to_check[] = $uri . '/sitemap.xml';
      $links = json_decode(json_encode(simplexml_load_file($uri . '/sitemap.xml') ), TRUE);
      if (isset($links['sitemap'])) {
        foreach($links['sitemap'] as $link) {
          $files_to_check[] = $link['loc'];
        }
      }
    } else {
      // There is no sitemap.xml
      $msg = 'A sitemap.xml file cannot be found.';
      $sandbox->setParameter('warning_message', $msg);
      return Audit::WARNING;
    }
    $this->searchDirectoriesForSitemap($sandbox, $files_to_check, $directory, $uri);

    $msg = '';
    $broken_links = [];
    foreach($files_to_check as $file){
      $this->checkSitemap($file, $broken_links, $total_links_count, $total_execution_time);
    }

    $msg .=  PHP_EOL . 'Total links checked: ' . $total_links_count . PHP_EOL;
    $msg .=  PHP_EOL . 'Total execution time: ' . $total_execution_time . PHP_EOL;

    if (count($broken_links)) {
      $msg .=  PHP_EOL . 'There ' . count($broken_links) .' broken links in the sitemap.xml' . PHP_EOL;
      $index = 1;
      foreach ($broken_links as $link) {
        $msg .= $index++ . '. ' . $link['uri'] . ' (STATUS: ' . $link['status'] . ')' . PHP_EOL;
      }
      $sandbox->setParameter('status', $msg);
      return Audit::FAILURE;
    }

    $sandbox->setParameter('status', 'All links are valid!' . $msg);
    return Audit::SUCCESS;
  }

}
