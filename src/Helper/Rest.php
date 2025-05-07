<?php
/**
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package ShipperHQ\Lib
 * @copyright Copyright (c) 2014 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace ShipperHQ\Lib\Helper;

/**
 * Class Helper
 *
 * @package ShipperHQ\Lib\Rate
 */
class Rest
{
    public $baseUrl = 'https://api.shipperhq.com/v1/';
    public $basePostorderUrl = 'https://postapi.shipperhq.com/v1/';

    /**
     * @param false $hideNotifications
     * @param false $transactionIdEnabled
     * @param null $locale
     */
    public function __construct($baseUrl = '')
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return string
     */
    public function getBasePostorderUrl()
    {
        return $this->basePostorderUrl;
    }

    /**
     * @param string $baseUrl
     */
    public function setBasePostorderUrl($baseUrl)
    {
        $this->basePostorderUrl = $baseUrl;
    }

    /**
     * Retrieve url for getting allowed methods
     * @return string
     */
    public function getAllowedMethodGatewayUrl()
    {
        return $this->getBaseUrl() . 'allowed_methods';
    }

    /**
     * Retrieve url for getting shipping rates
     * @return string
     */
    public function getRateGatewayUrl()
    {
        return  $this->getBaseUrl() . 'rates';
    }

    /*
     * *Retrieve url for retrieving attributes
     */
    public function getAttributeGatewayUrl()
    {
        return $this->getBaseUrl() . 'attributes/get';
    }

    /*
     * *Retrieve url for retrieving attributes
     */
    public function getCheckSynchronizedUrl()
    {
        return $this->getBaseUrl() . 'attributes/check';
    }

    /**
     * @return string
     */
    public function getPlaceOrderUrl()
    {
        return $this->getBasePostorderUrl() . 'placeorder';
    }
}
