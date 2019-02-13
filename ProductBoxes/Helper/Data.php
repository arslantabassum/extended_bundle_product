<?php

namespace PME\ProductBoxes\Helper;

Class Data extends \Magento\Bundle\Helper\Data
{
    /**
     * Retrieve array of allowed product types for bundle selection product
     *
     * @return array
     */
    public function getAllowedSelectionTypes()
    {
        $configData = $this->config->getType('box');

        return isset($configData['allowed_selection_types']) ? $configData['allowed_selection_types'] : [];
    }
}