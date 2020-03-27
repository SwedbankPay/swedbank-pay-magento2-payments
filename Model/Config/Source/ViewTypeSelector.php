<?php

namespace SwedbankPay\Payments\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ViewTypeSelector implements ArrayInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'hosted_view', 'label' => __('Hosted View')],
            ['value' => 'redirect_view', 'label' => __('Redirect View')]
        ];
    }
}
