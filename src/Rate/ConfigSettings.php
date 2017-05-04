<?php
/**
 *
 * ShipperHQ Shipping Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * Shipper HQ Shipping
 *
 * @category ShipperHQ
 * @package ShipperHQ_Lib
 * @copyright Copyright (c) 2014 Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */
namespace ShipperHQ\Lib\Rate;

/**
 * Class Helper
 *
 * @package ShipperHQ\Lib\Rate
 */
class ConfigSettings
{
    public $hideNotifications;
    public $transactionIdEnabled;
    public $locale;
    public $shipperHQCode;
    public $shipperHQTitle;
    public $timezone;

    /**
     * ConfigSettings constructor.
     * @param $hideNotifications
     * @param $transactionIdEnabled
     * @param $locale
     * @param $timezone
     * @param $shipperHQCode
     * @param $shipperHQTitle
     */
    public function __construct(
        $hideNotifications,
        $transactionIdEnabled,
        $locale,
        $shipperHQCode,
        $shipperHQTitle,
        $timezone
    ) {
        $this->hideNotifications = $hideNotifications;
        $this->transactionIdEnabled = $transactionIdEnabled;
        $this->locale = $locale;
        $this->shipperHQCode = $shipperHQCode;
        $this->shipperHQTitle = $shipperHQTitle;
        $this->timezone = $timezone;
    }

    /**
     * @param bool|false $hideNotifications
     */
    public function setHideNotifications($hideNotifications)
    {
        $this->hideNotifications = $hideNotifications;
    }

    /**
     * @return bool|false
     */
    public function getHideNotifications()
    {
        return $this->hideNotifications;
    }

    /**
     * @param null $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return null
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param bool|false $transactionIdEnabled
     */
    public function setTransactionIdEnabled($transactionIdEnabled)
    {
        $this->transactionIdEnabled = $transactionIdEnabled;
    }

    /**
     * @return bool|false
     */
    public function getTransactionIdEnabled()
    {
        return $this->transactionIdEnabled;
    }

    /**
     * @return mixed
     */
    public function getShipperHQCode()
    {
        return $this->shipperHQCode;
    }

    /**
     * @param mixed $shipperHQCode
     */
    public function setShipperHQCode($shipperHQCode)
    {
        $this->shipperHQCode = $shipperHQCode;
    }

    /**
     * @return mixed
     */
    public function getShipperHQTitle()
    {
        return $this->shipperHQTitle;
    }

    /**
     * @param mixed $shipperHQTitle
     */
    public function setShipperHQTitle($shipperHQTitle)
    {
        $this->shipperHQTitle = $shipperHQTitle;
    }

    /**
     * @return mixed
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @param mixed $timezone
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }
}
