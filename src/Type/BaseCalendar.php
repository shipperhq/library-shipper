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
 * Class BaseCalendar
 *
 * @package ShipperHQ_Lib
 */
class BaseCalendar
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
     * Process date select action
     */
    public function processDateSelect($dateSelected, $carrierId, $carrierCode, $carrierGroupId, $addressId = false)
    {
        $params = $this->getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId);
        $this->checkoutService->saveSelectedData($params);
        $this->checkoutService->cleanDownRates($carrierCode, $carrierGroupId, $addressId);
        $rates = $this->checkoutService->reqeustShippingRates($carrierCode, $carrierGroupId, $addressId);
        //need to do smart cleaning at this point
        $this->checkoutService->cleanDownSelectedData();
        return $rates;
    }

    public function getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId)
    {
        $selections = new \ShipperHQ\Lib\Rate\CarrierSelections($carrierGroupId, $carrierCode, $carrierId);
        $selections->setSelectedDate($dateSelected);

        return $selections;

    }

    public function processCalendarDetails($carrierRate, $carrierGroupDetail)
    {
        $calendarDetails = (array)$carrierRate->calendarDetails;
        //need to convert this to cldrFormat
        $locale = isset($carrierGroupDetail['locale']) ? $carrierGroupDetail['locale'] : null;
        $deliveryDateFormat = $carrierRate->deliveryDateFormat;

        foreach($carrierRate->rates as $rate) {
            $defaultDate = $rate->deliveryDate/1000;
            break;
        }
        if(!empty($calendarDetails)) {
            $calendarDetails = $this->getCalendarDetailsArray($calendarDetails, $carrierGroupDetail, $carrierRate->carrierId,
                $carrierRate->carrierCode, $locale, $deliveryDateFormat, $defaultDate);
            $defaultDate = date($calendarDetails['dateFormat'], $defaultDate);
            $calendarDetails['default_date'] = $defaultDate;
        }

        return $calendarDetails;

    }

    public function getCalendarDetailsArray($calendarDetails, $carrierGroupDetail, $carrierId, $carrierCode, $locale, $deliveryDateFormat, $defaultDate)
    {
        $calendarDetails['dateFormat'] = \ShipperHQ\Lib\Helper\Date::getDateFormat($locale);
        $calendarDetails['datepickerFormat'] = \ShipperHQ\Lib\Helper\Date::getDatepickerFormat($locale);
        $calendarDetails['displayDateFormat'] = \ShipperHQ\Lib\Helper\Date::getCldrDateFormat($locale, $deliveryDateFormat);
        $calendarDetails['timezone'] = $carrierGroupDetail['timezone'];
        if($calendarDetails['startDate'] != '') {
            $calendarDetails['start'] = $calendarDetails['startDate']/1000;

        }
        else {
            $calendarDetails['start'] = $defaultDate;
        }
        $calendarDetails['carrier_id'] = $carrierId;
        $calendarDetails['carrier_code'] = $carrierCode;
        $dateOptions = $this->getDateOptions($calendarDetails);
        if (count($dateOptions)>0) {
            $deliveryDatesAndTimes = array();
            if(isset($calendarDetails['timeSlots']) && !empty($calendarDetails['timeSlots'])) {
                foreach($dateOptions as $dateKey => $date) {
                    //TODO account for selected date
//                    if(array_key_exists('date_selected', $resultSet) && $resultSet['date_selected'] != '') {
//                        if ($dateKey != $resultSet['date_selected']) continue;
//
//                    }
                    if($slotsFound = $this->getDeliveryTimeSlots($calendarDetails, $dateKey)) {
                        $deliveryDatesAndTimes[$dateKey] = $slotsFound;
                       // break;
                    }
                    else {
                        unset($dateOptions[$dateKey]);
                    }
                }
            }

            if(count($dateOptions) <= 0 ) {
                //TODO properly handle if no date options found
                $dateOptions = [];
            }

            if(count($deliveryDatesAndTimes) > 0) {
                $calendarDetails['display_time_slots']= $deliveryDatesAndTimes;
             $calendarDetails['showTimeslots'] = true;
            }
            else {
                $calendarDetails['display_time_slots'] = false;
                $calendarDetails['showTimeslots'] = false;
            }
        }
        $calendarDetails['allowed_dates'] = $dateOptions;
        $keys = array_keys($dateOptions);
        $calendarDetails['min_date'] = $keys[0];
        $calendarDetails['max_date'] = end($keys);

        return $calendarDetails;

    }

    /**
     * Returns back an array of dates for Store Pickup
     * These dates are not influenced by the location
     * @return array
     */
    public function getDateOptions($calendarDetails)
    {
        $numPickupDays = array_key_exists('maxDays', $calendarDetails) ? $calendarDetails['maxDays'] : 10;

        //Convert java linux timestamps (milliseconds) into php linux timestamps (seconds)

        $dateOptions = [];
        $dateFormat = $calendarDetails['dateFormat'];
        $timezone = $calendarDetails['timezone'];
        $startDate = $calendarDetails['start'];
        $endDate = isset($calendarDetails['calendarEndDate']) ?
            $calendarDetails['calendarEndDate']/1000
            : false;

        $arrBlackoutDates = [];
        foreach($calendarDetails['blackoutDates'] as $blackoutDate)
        {
            $arrBlackoutDates[] = $this->getDateFromDate($blackoutDate, $timezone, $dateFormat);
        };
        $arrBlackoutDays = array();
        foreach($calendarDetails['blackoutDays'] as $dayOfWeek)
        {
            //Java Sunday = 7, Monday = 1. PHP Monday = 1, Saturday = 6, Sunday = 0
            if($dayOfWeek == 7) {
                $dayOfWeek = 0;
            }
            $arrBlackoutDays[] = $dayOfWeek;
        }
        if(count($arrBlackoutDays) == 7 ) {
            //TODO somehow flag that there are no date options available
//            if(Mage::helper('shipperhq_shipper')->isDebug()) {
//                Mage::helper('wsalogger/log')->postWarning('Shipperhq Calendar', 'No date options available ', 'All days of week are set as black out days for carrier');
//            }
            return $dateOptions;
        }
        while(count($dateOptions) < $numPickupDays) {
            //support end date inclusive
            if($endDate && $startDate > $endDate) {
                break;
            }

            $nextDay = $this->getDateFromTimestamp($startDate, $timezone, $dateFormat);

            // Blackout day or date...get next available
            if(in_array($nextDay, $arrBlackoutDates) ||
                in_array($this->getDayOfWeekFromTimestamp($startDate, $timezone), $arrBlackoutDays)) {
                $this->_addDay($startDate);
                continue;
            }
            $dateOptions[$nextDay] = $nextDay;
            $this->_addDay($startDate);
        }
        return $dateOptions;
    }

    public function getDeliveryTimeSlots($calendarDetails, $date)
    {

        if(!isset($calendarDetails['timeSlots']))
        {
            return false;
        }
        $timezone = $calendarDetails['timezone'];
        $dateFormat = $calendarDetails['dateFormat'];
        $today = $this->getCurrentDate($timezone, $dateFormat);

        $isToday = false;
        if($today == $date) {
            $isToday = true;
        }

        $timeSlotDetail = (array)$calendarDetails['timeSlots'];
        $sortTime = array();
        foreach ($timeSlotDetail as $key => $val) {
            $values = (array)$val;
            $sortTime[$key] = $values['timeStart'];
            $timeSlotDetail[$key] = $values;
        }

        array_multisort($sortTime, SORT_ASC, $timeSlotDetail);

        //for implementation of date/day based slot detail in future
        $timeSlots = array();
        foreach($timeSlotDetail as $timeSlotDetail) {
            $start = $timeSlotDetail['timeStart'];
            $end =  $timeSlotDetail['timeEnd'];
            $interval = $timeSlotDetail['interval'];

            $startTime = strtotime($start);
            $endTime = strtotime($end);

            if(!$startTime || !$endTime) {
                continue;
            }

            $currentTime = 0;
            //if we are generating slots for today, make sure we don't offer any in the past
            //and we account for lead time in hours
            if($isToday) {
                $currentTimeClass = new \DateTime("now", new \DateTimeZone($timezone));
                $currentTime = $currentTimeClass->getTimestamp();
            }

            //if interval is half or full day then calculate those intervals
            if($interval <= 2) {
                $interval = (($endTime - $startTime)/60)/$interval;
            }
            $intStartTime = $startTime;
            $intEndTime = $startTime;
            $intervalString = '+' . $interval . ' minutes';

            while ($endTime > $intStartTime) {
                $intEndTime = strtotime($intervalString, $intStartTime);
                if ($intEndTime > $endTime) {
                    $intEndTime = $endTime;
                }
                //will ignore any time slots in the past
                if($intStartTime > $currentTime) {
                    $timeSlots[date('H:i:s', $intStartTime) . '_' . date('H:i:s', $intEndTime)] = date('g:i a', $intStartTime) . ' - ' . date('g:i a', $intEndTime);
                }
                $intStartTime = $intEndTime;

            }
        }

        if(count($timeSlots) == 0) {
            return false;
        }
        return $timeSlots;

    }

    public function getDateFromDate($date, $timezone, $dateFormat)
    {
        $returnDate = $this->getDateFromTimestamp(strtotime($date), $timezone, $dateFormat);
        return $returnDate;
    }

    public function getCurrentDate($timezone, $dateFormat)
    {
        $dateTime = new \DateTime("now", new \DateTimeZone($timezone));
        $returnDate = $dateTime->format($dateFormat);

        return $returnDate;

    }

    public function getDateFromTimestamp($timeStamp, $timezone, $dateFormat)
    {
        $dateTime = new \DateTime("now", new \DateTimeZone($timezone));
        $dateTime->setTimestamp($timeStamp);
        $returnDate = $dateTime->format($dateFormat);

        return $returnDate;
    }


    /**
     * Get a numerical representation of the day of the week from a date
     *
     * @param string $date
     * @return bool|string
     */
    public function getDayOfWeekFromDate($date, $timezone){
        $unixTime = strtotime($date);
        $dayOfWeek = $this->getDateFromTimestamp($unixTime, $timezone, 'N');
        return $dayOfWeek;
    }

    /**
     * Get a numerical representation of the day of the week from a timestamp
     *
     * @param string $date
     * @return bool|string
     */
    public function getDayOfWeekFromTimestamp($timestamp, $timezone){
        $dayOfWeek = $this->getDateFromTimestamp($timestamp, $timezone, 'N');
        return $dayOfWeek;
    }

    /**
     * Given a date will add a day to it.
     * @param $day
     * @param int $numDaysToAdd
     */
    protected function _addDay(&$day,$numDaysToAdd = 1) {
        $day = strtotime('+' .$numDaysToAdd .' day', $day);
        return $day;

    }
}
