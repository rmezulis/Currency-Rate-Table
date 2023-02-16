<?php
/**
 * @file
 * Contains \Drupal\hello_world\Controller\CurrencyRateTableController.
 */

namespace Drupal\currency_rate_table\Controller;

use Drupal;

class CurrencyRateTableController {

  public function content(): array {
    $header = ['Name', 'Bank Buys', 'Bank Sells', 'ECB Rate', 'Change'];
    $rows = [];
    $connection = Drupal::service('database');
    $latest = $connection->query('
    SELECT created_at
    FROM currency_rates
    ORDER BY created_at DESC
    LIMIT 1');
    $latest = $latest->fetch();
    if (empty($latest) || (time() - strtotime($latest->created_at)) > 60 * 5) {
      $this->updateRates();
    }
    $results = $connection->query('
    SELECT cr.name, cr.buy_rate, cr.sell_rate, cr.ecb_rate, cr.ecb_change, cr.created_at
FROM currency_rates cr
INNER JOIN (
    SELECT name, max(created_at) as MaxDate
    FROM currency_rates
    GROUP BY name
) crm on cr.name = crm.name and cr.created_at = crm.MaxDate');
    while ($result = $results->fetch()) {
      $rows[] = [
        $result->name,
        $result->buy_rate,
        $result->sell_rate,
        $result->ecb_rate,
        $result->ecb_change,
      ];
    }
    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  private function updateRates(): void {
    $filepath = Drupal::service('file_system')
      ->realpath('public://testdata/www.crc');
    $data = array_map('str_getcsv', file($filepath));

    $connection = Drupal::service('database');
    foreach ($data as $rate) {
      $result = $connection->query(
        "SELECT ecb_rate FROM currency_rates WHERE name = :name ORDER BY created_at DESC LIMIT 1",
        [':name' => $rate[0]]
      )->fetch();
      $connection->insert('currency_rates')->fields([
        'name' => $rate[0],
        'buy_rate' => $rate[1],
        'sell_rate' => $rate[2],
        'ecb_rate' => $rate[3],
        'ecb_change' => $result && $result->ecb_rate > $rate[3] ? 'lower' : ($result && $result->ecb_rate < $rate[3] ? 'higher' : 'same'),
      ])->execute();
    }
  }

}
