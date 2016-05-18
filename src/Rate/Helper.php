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

    public function extractTransactionId($rateResponse)
    {
        $responseSummary = (array)$rateResponse->responseSummary;
        return $responseSummary['transactionId'];
    }

    public function extractGlobalSettings($rateResponse)
    {
        $globals = (array)$rateResponse->globalSettings;
        return $globals;
    }

    public function extractCarrierGroupDetail($carrierGroup, $transactionId)
    {
        $carrierGroupDetail = (array)$carrierGroup->carrierGroupDetail;
        if(!array_key_exists('carrierGroupId', $carrierGroupDetail) || $carrierGroupDetail['carrierGroupId'] =='') {
            $carrierGroupDetail['carrierGroupId'] = 0;
        }
        $carrierGroupDetail['transaction'] = $transactionId;

        return $carrierGroupDetail;
    }

    /**
     * @param $carrierRate
     * @param $carrierGroupDetail
     * @param ConfigSettings $config
     * @return array
     */
    public function extractShipperHQRates($carrierRate, $carrierGroupDetail, ConfigSettings $config)
    {
        $carrierGroupId = $carrierGroupDetail['carrierGroupId'];
        $carrierResultWithRates = [
            'code' => $carrierRate->carrierCode,
            'title' => $carrierRate->carrierTitle];

        if (isset($carrierRate->error)) {
            $carrierResultWithRates['error'] = (array)$carrierRate->error;
            $carrierResultWithRates['carriergroup_detail']['carrierGroupId'] = $carrierGroupId;
        }

        if (isset($carrierRate->rates) && !array_key_exists('error', $carrierResultWithRates)) {
            $thisCarriersRates = $this->populateRates($carrierRate, $carrierGroupDetail, $config);
            $carrierResultWithRates['rates'] = $thisCarriersRates;
        }

        return $carrierResultWithRates;
    }

    protected function populateRates($carrierRate, &$carrierGroupDetail, ConfigSettings $config)
    {
        $thisCarriersRates = [];
        $baseRate = 1;
        $this->populateCarrierLevelDetails($carrierRate, $carrierGroupDetail, $config->getHideNotifications());

        $dateFormat = $this->getDateFormat($carrierRate, $config->getLocale());

        $dateOption = $carrierRate->dateOption;
        $deliveryMessage = $this->getDeliveryMessage($carrierRate, $dateOption);

        foreach ($carrierRate->rates as $oneRate) {
            $methodDescription = false;
            $title = $config->getTransactionIdEnabled() ?
                $oneRate->name . ' (' . $carrierGroupDetail['transaction'] . ')'
                : $oneRate->name;

            $this->populateRateLevelDetails((array)$oneRate, $carrierGroupDetail, $baseRate);

            $this->populateRateDeliveryDetails((array)$oneRate, $carrierGroupDetail, $methodDescription, $dateFormat,
                $dateOption, $deliveryMessage);

            if ($methodDescription) {
                $title .= ' ' . __($methodDescription);
            }
            $carrierType = $oneRate->carrierType;
            if($carrierType == 'shqshared') {
                $carrierType.= '_' .$oneRate->carrierType;
            }
            //create rateToAdd array - freight_rate, custom_duties,
            $rateToAdd = [
                'methodcode' => $oneRate->code,
                'method_title' => $title,
                'cost' => (float)$oneRate->shippingPrice,
                'price' => (float)$oneRate->totalCharges,
                'currency' => $oneRate->currency,
                'carrier_type' => $carrierType,
                'carrier_id' => $carrierRate->carrierId,
            ];

            if ($methodDescription) {
                $rateToAdd['method_description'] = $methodDescription;
            }
            $rateToAdd['carriergroup_detail'] = $carrierGroupDetail;

            $thisCarriersRates[] = $rateToAdd;
        }
        return $thisCarriersRates;
    }

    public function populateCarrierLevelDetails($carrierRate, &$carrierGroupDetail, $hideNotifyConfigFlag)
    {
        $carrierGroupDetail['carrierType'] = $carrierRate->carrierType;
        $carrierGroupDetail['carrierTitle'] = $carrierRate->carrierTitle;
        $carrierGroupDetail['carrier_code'] = $carrierRate->carrierCode;
        $carrierGroupDetail['carrierName'] = $carrierRate->carrierName;
        $notice = $customDescription = false;
        if(!$hideNotifyConfigFlag && isset($carrierRate->notices)) {
            $notice = '';
            foreach($carrierRate->notices as $oneNotice) {
                $notice .= $oneNotice ;
            }

        }
        $carrierGroupDetail['notice'] = $notice;
        if(isset($carrierRate->customDescription)) {
            $customDescription =  __($carrierRate->customDescription) ;
        }
        $carrierGroupDetail['custom_description'] = $customDescription;
    }

    public function populateRateLevelDetails($rate, &$carrierGroupDetail, $currencyConversionRate)
    {
        $carrierGroupDetail['methodTitle'] = $rate['name'];
        $carrierGroupDetail['price'] = (float)$rate['totalCharges']*$currencyConversionRate;
        $carrierGroupDetail['cost'] = (float)$rate['shippingPrice']*$currencyConversionRate;
        $carrierGroupDetail['code'] = $rate['code'];
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

    public function getDateFormat($carrierRate, $locale)
    {
        $interim = isset($carrierRate->deliveryDateFormat) ?
            $carrierRate->deliveryDateFormat : self::DEFAULT_DATE_FORMAT;
        $dateFormat = $this->getCldrDateFormat($locale, $interim);
        return $dateFormat;
    }

    public function getCldrDateFormat($locale, $code)
    {
        $dateFormatArray = $this->getCode('cldr_date_format', $locale);
        $dateFormat = is_array($dateFormatArray) && array_key_exists($code, $dateFormatArray) ? $dateFormatArray[$code]:
            'MM/dd/Y';
        return $dateFormat;
    }

    /**
     * Get configuration data
     *
     * @param string $type
     * @param string $code
     * @return array|bool
     */
    public function getCode($type, $code = '')
    {
        $codes = array(
            'date_format'   =>array(
                'dd-mm-yyyy'    	    => 'd-m-Y',
                'mm/dd/yyyy'    	    => 'm/d/Y',
                'EEE dd-MM-yyyy'        => 'D d-m-Y'
            ),
            'short_date_format'   =>array(
                'dd-mm-yyyy'   	    => 'd-m-Y',
                'mm/dd/yyyy'    	    => 'm/d/Y',
                'EEE dd-MM-yyyy'        => 'D d-m-Y'
            ),
            'datepicker_format' => array(
                'dd-mm-yyyy'         => 'dd-mm-yy',
                'mm/dd/yyyy'         => 'mm/dd/yy',
                'EEE dd-MM-yyyy'        => 'DD d-MM-yy'

            ),
            'zend_date_format'     => array(
                'dd-mm-yyyy'         => 'dd-MM-y',
                'mm/dd/yyyy'         => 'MM/dd/y',
                'EEE dd-MM-yyyy'        => 'E d-M-y'
            ),
            'cldr_date_format'      => array(
                'en-US'            => array(
                    'yMd'           => 'M/d/Y',
                    'yMMMd'         => 'M d, Y',
                    'yMMMEd'        => 'EEE, M d, Y',
                    'yMEd'          => 'EEE, M/d/Y',
                    'MMMd'          => 'M d',
                    'MMMEd'         => 'EEE, M d',
                    'MEd'           => 'EEE, M/d',
                    'Md'            => 'M/d',
                    'yM'            => 'M/Y',
                    'yMMM'          => 'M Y',
                    'MMM'          => 'M',
                    'E'             => 'EEE',
                    'Ed'            => 'd EEE',
                ),
                'en-GB'            => array(
                    'yMd'           => 'd-M-Y',
                    'yMMMd'         => 'd M Y',
                    'yMMMEd'        => 'EEE, d M Y',
                    'yMEd'          => 'EEE, d-M-Y',
                    'MMMd'          => 'd M',
                    'MMMEd'         => 'EEE, d M',
                    'MEd'           => 'EEE, d-M',
                    'Md'            => 'd-M',
                    'yM'            => 'M-Y',
                    'yMMM'          => 'M Y',
                    'MMM'          =>  'M',
                    'E'             => 'EEE',
                    'Ed'            => 'EEE d',
                )
            )
        );

        if (!isset($codes[$type])) {
            return false;
        } elseif ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            return false;
        } else {
            return $codes[$type][$code];
        }
    }
}