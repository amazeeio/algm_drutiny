<?php

namespace Drutiny\algm\Audit;

use Drutiny\algm\Utils\MarkdownTableGenerator;
use Drutiny\Annotation\Param;
use Drutiny\Annotation\Token;
use Drutiny\Audit;
use Drutiny\Sandbox\Sandbox;
use Exception;

/**
 *  Filesystem analysis.
 *
 * @Token(
 *  name = "size",
 *  type = "string",
 *  description = "Result from file system analysis"
 * )
 *
 * @Param(
 *  name = "filesystem",
 *  type = "array",
 *  description = "the storage usage information for both disk and inodes.",
 * )
 */
class FileSystemAnalysis extends Audit {

  const MAX_STRING_LENGTH = 60;

  /**
   * @inheritdoc
   */
  public function audit(Sandbox $sandbox) {
    $path = $sandbox->getParameter('path', '%files');
    $status = $sandbox->drush(['format' => 'json'])->status();

    if ($status === null) {
      return AUDIT::ERROR;
    }

    $options = $sandbox->getTarget()->getOptions();

    $path = $options["root"] . '/' . strtr($path, $status['%paths']);

    try {
      $size = trim($sandbox->exec("du -d 0 -m $path | awk '{print $1}'"));
    }
    catch (Exception $e) {
      return Audit::ERROR;
    }

    $max_size = (int) $sandbox->getParameter('max_size', 20);

    // Set fs size in MB
    $sandbox->setParameter('size', $size);
    $sandbox->setParameter('path', $path);

    try {
      $output = $sandbox->exec("df -H");
    }
    catch (Exception $e) {
      return Audit::ERROR;
    }

    $disk = array_map(function($line) {
      $elements=preg_split('/\s+/',$line);

      return([
        'filesystem' => isset($elements[0]) ? $elements[0] : '',
        'size' => isset($elements[1]) ? $elements[1] : '',
        'used' => isset($elements[2]) ? $elements[2] : '',
        'available' => isset($elements[3]) ? $elements[3] : '',
        'use%' => isset($elements[4]) ? $elements[4] : '',
        'mounted' => isset($elements[5]) ? $elements[5] : '',
      ]);
    },explode("\n",$output));

    $columns = ['Fs', 'Size', 'Used', 'Avail.', 'Use%'];
    $rows = [];
    foreach ($disk as $key => $d) {
      $fs = $d["filesystem"]." (".$d["mounted"].")";

      $fs_mnt = (strlen($fs) > self::MAX_STRING_LENGTH) ?
        substr($fs, 0, self::MAX_STRING_LENGTH - 3).'...'
        : $fs;

      $rows[] = [ $fs_mnt, $d["size"], $d["used"], $d["available"], $d["use%"] ];
    }

    array_shift($rows);
    array_pop($rows);

    $md_table = new MarkdownTableGenerator($columns, $rows);
    $rendered_table_markdown = $md_table->render();

    $sandbox->setParameter('filesystem', $rendered_table_markdown);



    if ($size < $max_size) {
      return Audit::SUCCESS;
    }

    return Audit::FAIL;
  }
}
