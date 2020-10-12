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
   * Check that Curl exists.
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

  private function checkUrl($url) {
    $ch = curl_init ($url);
    // TODO Revise this values
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $output = curl_exec ($ch);
    $status = curl_getinfo($ch)['http_code'];
    curl_close($ch);
    return $status;
  }

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $depth = $sandbox->getParameter('depth');

    $sdata = $sandbox->drush(['format' => 'json'])->status();
    $uri = $sdata['uri'];

    $msg = '';
    $checkForFiles = array('sitemap.xml');
    $broken_links = [];
    foreach($checkForFiles as $file){
      $url = $uri . '/' . $file;
      $status = $this->checkUrl($url);
      if ( $status !== 200 ){
          // Do something
      } else {
        $results = [];
        $links = json_decode(json_encode(simplexml_load_file($url) ), TRUE);
          $items = array_shift($links);
          $msg = 'Total number of links checked:' . count($items) . PHP_EOL;
          $count =0 ;
          $time_start = microtime(true);
          foreach ($items as $item) {
            $s = $this->checkUrl($item['loc']);
            if ($s !== 200) {
              $broken_links[] = [ 'uri' => $item['loc'], 'status' => $s];
            }
          } //.end for
          $time_end = microtime(true);
          $execution_time = ($time_end - $time_start)/1;
          $msg .= 'Execution time: ' . round($execution_time,1) . ' seconds' . PHP_EOL;
        }
  }

    // TODO: Search other directories for sitemap.xml files (?)

    if (count($broken_links)) {
      $msg .=  PHP_EOL . 'There ' . count($broken_links) .' broken links in the sitemap.xml' . PHP_EOL;
      $index = 1;
      foreach ($broken_links as $link) {
        $msg .= $index++ . '. ' . $link['uri'] . ' (STATUS: ' . $link['status'] . ')' . PHP_EOL;
      }
      $sandbox->setParameter('status', $msg);
      return Audit::FAILURE;
    }

    $sandbox->setParameter('status', $msg . 'All links are valid!');
    return Audit::SUCCESS;
  }

}
