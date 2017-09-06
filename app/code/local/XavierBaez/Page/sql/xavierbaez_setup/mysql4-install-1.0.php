<?php

$installer = $this;

$installer->startSetup();

Mage::getModel('core/config')->saveConfig('design/head/default_robots', "NOODP,NOYDIR");

$installer->endSetup();
