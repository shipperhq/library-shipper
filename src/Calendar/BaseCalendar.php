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
namespace ShipperHQ\Lib\Calendar;

/**
 * Class BaseCalendar
 *
 * @package ShipperHQ_Lib
 */
class BaseCalendar
{
    /**
     * @var AbstractService
     */
    protected $checkoutService;

    /**
     * AbstractService $calendarService
     * @codeCoverageIgnore
     */
    public function __construct(
        AbstractService $checkoutService
    ) {
        $this->checkoutService = $checkoutService;
    }

    /*
     * Process date select action
     */
    public function processDateSelect($dateSelected, $carrierId, $carrierCode, $carrierGroupId, $addressId = false)
    {
        $params = $this->getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId);
        $this->checkoutService->saveSelectedData($params);
        $this->checkoutService->cleanDownRates($carrierCode, $carrierGroupId, $addressId);
        $rates = $this->checkoutService->reqeustShippingRates($carrierCode, $carrierGroupId, $addressId);
        $this->checkoutService->cleanDownSelectedData();
        return $rates;
    }

    public function getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId)
    {
        $selections = new \ShipperHQ\Lib\Rate\CarrierSelections($carrierGroupId, $carrierCode, $carrierId);
        $selections->setSelectedDate($dateSelected);

        return $selections;

    }

}
