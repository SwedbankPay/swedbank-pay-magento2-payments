<?php

namespace SwedbankPay\Payments\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use SwedbankPay\Payments\Model\Instrument\Collector\InstrumentCollector;

class InstrumentConfigVars implements ConfigProviderInterface
{

    /**
     * @var InstrumentCollector
     */
    protected $instrumentCollector;

    /**
     * InstrumentConfigVars constructor.
     * @param InstrumentCollector $instrumentCollector
     */
    public function __construct(
        InstrumentCollector $instrumentCollector
    ) {
        $this->instrumentCollector = $instrumentCollector;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'SwedbankPay_Payments_Instrument_List' => [
                'instruments' => $this->instrumentCollector->collectInstruments(),
                'active_instruments' => $this->instrumentCollector->collectActiveInstruments()
            ]
        ];
    }
}
