<?php

namespace Drupal\ip_indicator\Model;

class IpIndicatorModel {
    public static $table = 'ip_indicator';

    const TYPE_QUESTION = 0;
    const TYPE_CATEGORY = 1;

    /**
     * @param $webform
     * @param $element
     * @param bool $is_category
     * @param string $category
     *
     * @return
     * @throws \Exception
     */
    public static function add($webform, $element, $is_category = false, $category = '')
    {
        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('COUNT(*)');
        $query->condition('webform', $webform)
            ->condition('element', $element);

        $count = $query->execute()
            ->fetchField();

        if($count == 0) {
            $query = \Drupal::database()
                ->select(self::$table, 'i');

            $query->addExpression('COUNT(*)');
            $query->condition('webform', $webform)
                ->condition('type', ($is_category ? self::TYPE_CATEGORY : self::TYPE_QUESTION));

            $count = $query->execute()
                ->fetchField();

            \Drupal::database()->insert(self::$table)
                ->fields([
                    'webform' => $webform,
                    'element' => $element,
                    'type' => ($is_category ? self::TYPE_CATEGORY : self::TYPE_QUESTION),
                    'category' => $category,
                    'indicator' => ($is_category ? 'c' : 'i') . IpIndicatorWebformModel::add($webform) . '.' . sprintf('%06d', ($count + 1))
                ])
                ->execute();
        }
        else {
            \Drupal::database()->update(self::$table)
                ->fields([
                    'category' => $category
                ])
                ->condition('webform', $webform)
                ->condition('element', $element)
                ->execute();
        }

        return self::get($webform, $element);
    }

    public static function get($webform, $element)
    {
        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('i.indicator');
        $query->condition('webform', $webform)
            ->condition('element', $element);

        return $query->execute()
            ->fetchField();
    }

    public static function update($webform, $element, $indicator)
    {
        \Drupal::database()->update(self::$table)
            ->fields([
                'indicator' => $indicator
            ])
            ->condition('webform', $webform)
            ->condition('element', $element)
            ->execute();
    }

    public static function recalculate($webform, $indicator)
    {
      $query = \Drupal::database()
        ->select(self::$table, 'i');

      $query->addExpression('i.webform', 'webform');
      $query->addExpression('i.element', 'element');
      $query->addExpression('i.indicator', 'indicator');
      $query->condition('webform', $webform);

      $result = $query
        ->execute()
        ->fetchAll();

      foreach($result as $item) {
        $i = explode('.', $item->indicator);
        $new_indicator = str_replace($i[0], $i[0][0] . $indicator, $item->indicator);

        IpIndicatorModel::update($item->webform, $item->element, $new_indicator);
      }

      return true;
    }

    public static function key($webform)
    {
        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('MAX(CAST(element as UNSIGNED))');
        $query->condition('webform', $webform);

        return sprintf('%06d', $query->execute()->fetchField() + 1);
    }

    public static function exist($webform, $element, $indicator)
    {
        if(empty($indicator)) {
            return false;
        }

        $query = \Drupal::database()
            ->select(self::$table, 'i');

        $query->addExpression('COUNT(*)');
        $query->condition('webform', $webform)
            ->condition('element', $element, '<>')
            ->condition('indicator', $indicator);

        return $query->execute()->fetchField() > 0;
    }
}
