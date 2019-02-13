<?php

namespace PME\ProductBoxes\Model\Bundle;

use Magento\Framework\Exception\InputException;

class OptionManagement extends \Magento\Bundle\Model\OptionManagement
{
    /**
     * {@inheritdoc}
     */
    public function save(\Magento\Bundle\Api\Data\OptionInterface $option)
    {
        $product = $this->productRepository->get($option->getSku(), true);
        if (($product->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) && ($product->getTypeId() != 'box')) {
            throw new InputException(__('Only implemented for bundle && box product2'));
        }
        return $this->optionRepository->save($product, $option);
    }

}