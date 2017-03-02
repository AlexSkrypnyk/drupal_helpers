<?php

namespace Drupal\drupal_helpers;

use Exception;

/**
 * Class Field.
 *
 * @package Drupal\drupal_helpers
 */
class Field {

  /**
   * Delete a field.
   *
   * Remove all instances of a field from all the entity bundles it has been
   * attached to and then delete and purge field's data from the database.
   *
   * @param string $field_name
   *   Machine name of the Field.
   *
   * @throws \Exception
   */
  public static function delete($field_name) {
    $t = get_t();

    try {
      $field = field_info_field($field_name);
      $replacements = ['!field' => $field_name];
      if (!$field) {
        General::messageSet($t('Skipped: !field was not found', $replacements));

        return;
      }

      if (isset($field['bundles']) && is_array($field['bundles'])) {
        foreach ($field['bundles'] as $entity_type => $bundles) {
          $replacements['!entity'] = $entity_type;
          if (is_array($bundles)) {
            foreach ($bundles as $entity_bundle) {
              self::deleteInstance($field_name, $entity_type, $entity_bundle);
            }
          }
        }
      }

      field_delete_field($field_name);

      $batch_size = variable_get('field_purge_batch_size', 10);
      field_purge_batch($batch_size);

      General::messageSet($t('The field !field has been deleted.', $replacements));
    }
    catch (Exception $e) {
      $replacements['@error_message'] = $e->getMessage();
      $message = 'Failed to delete !field: @error_message';

      throw new Exception($t($message, $replacements), $e->getCode(), $e);
    }
  }

  /**
   * Delete an Instance of a Field.
   *
   * Delete a specific field instance attached to one entity type without
   * deleting the field itself.
   *
   * @param string $field_name
   *   Machine name of the Field.
   * @param string $entity_type
   *   Machine name of the Entity type.
   * @param string $entity_bundle
   *   Machine name of the Entity Bundle.
   *
   * @throws \Exception
   */
  public static function deleteInstance($field_name, $entity_type, $entity_bundle) {
    $t = get_t();
    $replacements = [
      '!field' => $field_name,
      '!entity' => $entity_type,
      '!bundle' => $entity_bundle,
    ];

    try {
      $instance = field_info_instance($entity_type, $field_name, $entity_bundle);
      if ($instance) {
        field_delete_instance($instance, FALSE);
        $message = 'Success: deleted the field !field from the !entity !bundle content type.';
      }
      else {
        $message = 'Skipped: the !field was not found for the !entity !bundle content type.';
      }
    }
    catch (Exception $e) {
      $replacements['@error_message'] = $e->getMessage();
      $message = 'Problem removing the field !field from the !entity !bundle content type - @error_message';

      throw new Exception($t($message, $replacements), $e->getCode(), $e);
    }

    General::messageSet($t($message, $replacements));
  }

  /**
   * Get Field Configuration Data.
   *
   * @param string $field_name
   *   Field name.
   *
   * @return array|bool
   *   Field configuration array else FALSE.
   *
   * @throws \Exception
   */
  public static function getFieldConfigData($field_name) {
    try {
      $query = '
        SELECT CAST(data AS CHAR(10000) CHARACTER SET utf8)
        FROM {field_config}
        WHERE field_name = :field_name
      ';
      $result = db_query($query, [':field_name' => $field_name]);
      $config = $result->fetchField();
    }
    catch (Exception $e) {
      // Pass on the exception with an explanation.
      $message = sprintf(
        'Failed to get field config data for %s : %s',
        $field_name, $e->getMessage()
      );
      throw new Exception($message, $e->getCode(), $e);
    }

    if ($config) {
      return unserialize($config);
    }

    return FALSE;
  }

}
