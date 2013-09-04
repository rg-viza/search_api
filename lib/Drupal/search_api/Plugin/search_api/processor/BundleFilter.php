<?php

/**
 * @file
 * Contains \Drupal\search_api\Plugin\search_api\processor\BundleFilter.
 */

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Annotation\Translation;
use Drupal\search_api\Annotation\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase;

/**
 * Filters out entities based on their bundle.
 *
 * @SearchApiProcessor(
 *   id = "search_api_bundle_filter",
 *   name = @Translation("Bundle filter"),
 *   description = @Translation("Exclude items from indexing based on their bundle (content type, vocabulary, …)."),
 *   weight = -20
 * )
 */
class BundleFilter extends ProcessorPluginBase {

  /**
   * Overrides \Drupal\search_api\Plugin\Type\Processor\ProcessorPluginBase::supportsIndex().
   *
   * Only support entities with bundles.
   */
  public static function supportsIndex(IndexInterface $index) {
    return $index->getEntityType() && ($info = entity_get_info($index->getEntityType())) && self::hasBundles($info);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $info = entity_get_info($this->index->getEntityType());
    if (self::hasBundles($info)) {
      $options = array();
      foreach ($info['bundles'] as $bundle => $bundle_info) {
        $options[$bundle] = isset($bundle_info['label']) ? $bundle_info['label'] : $bundle;
      }
      $form = array(
        'default' => array(
          '#type' => 'radios',
          '#title' => t('Which items should be indexed?'),
          '#default_value' => isset($this->options['default']) ? $this->options['default'] : 1,
          '#options' => array(
            1 => t('All but those from one of the selected bundles'),
            0 => t('Only those from the selected bundles'),
          ),
        ),
        'bundles' => array(
          '#type' => 'select',
          '#title' => t('Bundles'),
          '#default_value' => isset($this->options['bundles']) ? $this->options['bundles'] : array(),
          '#options' => $options,
          '#size' => min(4, count($options)),
          '#multiple' => TRUE,
        ),
      );
    }
    else {
      $form = array(
        'forbidden' => array(
          '#markup' => '<p>' . t("Items indexed by this index don't have bundles and therefore cannot be filtered here.") . '</p>',
        ),
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array &$items) {
    $info = entity_get_info($this->index->getEntityType());
    if (self::hasBundles($info) && isset($this->options['bundles'])) {
      $bundles = array_flip($this->options['bundles']);
      $default = (bool) $this->options['default'];
      $bundle_prop = $info['entity keys']['bundle'];
      foreach ($items as $id => $item) {
        if (isset($bundles[$item->$bundle_prop]) == $default) {
          unset($items[$id]);
        }
      }
    }
  }

  /**
   * Determines whether an entity type defines any bundles.
   *
   * @param array $entity_info
   *   The information for the entity type in question.
   *
   * @return bool
   *   TRUE if the type defines bundles, FALSE otherwise.
   */
  protected static function hasBundles(array $entity_info) {
    return !empty($entity_info['entity keys']['bundle']) && !empty($entity_info['bundles']);
  }

}
