<?php

namespace Drupal\ip_indicator\Model;

use Drupal\bootstrap\Plugin\Preprocess\Page;

class IpIndicatorWebformModel {
    public static $table = 'ip_indicator_webform';

    /**
     * @param $webform
     * @param $element
     * @param bool $is_category
     * @param string $category
     *
     * @return
     * @throws \Exception
     */
    public static function add($webform){
        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('COUNT(*)');
        $query->condition('webform', $webform);

        $count = $query->execute()
            ->fetchField();

        if($count == 0) {
            $query = \Drupal::database()
                ->select(self::$table, 'i');

            $query->addExpression('MAX(CAST(indicator as UNSIGNED))');

            $count = $query->execute()
                ->fetchField();

            \Drupal::database()->insert(self::$table)
                ->fields([
                    'webform' => $webform,
                    'indicator' => sprintf('%04d', ($count + 1))
                ])
                ->execute();
        }

        return self::get($webform);
    }

    public static function get($webform)
    {
        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('i.indicator');
        $query->condition('webform', $webform);

        return $query->execute()
            ->fetchField();
    }

    public static function update($webform, $indicator)
    {
        \Drupal::database()->update(self::$table)
            ->fields([
                'indicator' => $indicator
            ])
            ->condition('webform', $webform)
            ->execute();

        IpIndicatorModel::recalculate($webform, $indicator);
    }

    public static function key()
    {
        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('MAX(CAST(indicator as UNSIGNED))');

        return sprintf('%04d', $query->execute()->fetchField() + 1);
    }

    public static function exist($webform, $indicator)
    {
        if(empty($indicator)) {
            return false;
        }

        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('COUNT(*)');
        $query->condition('webform', $webform, '<>')
            ->condition('indicator', $indicator);

        return $query->execute()->fetchField() > 0;
    }
}
