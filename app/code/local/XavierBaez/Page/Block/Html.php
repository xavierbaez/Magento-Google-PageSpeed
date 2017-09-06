<?php

class XavierBaez_Mage_Page_Block_Html extends Mage_Page_Block_Html {

    /**
     * @return mixed
     */
    public function getFeaturedProductHtml()
    {
        return $this->getBlockHtml('product_featured');
    }

}