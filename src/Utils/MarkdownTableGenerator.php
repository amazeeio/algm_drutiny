<?php

namespace Drutiny\algm\Utils;

/**
 * Generate markdown table as output from php array
 */
class MarkdownTableGenerator {
	private $columns = [];
	private $length = [];
  private $data = [];

  const MAX_STRING_LENGTH = 75;

	/**
   * Generate columns and populate rows with given data
	 */
	public function __construct($columns = null, $rows = []) {
		if ($columns) {
			$this->columns = $columns;
		}
		elseif ($rows) {
			foreach ($rows[0] as $key => $value) {
        $this->columns[$key] = $key;
      }
		}

		foreach ($this->columns as $key => $header) {
			$this->length[$key] = strlen($header);
		}

    foreach ($rows as &$row) {
      foreach ($this->columns as $key => $value) {
        if (!isset($row[$key])) {
          $row[$key] = '-';
        }
        elseif (strlen($row[$key]) > self::MAX_STRING_LENGTH) {
          $this->length[$key] = self::MAX_STRING_LENGTH;
          // Add an ellipsis if string reaches max.
          $row[$key] = substr($row[$key], 0, self::MAX_STRING_LENGTH - 3).'...';
        }
        elseif (strlen($row[$key]) > $this->length[$key]) {
          $this->length[$key] = strlen($row[$key]);
        }
      }
    }

    $this->data = $rows;
	}

	private function renderColumnRow() {
		$res = '|';

		foreach ($this->length as $key => $l) {
      $res .= ' ' . str_repeat('-', $l) . ' ' . '|';
    }

		return $res."\r\n";
	}

  private function renderLineRow() {
    $res = ' ';

    $sum = 0;
    foreach ($this->length as $key => $l) {
      $sum += $l+3;
    }

    $res .= str_repeat('-', $sum);

    return $res."\r\n";
  }

	private function renderRow($row) {
		$res = '|';
		foreach ($this->length as $key => $l) {
			$res .= ' '.$row[$key].($l > strlen($row[$key]) ? str_repeat(' ', $l - strlen($row[$key])) : '').' |';
		}

		return $res."\r\n";
	}

	public function render() {
    $res = $this->renderLineRow();
		$res .= $this->renderRow($this->columns);
		$res .= $this->renderColumnRow();

		foreach ($this->data as $row) {
      $res .= $this->renderRow($row);
    }

    $res .= $this->renderLineRow();

		return $res;
	}
}
