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
     * Process date select action
     */
    public function processDateSelect(
        $dateSelected,
        $carrierId,
        $carrierCode,
        $carrierGroupId,
        $addressData,
        $cartId,
        $addressId = false
    ) {
        $params = $this->getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId);
        $this->checkoutService->saveSelectedData($params);
        $this->checkoutService->cleanDownRates($cartId, $carrierCode, $carrierGroupId, $addressId);
        $addressArray = ['street' => $addressData->getStreet(), 'region' => $addressData->getRegion(),
                'region_id' => $addressData->getRegionId(), 'postcode' => $addressData->getPostcode(),
                'country_id' => $addressData->getCountryId()];
        try {
            $rates = $this->checkoutService->reqeustShippingRates(
                $cartId,
                $carrierCode,
                $carrierGroupId,
                $addressArray,
                $addressId
            );
        } catch (\Exception $e) {
            //handle so we can clean down rates if necessary
        }
        //need to do smart cleaning at this point
        $this->checkoutService->cleanDownSelectedData();
        return $rates;
    }

    /*
    * Process date select action
    */
    public function processAdminDateSelect(
        $dateSelected,
        $carrierId,
        $carrierCode,
        $carrierGroupId,
        $cartId
    ) {
        $params = $this->getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId);
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

    public function saveDateSelectOnCheckoutProceed($dateSelected, $carrierId, $carrierCode, $carrierGroupId)
    {
        $params = $this->getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId);
        $this->checkoutService->saveSelectedData($params);
    }

    public function resetDateSelectOnCheckoutProceed()
    {
        $this->checkoutService->cleanDownSelectedData(['SelectedDate']);
    }

    public function getDateSelectSaveParameters($dateSelected, $carrierId, $carrierCode, $carrierGroupId)
    {
        $selections = [
            'CarrierGroupId' => $carrierGroupId,
            'CarrierId' => $carrierId,
            'CarrierCode' => $carrierCode,
            'SelectedDate' => $dateSelected
        ];
        return $selections;
    }

    public function processCalendarDetails($carrierRate, $carrierGroupDetail)
    {
        $calendarDetails = (array)$carrierRate->calendarDetails;
        //need to convert this to cldrFormat
        $locale = isset($carrierGroupDetail['locale']) ? $carrierGroupDetail['locale'] : null;
        $deliveryDateFormat = $carrierRate->deliveryDateFormat;

        foreach ($carrierRate->rates as $rate) {
            $defaultDate = $rate->deliveryDate/1000;
            break;
        }
        if (!empty($calendarDetails)) {
            $calendarDetails = $this->getCalendarDetailsArray(
                $calendarDetails,
                $carrierGroupDetail,
                $carrierRate->carrierId,
                $carrierRate->carrierCode,
                $locale,
                $deliveryDateFormat,
                $defaultDate
            );
        }

        return $calendarDetails;
    }

    public function getCalendarDetailsArray(
        $calendarDetails,
        $carrierGroupDetail,
        $carrierId,
        $carrierCode,
        $locale,
        $deliveryDateFormat,
        $defaultDate
    ) {
        $calendarDetails['dateFormat'] = \ShipperHQ\Lib\Helper\Date::getDateFormat($locale);
        $calendarDetails['datepickerFormat'] = \ShipperHQ\Lib\Helper\Date::getDatepickerFormat($locale);
        $calendarDetails['displayDateFormat'] = \ShipperHQ\Lib\Helper\Date::getCldrDateFormat($locale, $deliveryDateFormat);
        $calendarDetails['timezone'] = $carrierGroupDetail['timezone'];
        $calendarDetails['default_date_timestamp'] = $defaultDate;
        //SHQ16-2041 pass default date in calendar details
        $calendarDetails['default_date'] = $this->getDateFromTimestamp(
            $defaultDate,
            'Europe/London', //SHQ16-2078 12th Oct - see Jira for details
            $calendarDetails['dateFormat']
        );
        if ($calendarDetails['startDate'] != '') {
            $calendarDetails['start'] = $calendarDetails['startDate']/1000;
        } else {
            $calendarDetails['start'] = $defaultDate;
        }
        $calendarDetails['carrier_id'] = $carrierId;
        $calendarDetails['carrier_code'] = $carrierCode;
        $dateOptions = $this->getDateOptions($calendarDetails);
        if (count($dateOptions)>0) {
            $deliveryDatesAndTimes = [];
            if (isset($calendarDetails['timeSlots']) && !empty($calendarDetails['timeSlots'])) {
                foreach ($dateOptions as $dateKey => $date) {
                    if ($slotsFound = $this->getDeliveryTimeSlots($calendarDetails, $dateKey)) {
                        $deliveryDatesAndTimes[$dateKey] = $slotsFound;
                    } else {
                        unset($dateOptions[$dateKey]);
                    }
                }
            }

            if (count($dateOptions) <= 0) {
                //handle if no date options found
                $dateOptions = [];
            }

            if (count($deliveryDatesAndTimes) > 0) {
                $calendarDetails['display_time_slots']= $deliveryDatesAndTimes;
                $calendarDetails['showTimeslots'] = true;
            } else {
                $calendarDetails['display_time_slots'] = false;
                $calendarDetails['showTimeslots'] = false;
            }
        }
        $calendarDetails['allowed_dates'] = $dateOptions;
        if (!empty($dateOptions)) { //SHQ16-2041
            $keys = array_keys($dateOptions);
            $calendarDetails['min_date'] = $keys[0];
            $calendarDetails['max_date'] = end($keys);
            //SHQ16-2392 check the selected date is in the date options - if not then remove it and push the default date to the first available
            //this is an issue for example if you change carriers selected on checkout, the previous carriers selected date
            //may not be viable for this carrier
            $isInDateOptions = in_array($calendarDetails['default_date'], $keys);
            if (!$isInDateOptions) {
                $calendarDetails['default_date'] = $calendarDetails['min_date'];
            }
        }
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
        foreach ($calendarDetails['blackoutDates'] as $blackoutDate) {
            $arrBlackoutDates[] = $this->getDateFromDate($blackoutDate, $timezone, $dateFormat);
        };
        $arrBlackoutDays = $calendarDetails['blackoutDays'];

        if (count($arrBlackoutDays) == 7) {
            //somehow flag that there are no date options available
            return $dateOptions;
        }
        $countDays = 0;
        while ($countDays < $numPickupDays) {
            //support end date inclusive
            if ($endDate && $startDate > $endDate) {
                break;
            }

            $nextDay = $this->getDateFromTimestamp($startDate, $timezone, $dateFormat);

            // Blackout day or date...get next available
            if (in_array($nextDay, $arrBlackoutDates) ||
                in_array($this->getDayOfWeekFromTimestamp($startDate, $timezone), $arrBlackoutDays)) {
                $this->_addDay($startDate);
                $countDays++;
                continue;
            }
            $dateOptions[$nextDay] = $nextDay;
            $this->_addDay($startDate);
            $countDays++;
        }
        return $dateOptions;
    }

    public function getDeliveryTimeSlots($calendarDetails, $date)
    {
        if (!isset($calendarDetails['timeSlots'])) {
            return false;
        }
        $timezone = $calendarDetails['timezone'];
        $timezoneObj = new \DateTimeZone($timezone);
        $dateFormat = $calendarDetails['dateFormat'];
        $today = $this->getCurrentDate($timezone, $dateFormat);

        $isToday = false;
        $selectedDate = $calendarDetails['default_date'];

        if ($today == $date) {
            $isToday = true;
            //account for same day delivery with lead time in hours
            $exactStartTimeStamp = $calendarDetails['start'];
            //if we are calcuating time slots for the selected date and that date is today.
            //need to now check that the current time is not AFTER the earliest possible start time (including any lead time in hours)
            if ($selectedDate == $today && $calendarDetails['default_date_timestamp'] > $exactStartTimeStamp) {
                $exactStartTimeStamp = $calendarDetails['default_date_timestamp'];
            }
        }

        $currentTime = 0;
        //if we are generating slots for today, make sure we don't offer any in the past
        //and we account for lead time in hours
        if ($isToday) {
            $currentTimeClass = new \DateTime("now", $timezoneObj);
            $currentTime = $currentTimeClass->getTimestamp();
            $currentTime = max($currentTime, $exactStartTimeStamp);
        }
        //if we are generating slots for the date that matches the default date, there may be lead time in hours,
        //lets account for that by setting the currentTime to be the default_date_timestamp
        if (!$isToday && $selectedDate == $date) {
            $currentTime = $calendarDetails['default_date_timestamp'];
        }

        $timeSlotDetail = (array)$calendarDetails['timeSlots'];

        $sortTime = [];
        foreach ($timeSlotDetail as $key => $val) {
            $values = (array)$val;
            $sortTime[$key] = $values['timeStart'];
            $timeSlotDetail[$key] = $values;
        }

        array_multisort($sortTime, SORT_ASC, $timeSlotDetail);

        //for implementation of date/day based slot detail in future
        $timeSlots = [];
        foreach ($timeSlotDetail as $slotDetail) {
            // If we dont have all our datapoints then drop the slot
            if (!isset($slotDetail['timeStart'], $slotDetail['timeEnd'], $slotDetail['interval'])) {
                continue;
            }

            // Setup slot boundaries and interval
            $slotStartTime = new \DateTime($date . ' ' .$slotDetail['timeStart'], $timezoneObj);
            $slotEndTime = new \DateTime($date . ' ' .$slotDetail['timeEnd'], $timezoneObj);
            $interval = $slotDetail['interval'];

            //if interval is half or full day then calculate those intervals
            if ($interval <= 2) {
                $dayLengthInMins = ($slotEndTime->getTimestamp() - $slotStartTime->getTimestamp() ) / 60;
                $interval = $dayLengthInMins / $interval;
            }

            // If end time < start time (slot runs overnight) then clamp it to midnight
            if ($slotEndTime <= $slotStartTime) {
                $slotEndTime->setTime(24, 00);
            }

            for ($e = clone ($s = clone $slotStartTime); $s < $slotEndTime; $s->setTimestamp($e->getTimestamp())) {
                // EndTime = StartTime + Interval
                $e->setTimestamp($s->getTimestamp())
                    ->modify("+ $interval minutes");
                // Don't let end of slot overrun our hard ceiling
                if ($e > $slotEndTime) {
                    $e->setTimestamp($slotEndTime->getTimestamp());
                }

                // If the timeslot has already passed, then move on
                if ($s->getTimestamp() < $currentTime) {
                    continue;
                }

                $intervalStartStr = $s->format('H:i');
                $intervalEndStr = $e->format('H:i');
                $intervalEndStr = ($intervalEndStr === "00:00") ? "24:00" : $intervalEndStr;
                $key = "{$intervalStartStr}_{$intervalEndStr}";
                $value = "{$intervalStartStr} - {$intervalEndStr}";

                $timeSlots[$key] = $value;
            }
            unset ($e, $s); // Paranoia
        }

        if (count($timeSlots) == 0) {
            return false;
        }
        ksort($timeSlots);
        return $timeSlots;
    }

    public function getDateFromDate($date, $timezone, $dateFormat)
    {
        //SHQ16-2417 correct black out dates to ignore time zone. This is just to reformat the dates correctly
        $returnDate = $this->getDateFromTimestamp(strtotime($date), 'Europe/London', $dateFormat);
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
    public function getDayOfWeekFromDate($date, $timezone)
    {
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
    public function getDayOfWeekFromTimestamp($timestamp, $timezone)
    {
        $dayOfWeek = $this->getDateFromTimestamp($timestamp, $timezone, 'N');
        return $dayOfWeek;
    }

    /**
     * Given a date will add a day to it.
     * @param $day
     * @param int $numDaysToAdd
     */
    protected function _addDay(&$day, $numDaysToAdd = 1)
    {
        $day = strtotime('+' .$numDaysToAdd .' day', $day);
        return $day;
    }

    public function getBlackoutDaysList($blackoutArray)
    {
        $arrBlackoutDays = [];
        foreach ($blackoutArray as $dayOfWeek) {
        //Java Sunday = 7, Monday = 1. PHP Monday = 1, Saturday = 6, Sunday = 0
            if ($dayOfWeek == 7) {
                $dayOfWeek = 0;
            }
            $arrBlackoutDays[] = $dayOfWeek;
        }
        return $arrBlackoutDays;
    }
}
