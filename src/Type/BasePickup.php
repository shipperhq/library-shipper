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
     * @var \ShipperHQ\Lib\AdminOrder\AbstractService
     */
    protected $adminOrderService;

    /**
     * AbstractService $calendarService
     * @codeCoverageIgnore
     */
    public function __construct(
        \ShipperHQ\Lib\Checkout\AbstractService $checkoutService,
        \ShipperHQ\Lib\AdminOrder\AbstractService $adminOrderService
    ) {
        $this->checkoutService = $checkoutService;
        $this->adminOrderService = $adminOrderService;
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

    /*
    * Process date select action
    */
    public function processPickupAdminDateSelect(
        $locationSelected,
        $dateSelected,
        $carrierId,
        $carrierCode,
        $carrierGroupId,
        $cartId
    ) {
        $params = $this->getLocationSelectSaveParameters(
            $locationSelected,
            $dateSelected,
            $carrierId,
            $carrierCode,
            $carrierGroupId
        );
        $this->adminOrderService->saveSelectedData($params);
        $this->adminOrderService->cleanDownRates($cartId, $carrierCode, $carrierGroupId);
        try {
            $rates = $this->adminOrderService->reqeustShippingRates(
                $cartId,
                $carrierCode,
                $carrierGroupId
            );
        } catch (\Exception $e) {
            //handle so we can clean down rates if necessary
        }
        //need to do smart cleaning at this point
        $this->adminOrderService->cleanDownSelectedData();
        return $rates;
    }

    public function getLocationSelectSaveParameters(
        $locationSelected,
        $dateSelected,
        $carrierId,
        $carrierCode,
        $carrierGroupId
    ) {
        $selections = [
            'CarrierGroupId' => $carrierGroupId,
            'CarrierId' => $carrierId,
            'CarrierCode' => $carrierCode,
            'SelectedLocation' => $locationSelected,
            'SelectedDate' => $dateSelected
        ];
        return $selections;
    }

    public function processPickupDetails($carrierRate, $carrierGroupDetail)
    {
        $locationDetails = (array)$carrierRate['pickupLocationDetails'];
        $locationsAvailable = [];
        if (!empty($locationDetails) && isset($locationDetails['pickupLocations'])) {
            foreach ($locationDetails['pickupLocations'] as $location) {
                $locationAsArray =(array)$location;
                $calendarDetails = (array)$location['calendarDetails'];
                if (!empty($calendarDetails)) {
                    $defaultDate = intval($location['pickupDate']/1000);
                    $locale = isset($carrierGroupDetail['locale']) ? $carrierGroupDetail['locale'] : null;
                    $deliveryDateFormat = $carrierRate['deliveryDateFormat'];
                    $calendarDetails = $this->getCalendarDetailsArray(
                        $calendarDetails,
                        $carrierGroupDetail,
                        $carrierRate['carrierId'],
                        $carrierRate['carrierCode'],
                        $locale,
                        $deliveryDateFormat,
                        $defaultDate
                    );
                } else {
                    $calendarDetails['showDate'] = false;
                }
                $locationAsArray['calendarDetails'] = $calendarDetails;
                $locationAsArray['carrier_id'] = $carrierRate['carrierId'];
                $locationAsArray['carrier_code'] = $carrierRate['carrierCode'];
                $locationAsArray['distanceUnit'] = $carrierGroupDetail['distanceUnit'];
                $locationAsArray['showMap'] = $locationDetails['showMap'];
                $locationAsArray['showOpeningHours']  = $locationDetails['showOpeningHours'];
                $locationAsArray['showAddress']   = $locationDetails['showAddress'];
                $locationAsArray['googleApiKey']   = $locationDetails['googleApiKey'];
                $locationsAvailable[$location['pickupId']] = $locationAsArray;
            }
        }
        return $locationsAvailable;
    }
}
