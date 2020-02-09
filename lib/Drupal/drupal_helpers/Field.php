<?php

namespace Drupal\drupal_helpers;

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
   */
  public static function delete($field_name) {
    try {
      $field = field_info_field($field_name);

      if (!$field) {
        Utility::message('Skipped: "@field_name" was not found', [
          '@field_name' => $field_name,
        ]);

        return;
      }

      if (isset($field['bundles']) && is_array($field['bundles'])) {
        foreach ($field['bundles'] as $entity_type => $bundles) {
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

      Utility::message('The field @field_name has been deleted.', [
        '@field_name' => $field_name,
      ]);
    }
    catch (\Exception $e) {
      throw new DrupalHelpersException('Failed to delete "@field_name"', [
        '@field_name' => $field_name,
      ], $e);
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
   */
  public static function deleteInstance($field_name, $entity_type, $entity_bundle) {
    $replacements = [
      '@field_name' => $field_name,
      '@entity' => $entity_type,
      '@bundle' => $entity_bundle,
    ];

    try {
      $instance = field_info_instance($entity_type, $field_name, $entity_bundle);
      if ($instance) {
        field_delete_instance($instance, FALSE);
        Utility::message('Success: deleted the field @field_name from the @entity @bundle content type.', $replacements);
      }
      else {
        Utility::message('Skipped: the @field_name was not found for the @entity @bundle content type.', $replacements);
      }
    }
    catch (\Exception $e) {
      throw new DrupalHelpersException('Failed to remove the field @field_name from the @entity @bundle content type.', $replacements, $e);
    }
  }

  /**
   * Get Field Configuration Data.
   *
   * @param string $field_name
   *   Field name.
   *
   * @return array|bool
   *   Field configuration array else FALSE.
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
    catch (\Exception $e) {
      throw new DrupalHelpersException('Failed to get field config data for @field_name', ['@field_name' => $field_name], $e);
    }

    return $config ? unserialize($config) : FALSE;
  }

  /**
   * Set Field Configuration Data.
   *
   * @param string $field_name
   *   Field name.
   * @param array $config
   *   Field configuration array.
   */
  public static function setFieldConfigData($field_name, array $config) {
    try {
      $data = serialize($config);
      db_update('field_config')
        ->fields(['data' => $data])
        ->condition('field_name', $field_name)
        ->execute();
    }
    catch (\Exception $e) {
      throw new DrupalHelpersException('Failed to set field config data for @field_name', ['@field_name' => $field_name], $e);
    }
  }

  /**
   * Change Text max length of the text field.
   *
   * Change the max length of a Text field, even if it contains content. Any
   * text content longer than the new max length will be trimmed permanently.
   *
   * All changes are rolled back if there is a failure.
   *
   * @param string $field_name
   *   Field name.
   * @param int $length
   *   Field length in characters.
   */
  public static function changeTextFieldMaxLength($field_name, $length) {
    $db_transaction = db_transaction();

    try {
      // Modify field data and revisions.
      foreach (['field_data', 'field_revision'] as $prefix) {
        $table_name = "{$prefix}_{$field_name}";
        self::modifyTextFieldValueLength($table_name, $field_name, $length);
      }
      // Update field config.
      self::updateTextFieldConfigMaxLength($field_name, $length);
    }
    catch (\Exception $e) {
      // Something went wrong, so roll back all changes.
      $db_transaction->rollback();

      throw new DrupalHelpersException('Failed to change field @field_name max length to @length', [
        '@field_name' => $field_name,
        '@length' => $length,
      ], $e);
    }

    Utility::message('Text field @field_name max length changed to @length', [
      '@field_name' => $field_name,
      '@length' => $length,
    ]);
  }

  /**
   * Modify a Text Field Table Value Column Length.
   *
   * @param string $table_name
   *   The name of the table that contains the field.
   * @param string $field_name
   *   The name of the field being resized.
   * @param int $length
   *   Field length in characters.
   */
  protected static function modifyTextFieldValueLength($table_name, $field_name, $length) {
    $field_value_column = $field_name . '_value';
    $query_alter = sprintf(
      'ALTER TABLE {%s} MODIFY %s VARCHAR(%d)',
      $table_name, $field_value_column, $length
    );
    db_query($query_alter);
  }

  /**
   * Update Text Field Configuration for Max Length.
   *
   * @param string $field_name
   *   The field name being updated.
   * @param int $length
   *   Field length in characters.
   */
  protected static function updateTextFieldConfigMaxLength($field_name, $length) {
    $config = self::getFieldConfigData($field_name);

    if (!is_array($config)) {
      throw new DrupalHelpersException('No config data found for field @field_name', ['@field_name' => $field_name]);
    }

    $config['settings']['max_length'] = $length;
    self::setFieldConfigData($field_name, $config);
  }

}
