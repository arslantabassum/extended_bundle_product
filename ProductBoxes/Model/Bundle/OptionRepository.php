<?php

namespace PME\ProductBoxes\Model\Bundle;

use Magento\Framework\Exception\InputException;

class OptionRepository extends \Magento\Bundle\Model\OptionRepository
{

    /**
     * {@inheritdoc}
     */
    public function get($sku, $optionId)
    {
        $product = $this->getProductCustom($sku);

        /** @var \Magento\Bundle\Model\Option $option */
        $option = $this->type->getOptionsCollection($product)->getItemById($optionId);
        if (!$option || !$option->getId()) {
            throw new NoSuchEntityException(__('Requested option doesn\'t exist'));
        }

        $productLinks = $this->linkList->getItems($product, $optionId);

        /** @var \Magento\Bundle\Api\Data\OptionInterface $option */
        $optionDataObject = $this->optionFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $optionDataObject,
            $option->getData(),
            \Magento\Bundle\Api\Data\OptionInterface::class
        );
        $optionDataObject->setOptionId($option->getId())
            ->setTitle($option->getTitle() === null ? $option->getDefaultTitle() : $option->getTitle())
            ->setSku($product->getSku())
            ->setProductLinks($productLinks);

        return $optionDataObject;
    }

    /**
     * {@inheritdoc}
     */
    public function getList($sku)
    {
        $product = $this->getProductCustom($sku);
        return $this->getListByProduct($product);
    }


    /**
     * {@inheritdoc}
     */
    public function deleteById($sku, $optionId)
    {
        $product = $this->getProductCustom($sku);
        $optionCollection = $this->type->getOptionsCollection($product);
        $optionCollection->setIdFilter($optionId);
        return $this->delete($optionCollection->getFirstItem());
    }



    public function getProductCustom($sku)
    {
        $product = $this->productRepository->get($sku, true);
        if (($product->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) && ($product->getTypeId() != 'box')) {
            throw new InputException(__('Only implemented for bundle & box product3'));
        }
        return $product;
    }
}