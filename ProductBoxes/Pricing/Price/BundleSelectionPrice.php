<?php

namespace PME\ProductBoxes\Pricing\Price;

Class BundleSelectionPrice extends \Magento\Bundle\Pricing\Price\BundleSelectionPrice
{
    /**
     * Get Price Amount object
     *
     * @return AmountInterface
     */
    public function getAmount()
    {
        $product = $this->selection;
        $bundleSelectionKey = 'bundle-selection'
            . ($this->useRegularPrice ? 'regular-' : '')
            . '-amount-'
            . $product->getSelectionId();
        if ($product->hasData($bundleSelectionKey)) {
            return $product->getData($bundleSelectionKey);
        }
        $value = $this->getValue();
        if (!isset($this->amount[$value])) {
            $exclude = null;
            if ($this->getProduct()->getTypeId() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE || $this->getProduct()->getTypeId() == 'box') {
                $exclude = $this->excludeAdjustment;
            }
            $this->amount[$value] = $this->calculator->getAmount(
                $value,
                $this->getProduct(),
                $exclude
            );
            $product->setData($bundleSelectionKey, $this->amount[$value]);
        }
        return $this->amount[$value];
    }
}