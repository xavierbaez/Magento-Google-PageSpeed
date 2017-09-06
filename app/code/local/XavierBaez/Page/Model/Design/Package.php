<?php

/**
 * Class XavierBaez_Page_Model_Design_Package
 */
class XavierBaez_Page_Model_Design_Package extends Mage_Core_Model_Design_Package {

    /**
     * @param $dirRelativeName
     * @return bool|string
     */
    public function initMergerDir($dirRelativeName) {
        return $this->_initMergerDir($dirRelativeName);
    }
}