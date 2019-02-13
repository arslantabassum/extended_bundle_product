<?php

namespace PME\ProductBoxes\Model\Bundle;

use Magento\Framework\Exception\InputException;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Api\Data\ProductInterface;

class LinkManagement extends \Magento\Bundle\Model\LinkManagement
{
    /**
     * @var MetadataPool
     */
    private $metadataPool;


    /**
     * {@inheritdoc}
     */
    public function getChildren($productSku, $optionId = null)
    {
        $product = $this->productRepository->get($productSku, true);
        if (($product->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) && ($product->getTypeId() != 'box')) {
            throw new InputException(__('Only implemented for bundle product1'));
        }

        $childrenList = [];
        foreach ($this->getOptionsCustom($product) as $option) {
            if (!$option->getSelections() || ($optionId !== null && $option->getOptionId() != $optionId)) {
                continue;
            }
            /** @var \Magento\Catalog\Model\Product $selection */
            foreach ($option->getSelections() as $selection) {
                $childrenList[] = $this->buildLinkCustom($selection, $product);
            }
        }
        return $childrenList;
    }

    public function saveChild(
        $sku,
        \Magento\Bundle\Api\Data\LinkInterface $linkedProduct
    ) {
        $product = $this->productRepository->get($sku, true);
        if (($product->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) && ($product->getTypeId() != 'box')) {
            throw new InputException(
                __('Product with specified sku: "%1" is not a bundle product', [$product->getSku()])
            );
        }

        /** @var \Magento\Catalog\Model\Product $linkProductModel */
        $linkProductModel = $this->productRepository->get($linkedProduct->getSku());
        if ($linkProductModel->isComposite()) {
            throw new InputException(__('Bundle product could not contain another composite product'));
        }

        if (!$linkedProduct->getId()) {
            throw new InputException(__('Id field of product link is required'));
        }

        /** @var \Magento\Bundle\Model\Selection $selectionModel */
        $selectionModel = $this->bundleSelection->create();
        $selectionModel->load($linkedProduct->getId());
        if (!$selectionModel->getId()) {
            throw new InputException(__('Can not find product link with id "%1"', [$linkedProduct->getId()]));
        }
        $linkField = $this->getMetadataPoolCustom()->getMetadata(ProductInterface::class)->getLinkField();
        $selectionModel = $this->mapProductLinkToSelectionModel(
            $selectionModel,
            $linkedProduct,
            $linkProductModel->getId(),
            $product->getData($linkField)
        );

        try {
            $selectionModel->save();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save child: "%1"', $e->getMessage()), $e);
        }

        return true;
    }




    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function addChild(
        \Magento\Catalog\Api\Data\ProductInterface $product,
        $optionId,
        \Magento\Bundle\Api\Data\LinkInterface $linkedProduct
    ) {
        if (($product->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) && ($product->getTypeId() != 'box')) {
            throw new InputException(
                __('Product with specified sku: "%1" is not a bundle product', $product->getSku())
            );
        }

        $linkField = $this->getMetadataPoolCustom()->getMetadata(ProductInterface::class)->getLinkField();

        $options = $this->optionCollection->create();

        $options->setIdFilter($optionId);
        $options->setProductLinkFilter($product->getData($linkField));

        $existingOption = $options->getFirstItem();

        if (!$existingOption->getId()) {
            throw new InputException(
                __(
                    'Product with specified sku: "%1" does not contain option: "%2"',
                    [$product->getSku(), $optionId]
                )
            );
        }

        /* @var $resource \Magento\Bundle\Model\ResourceModel\Bundle */
        $resource = $this->bundleFactory->create();
        $selections = $resource->getSelectionsData($product->getData($linkField));
        /** @var \Magento\Catalog\Model\Product $linkProductModel */
        $linkProductModel = $this->productRepository->get($linkedProduct->getSku());
        if ($linkProductModel->isComposite()) {
            throw new InputException(__('Bundle product could not contain another composite product'));
        }

        if ($selections) {
            foreach ($selections as $selection) {
                if ($selection['option_id'] == $optionId &&
                    $selection['product_id'] == $linkProductModel->getEntityId() &&
                    $selection['parent_product_id'] == $product->getData($linkField)) {
                    if (!$product->getCopyFromView()) {
                        throw new CouldNotSaveException(
                            __(
                                'Child with specified sku: "%1" already assigned to product: "%2"',
                                [$linkedProduct->getSku(), $product->getSku()]
                            )
                        );
                    } else {
                        return $this->bundleSelection->create()->load($linkProductModel->getEntityId());
                    }
                }
            }
        }

        $selectionModel = $this->bundleSelection->create();
        $selectionModel = $this->mapProductLinkToSelectionModel(
            $selectionModel,
            $linkedProduct,
            $linkProductModel->getEntityId(),
            $product->getData($linkField)
        );
        $selectionModel->setOptionId($optionId);

        try {
            $selectionModel->save();
            $resource->addProductRelation($product->getData($linkField), $linkProductModel->getEntityId());
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save child: "%1"', $e->getMessage()), $e);
        }

        return $selectionModel->getId();
    }


