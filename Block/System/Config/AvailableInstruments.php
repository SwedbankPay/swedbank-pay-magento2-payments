<?php

namespace SwedbankPay\Payments\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Payments\Helper\Config;
use SwedbankPay\Payments\Model\Instrument\Collector\InstrumentCollector;

/**
 * Class AvailableInstruments

 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class AvailableInstruments extends Field
{
    const CONFIG_PATH = 'payment/swedbank_pay_payments/available_instruments';

    // phpcs:disable
    /**
     * @var string
     */
    protected $_template = 'SwedbankPay_Payments::system/config/available_instruments_checkbox.phtml';

    /**
     * @var array|null
     *
     */
    protected $_values = null;

    // phpcs:enable
    /**
     * @var InstrumentCollector
     */
    protected $instrumentCollector;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * AvailableInstruments constructor.
     * @param Context $context
     * @param InstrumentCollector $instrumentCollector
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Logger $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        InstrumentCollector $instrumentCollector,
        StoreManagerInterface $storeManager,
        Config $config,
        Logger $logger,
        array $data = []
    ) {
        $this->instrumentCollector = $instrumentCollector;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->logger = $logger;

        parent::__construct($context, $data);
    }

    // phpcs:disable
    /**
     * Retrieve element HTML markup.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        // phpcs:enable
        $this->setNamePrefix($element->getName())
            ->setHtmlId($element->getHtmlId());

        return $this->_toHtml();
    }

    /**
     * @return array
     */
    public function getValues()
    {
        $instruments = $this->instrumentCollector->collectInstruments();

        return array_reduce($instruments, function (&$result, $instrument) {
            $result[$instrument['name']] = $instrument['pretty_name'];
            return $result;
        }, []);
    }

    /**
     * @param $name
     * @return bool
     */
    public function isChecked($name)
    {
        return in_array($name, $this->getCheckedValues());
    }

    /**
     * Gets the checked values from the config
     *
     * @return array|null
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function getCheckedValues()
    {
        if ($this->_values === null) {
            $data = $this->getConfigData();

            if (isset($data[self::CONFIG_PATH])) {
                $data = $data[self::CONFIG_PATH];
            } else {
                $data = '';
            }

            $this->_values = explode(',', $data);
        }

        return $this->_values;
    }

    /**
     * @return bool
     */
    public function isDisabled()
    {
        return false;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getCheckedHtml($name)
    {
        return $this->isChecked($name) ? ' checked="checked"' : '';
    }

    /**
     * @return string
     */
    public function getDisabledHtml()
    {
        return $this->isDisabled() ? ' disabled="disabled"' : '';
    }
}
