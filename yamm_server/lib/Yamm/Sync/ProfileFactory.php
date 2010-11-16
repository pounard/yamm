<?php
// $Id$

class Yamm_Sync_ProfileFactory extends XoxoExportableFactory
{
  /**
   * Get default profile internal name
   *
   * @return string
   */
  public function getDefaultProfile() {
    return variable_get('sync_profile_default', NULL);
  }

  /**
   * Set default profile internal name
   *
   * @param string $name
   * 
   * @return void
   */
  public function setDefaultProfile($name) {
    return variable_set('sync_profile_default', $name);
  }

  /**
   * (non-PHPdoc)
   * @see XoxoExportableFactory::exportCode()
   */
  public function exportCode($object) {
    $code = array();

    $code[] = "\$profile = new Yamm_Sync_Profile();";
    $code[] = "\$profile->setName('" . $object->getName() . "')";
    $code[] = "\$profile->setDescription('" . $object->getDescription() . "')";
    foreach ($object->getOptions() as $name => $value) {
      if (is_string($value)) {
        $code[] = "\$profile->setOption('" . $name . "', '" . $value . "');";
      }
      else if (is_numeric($value)) {
        $code[] = "\$profile->setOption('" . $name . "', " . $value . ");";
      }
      else {
        $code[] = "\$profile->setOption('" . $name . "', unserialize('" . serialize($value) . "'));";
      }
    }

    return implode("\n", $code);
  }
}
