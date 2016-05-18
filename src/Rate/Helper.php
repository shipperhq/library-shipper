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
class Helper
{
    CONST CALENDAR_DATE_OPTION = 'calendar';
    CONST DELIVERY_DATE_OPTION = 'delivery_date';
    CONST TIME_IN_TRANSIT = 'time_in_transit';

    CONST DEFAULT_DELIVERY_MESSAGE = 'Delivers: ';
    CONST DEFAULT_TRANSIT_MESSAGE = 'business day(s): ';
    CONST DEFAULT_DATE_FORMAT = 'yMd';


    public function getDeliveryMessage($carrierRate, $dateOption)
    {
        $message = self::DEFAULT_DELIVERY_MESSAGE;
        if($dateOption == self::TIME_IN_TRANSIT) {
            $message = self::DEFAULT_TRANSIT_MESSAGE;
        }
        else if($dateOption == self::DELIVERY_DATE_OPTION && isset($carrierRate->deliveryDateMessage)) {
            $message = $carrierRate->deliveryDateMessage;
        }
        return $message;
    }

    public function getDateFormat()
    {
        return self::DEFAULT_DATE_FORMAT;
    }

    public function populateRateDeliveryDetails($rate, &$carrierGroupDetail, &$methodDescription, $dateFormat, $dateOption, $deliveryMessage)
    {
        if(isset($rate['deliveryDate']) && is_numeric($rate['deliveryDate'])) {
            $date = new \DateTime();
            $date->setTimestamp($rate['deliveryDate']/1000);
            $deliveryDate = $date->format($dateFormat);

            if($dateOption == self::DELIVERY_DATE_OPTION) {
                $methodDescription = " $deliveryMessage $deliveryDate";
                if($rate['latestDeliveryDate'] && is_numeric($rate['latestDeliveryDate'])) {
                    $latestdate = new \DateTime();
                    $latestdate->setTimestamp($rate['latestDeliveryDate']/1000);
                    $latestDeliveryDate = $latestdate->format($dateFormat);
                    $methodDescription.= ' - ' .$latestDeliveryDate;
                }
            }
            else if($dateOption == self::TIME_IN_TRANSIT
                && isset($rate['dispatchDate'])) {
                $numDays = floor(abs($rate['deliveryDate']/1000 - $rate['dispatchDate']/1000)/60/60/24);

                if($rate['latestDeliveryDate'] && is_numeric($rate['latestDeliveryDate'])) {
                    $maxNumDays = floor(abs($rate['latestDeliveryDate']/1000 - $rate->dispatchDate/1000)/60/60/24);
                    $methodDescription = " ($numDays - $maxNumDays $deliveryMessage)";
                }
                else {
                    $methodDescription = " ($numDays $deliveryMessage)";
                }
            }
            $carrierGroupDetail['delivery_date'] = $deliveryDate;
        }
        if(isset($rate['dispatchDate']) && is_numeric($rate['dispatchDate'])) {
            $dispatch = new \DateTime();
            $dispatch->setTimestamp($rate['dispatchDate']/1000);
            $dispatchDate = $dispatch->format($dateFormat);
            $carrierGroupDetail['dispatch_date'] = $dispatchDate;
        }
    }
}