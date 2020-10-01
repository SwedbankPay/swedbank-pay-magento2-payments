<?php

namespace SwedbankPay\Payments\Helper\Factory;

class SubresourceFactory
{

    /**
     * @param string $instrument
     * @param $subresource
     * @return mixed
     */
    public function create($instrument, $subresource)
    {
        $className =
            'SwedbankPay\\Api\\Service\\' .
            ucfirst($instrument) .
            '\\Resource\\Request\\' .
            ucfirst($subresource);

        return new $className();
    }
}
