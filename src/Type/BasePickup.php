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
namespace ShipperHQ\Lib\Type;

/**
 * Class BasePickup
 *
 * @package ShipperHQ_Lib
 */
class BasePickup extends BaseCalendar
{
    /**
     * @var \ShipperHQ\Lib\Checkout\AbstractService
     */
    protected $checkoutService;

    /**
     * AbstractService $calendarService
     * @codeCoverageIgnore
     */
    public function __construct(
        \ShipperHQ\Lib\Checkout\AbstractService $checkoutService
    ) {
        $this->checkoutService = $checkoutService;
    }

    /*
     * Process location select action
     */
    public function processLocationSelect(
        $locationSelected,
        $dateSelected,
        $carrierId,
        $carrierCode,
        $carrierGroupId,
        $addressId = false
    ) {
        $params = $this->getLocationSelectSaveParameters(
            $locationSelected,
            $dateSelected,
            $carrierId,
            $carrierCode,
            $carrierGroupId
        );
        $this->checkoutService->saveSelectedData($params);
        $this->checkoutService->cleanDownRates($carrierCode, $carrierGroupId, $addressId);
        $rates = $this->checkoutService->reqeustShippingRates($carrierCode, $carrierGroupId, $addressId);
        //need to do smart cleaning at this point
        $this->checkoutService->cleanDownSelectedData();
        return $rates;
    }

    public function getLocationSelectSaveParameters(
        $locationSelected,
        $dateSelected,
        $carrierId,
        $carrierCode,
        $carrierGroupId
    ) {
        $selections = new \ShipperHQ\Lib\Rate\CarrierSelections($carrierGroupId, $carrierCode, $carrierId);
        $selections->setSelectedLocation($locationSelected);
        $selections->setSelectedDate($dateSelected);

        return $selections;
    }

    public function processPickupDetails($carrierRate, $carrierGroupDetail)
    {
        $locationDetails = (array)$carrierRate->pickupLocationDetails;
        $locationsAvailable = [];
        if (!empty($locationDetails) && isset($locationDetails['pickupLocations'])) {
            foreach ($locationDetails['pickupLocations'] as $location) {
                $locationAsArray =(array)$location;
                $calendarDetails = (array)$location->calendarDetails;
                if (!empty($calendarDetails)) {
                    $defaultDate = $location->pickupDate/1000;
                    $locale = isset($carrierGroupDetail['locale']) ? $carrierGroupDetail['locale'] : null;
                    $deliveryDateFormat = $carrierRate->deliveryDateFormat;
                    $calendarDetails = $this->getCalendarDetailsArray(
                        $calendarDetails,
                        $carrierGroupDetail,
                        $carrierRate->carrierId,
                        $carrierRate->carrierCode,
                        $locale,
                        $deliveryDateFormat,
                        $defaultDate
                    );
                } else {
                    $calendarDetails['showDate'] = false;
                }
                $locationAsArray['calendarDetails'] = $calendarDetails;
                $locationAsArray['carrier_id'] = $carrierRate->carrierId;
                $locationAsArray['carrier_code'] = $carrierRate->carrierCode;
                $locationAsArray['distanceUnit'] = $carrierGroupDetail['distanceUnit'];
                $locationsAvailable[$location->pickupId] = $locationAsArray;
            }
        }
        return $locationsAvailable;
    }
}