    /**
     * {@inheritdoc}
     */
    public function removeChild($sku, $optionId, $childSku)
    {
        $product = $this->productRepository->get($sku, true);

        if (($product->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) && ($product->getTypeId() != 'box')) {
            throw new InputException(__('Product with specified sku: %1 is not a bundle product', $sku));
        }

        $excludeSelectionIds = [];
        $usedProductIds = [];
        $removeSelectionIds = [];
        foreach ($this->getOptionsCustom($product) as $option) {
            /** @var \Magento\Bundle\Model\Selection $selection */
            foreach ($option->getSelections() as $selection) {
                if ((strcasecmp($selection->getSku(), $childSku) == 0) && ($selection->getOptionId() == $optionId)) {
                    $removeSelectionIds[] = $selection->getSelectionId();
                    $usedProductIds[] = $selection->getProductId();
                    continue;
                }
                $excludeSelectionIds[] = $selection->getSelectionId();
            }
        }
        if (empty($removeSelectionIds)) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(
                __('Requested bundle option product doesn\'t exist')
            );
        }
        $linkField = $this->getMetadataPoolCustom()->getMetadata(ProductInterface::class)->getLinkField();
        /* @var $resource \Magento\Bundle\Model\ResourceModel\Bundle */
        $resource = $this->bundleFactory->create();
        $resource->dropAllUnneededSelections($product->getData($linkField), $excludeSelectionIds);
        $resource->removeProductRelations($product->getData($linkField), array_unique($usedProductIds));

        return true;
    }

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return \Magento\Bundle\Api\Data\OptionInterface[]
     */
    public function getOptionsCustom(\Magento\Catalog\Api\Data\ProductInterface $product)
    {
        /** @var \Magento\Bundle\Model\Product\Type $productTypeInstance */
        $productTypeInstance = $product->getTypeInstance();
        $productTypeInstance->setStoreFilter(
            $product->getStoreId(),
            $product
        );

        $optionCollection = $productTypeInstance->getOptionsCollection($product);

        $selectionCollection = $productTypeInstance->getSelectionsCollection(
            $productTypeInstance->getOptionsIds($product),
            $product
        );

        $options = $optionCollection->appendSelections($selectionCollection, true);
        return $options;
    }

    /**
     * @param \Magento\Catalog\Model\Product $selection
     * @param \Magento\Catalog\Model\Product $product
     * @return \Magento\Bundle\Api\Data\LinkInterface
     */
    public function buildLinkCustom(\Magento\Catalog\Model\Product $selection, \Magento\Catalog\Model\Product $product)
    {
        $selectionPriceType = $selectionPrice = null;

        /** @var \Magento\Bundle\Model\Selection $product */
        if ($product->getPriceType()) {
            $selectionPriceType = $selection->getSelectionPriceType();
            $selectionPrice = $selection->getSelectionPriceValue();
        }

        /** @var \Magento\Bundle\Api\Data\LinkInterface $link */
        $link = $this->linkFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $link,
            $selection->getData(),
            \Magento\Bundle\Api\Data\LinkInterface::class
        );
        $link->setIsDefault($selection->getIsDefault())
            ->setId($selection->getSelectionId())
            ->setQty($selection->getSelectionQty())
            ->setCanChangeQuantity($selection->getSelectionCanChangeQty())
            ->setPrice($selectionPrice)
            ->setPriceType($selectionPriceType);
        return $link;
    }

    /**
     * Get MetadataPool instance
     * @return MetadataPool
     */
    public function getMetadataPoolCustom()
    {
        if (!$this->metadataPool) {
            $this->metadataPool = ObjectManager::getInstance()->get(MetadataPool::class);
        }
        return $this->metadataPool;
    }




}