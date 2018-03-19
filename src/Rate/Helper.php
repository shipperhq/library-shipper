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
    const CALENDAR_DATE_OPTION = 'calendar';
    const DELIVERY_DATE_OPTION = 'delivery_date';
    const TIME_IN_TRANSIT = 'time_in_transit';

    const DEFAULT_DELIVERY_MESSAGE = 'Delivers: ';
    const DEFAULT_TRANSIT_MESSAGE = 'business days';
    const DEFAULT_DATE_FORMAT = 'yMd';

    const AV_DISABLED = 'VALIDATION_NOT_ENABLED';
    const ADDRESS_TYPE_UNKNOWN = 'UNKNOWN';

    /**
     * @var \ShipperHQ\Lib\Helper\Date
     */
    protected $dateHelper;

    /**
     * \ShipperHQ\Lib\Helper\Date $dateHelper
     * @codeCoverageIgnore
     */
    public function __construct(
        \ShipperHQ\Lib\Helper\Date $dateHelper
    ) {
    
        $this->dateHelper = $dateHelper;
    }

    public function shouldValidateAddress($addressValidationStatus, $destinationType)
    {
        $validate = true;
        if ($destinationType !== null && $destinationType != '') {
            $validate = false;
        } elseif ($addressValidationStatus !== null && $addressValidationStatus != ''
            && $addressValidationStatus != 'EXACT_MATCH') {
            $validate = false;
        }
        return $validate;
    }

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
    
    public function extractDestinationType($rateResponse)
    {
        $addressType = false;
        if (isset($rateResponse->addressValidationResponse)) {
            $avResponse = $rateResponse->addressValidationResponse;
            if (!isset($avResponse->validationStatus) || $avResponse->validationStatus == self::AV_DISABLED) {
                return $addressType;
            }
            if (isset($avResponse->destinationType) && $avResponse->destinationType != self::ADDRESS_TYPE_UNKNOWN) {
                $addressType = $avResponse->destinationType;
            }
        }
        return $addressType;
    }

    public function extractAddressValidationStatus($rateResponse)
    {
        $validStatus = false;
        if (isset($rateResponse->addressValidationResponse)) {
            $avResponse = $rateResponse->addressValidationResponse;
            if (!isset($avResponse->validationStatus) || $avResponse->validationStatus == self::AV_DISABLED) {
                return $validStatus;
            }
            $validStatus = $avResponse->validationStatus;
        }
        return $validStatus;
    }

    public function extractValidatedAddress($rateResponse)
    {
        $validatedAddress = false;
        if (isset($rateResponse->addressValidationResponse)) {
            $avResponse = $rateResponse->addressValidationResponse;
            if (!isset($avResponse->suggestedAddresses) || count($avResponse->suggestedAddresses) !== 1) {
                return $validatedAddress;
            }
            $validatedAddress = is_array($avResponse->suggestedAddresses) ? $avResponse->suggestedAddresses[0] : false;
        }
        return $validatedAddress;
    }

    public function extractCarrierGroupDetail($carrierGroup, $transactionId, $configSettings)
    {
        $carrierGroupDetail = (array)$carrierGroup->carrierGroupDetail;
        if (!array_key_exists('carrierGroupId', $carrierGroupDetail) || $carrierGroupDetail['carrierGroupId'] =='') {
            $carrierGroupDetail['carrierGroupId'] = 0;
        }
        $carrierGroupDetail['transaction'] = $transactionId;
        $carrierGroupDetail['locale'] = $configSettings->getLocale();
        $carrierGroupDetail['timezone'] = $configSettings->getTimezone();

        return $carrierGroupDetail;
    }

    /**
     * @param $carrierRate
     * @param $carrierGroupDetail
     * @param ConfigSettings $config
     * @return array
     */
    public function extractShipperHQRates(
        $carrierRate,
        $carrierGroupDetail,
        ConfigSettings $config,
        &$splitCarrierGroupDetail
    ) {
        $carrierGroupId = $carrierGroupDetail['carrierGroupId'];
        $carrierResultWithRates = [
            'code' => $carrierRate->carrierCode,
            'title' => $carrierRate->carrierTitle];

        if (isset($carrierRate->error)) {
            $carrierResultWithRates['error'] = (array)$carrierRate->error;
            $carrierResultWithRates['carriergroup_detail']['carrierGroupId'] = $carrierGroupId;
        }

        if (isset($carrierRate->rates) && !array_key_exists('error', $carrierResultWithRates)) {
            $thisCarriersRates = $this->populateRates(
                $carrierRate,
                $carrierGroupDetail,
                $config,
                $splitCarrierGroupDetail
            );
            $carrierResultWithRates['rates'] = $thisCarriersRates;
            $thisCarriersShipments = $this->populateShipments($carrierRate, $carrierGroupDetail);
            $carrierResultWithRates['shipments'] = $thisCarriersShipments;
        }

        return $carrierResultWithRates;
    }

    public function extractShipperHQMergedRates(
        $carrierRate,
        $splitCarrierGroupDetail,
        ConfigSettings $config,
        $transactionId
    ) {
        $mergedCarrierResultWithRates = [
            'code' => $carrierRate->carrierCode,
            'title' => $carrierRate->carrierTitle];

        if (isset($carrierRate->error)) {
            $mergedCarrierResultWithRates['error'] = (array)$carrierRate->error;
            $mergedCarrierResultWithRates['code'] = $config->getShipperHQCode();
            $mergedCarrierResultWithRates['title'] = $config->getShipperHQTitle();
        }

        if (isset($carrierRate->rates) && !isset($mergedCarrierResultWithRates['error'])) {
            $carrierGroupDetail = ['transaction' => $transactionId];
            $emptySplitCarrierGroupArray = false;
            $mergedRates = $this->populateRates(
                $carrierRate,
                $carrierGroupDetail,
                $config,
                $emptySplitCarrierGroupArray
            );
            foreach ($carrierRate->rates as $oneRate) {
                if (isset($oneRate->rateBreakdownList)) {
                    $carrierGroupShippingDetail = [];
                    $rateBreakdown = $oneRate->rateBreakdownList;
                    foreach ($rateBreakdown as $rateInMergedRate) {
                        if (isset($splitCarrierGroupDetail[$rateInMergedRate->carrierGroupId])) {
                            if (isset($splitCarrierGroupDetail[$rateInMergedRate->carrierGroupId][$rateInMergedRate->carrierCode])
                                && isset($splitCarrierGroupDetail[$rateInMergedRate->carrierGroupId][$rateInMergedRate->carrierCode][$rateInMergedRate->methodCode])) {
                                $carrierGroupShippingDetail[]= $splitCarrierGroupDetail[$rateInMergedRate->carrierGroupId][$rateInMergedRate->carrierCode][$rateInMergedRate->methodCode];
                            }
                        }
                    }
                    foreach ($mergedRates as $key => $rateToAdd) {
                        if ($rateToAdd['methodcode'] != $oneRate->code) {
                            continue;
                        }
                        $rateToAdd['carriergroup_detail'] = $carrierGroupShippingDetail;
                        $mergedRates[$key] = $rateToAdd;
                    }
                }
            }
            $mergedCarrierResultWithRates['rates'] = $mergedRates;
        }
        return $mergedCarrierResultWithRates;
    }

    protected function populateRates(
        $carrierRate,
        &$carrierGroupDetail,
        ConfigSettings $config,
        &$splitCarrierGroupDetail
    ) {
        $thisCarriersRates = [];
        $baseRate = 1;
        $shipments = $this->populateCarrierLevelDetails(
            $carrierRate,
            $carrierGroupDetail,
            $config->getHideNotifications()
        );

        $dateFormat = $this->extractDateFormat($carrierRate, $config->getLocale());

        $dateOption = $carrierRate->dateOption;

        foreach ($carrierRate->rates as $oneRate) {
            $methodDescription = false;
            $title = $config->getTransactionIdEnabled() ?
                $oneRate->name . ' (' . $carrierGroupDetail['transaction'] . ')'
                : $oneRate->name;

            $this->populateRateLevelDetails((array)$oneRate, $carrierGroupDetail, $baseRate);

            $this->populateRateDeliveryDetails(
                (array)$oneRate,
                $carrierGroupDetail,
                $methodDescription,
                $dateFormat,
                $dateOption,
                $config->getTimezone()
            );

            if ($methodDescription) {
                $title .= ' ' . __($methodDescription);
            }
            $carrierType = $oneRate->carrierType;
            if ($carrierRate->carrierType == 'shqshared') {
                $carrierType = $carrierRate->carrierType .'_' .$oneRate->carrierType;
                $carrierGroupDetail['carrierType'] = $carrierType;
                if (isset($oneRate->carrierTitle)) {
                    $carrierGroupDetail['carrierTitle'] = $oneRate->carrierTitle;
                }
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
            if (isset($carrierGroupDetail['dispatch_date'])) {
                $rateToAdd['dispatch_date'] = $carrierGroupDetail['dispatch_date'];
            }
            if (isset($carrierGroupDetail['delivery_date'])) {
                $rateToAdd['delivery_date'] = $carrierGroupDetail['delivery_date'];
            }
            $rateToAdd['tooltip'] = $oneRate->description;

            if ($methodDescription) {
                $rateToAdd['method_description'] = $methodDescription;
            }
            $rateToAdd['carriergroup_detail'] = $carrierGroupDetail;
            if (is_array($splitCarrierGroupDetail)) {
                $splitCarrierGroupDetail[$carrierGroupDetail['carrierGroupId']][$carrierRate->carrierCode][$oneRate->code] =
                    $carrierGroupDetail;
            }
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
        if (!$hideNotifyConfigFlag && isset($carrierRate->notices)) {
            $notice = '';
            foreach ($carrierRate->notices as $oneNotice) {
                $notice .= $oneNotice ;
            }
        }
        $carrierGroupDetail['notice'] = $notice;
        if (isset($carrierRate->customDescription)) {
            $customDescription =  __($carrierRate->customDescription);
        }
        $carrierGroupDetail['custom_description'] = $customDescription;
    }

    public function populateRateLevelDetails($rate, &$carrierGroupDetail, $currencyConversionRate)
    {
        $carrierGroupDetail['methodTitle'] = $rate['name'];
        $carrierGroupDetail['price'] = (float)$rate['totalCharges']*$currencyConversionRate;
        $carrierGroupDetail['cost'] = (float)$rate['shippingPrice']*$currencyConversionRate;
        $carrierGroupDetail['code'] = $rate['code'];
        if (isset($rate['selectedOptions'])) {
            $selectedOptions =  (array)$rate['selectedOptions'];
            if (isset($selectedOptions['options'])) {
                foreach ($selectedOptions['options'] as $option) {
                    $thisOption =(array)$option;
                    if (isset($thisOption['name'])) {
                        $carrierGroupDetail[$thisOption['name']] = $thisOption['value'];
                    }
                }
            }
        }
    }

    public function populateRateDeliveryDetails(
        $rate,
        &$carrierGroupDetail,
        &$methodDescription,
        $dateFormat,
        $dateOption,
        $timezone
    ) {
        $carrierGroupDetail['delivery_date'] = '';
        $carrierGroupDetail['dispatch_date'] = '';
        $carrierGroupDetail['display_date_format'] = $dateFormat;
        if (isset($rate['deliveryDate']) && is_numeric($rate['deliveryDate'])) {
            $date = new \DateTime();

            $date->setTimezone(new \DateTimeZone($timezone));
            $date->setTimestamp($rate['deliveryDate']/1000);
            $deliveryDate = $date->format($dateFormat);
            $carrierGroupDetail['delivery_date'] = $deliveryDate;
        }
        if (isset($rate['dispatchDate']) && is_numeric($rate['dispatchDate'])) {
            $dispatch = new \DateTime();
            $dispatch->setTimezone(new \DateTimeZone($timezone));
            $dispatch->setTimestamp($rate['dispatchDate']/1000);
            $dispatchDate = $dispatch->format($dateFormat);
            $carrierGroupDetail['dispatch_date'] = $dispatchDate;
        }

        if (isset($rate['deliveryMessage']) && $rate['deliveryMessage'] !== null && $rate['deliveryMessage'] != '') {
            $methodDescription = $dateOption == self::TIME_IN_TRANSIT ?
                '(' .$rate['deliveryMessage'] .')' : $rate['deliveryMessage'];
        }
    }

    protected function populateShipments($carrierRate, $carrierGroupDetail)
    {
        $shipments = [];
        $cgId = array_key_exists('carrierGroupId', $carrierGroupDetail) ? $carrierGroupDetail['carrierGroupId'] : null;
        $mapping = $this->getPackagesMapping();

        //populate packages
        if (isset($carrierRate->shipments) && $carrierRate->shipments != null) {
            $standardData = ['carrier_group_id' => $cgId,
                'carrier_code' =>  $carrierRate->carrierCode];
            foreach ($carrierRate->shipments as $shipment) {
                $data = array_merge($standardData, $this->map($mapping, (array)$shipment));
                $shipments[] = $data;
            }
        }
        if (isset($carrierRate->rates)) {
            foreach ($carrierRate->rates as $oneRate) {
                if (isset($oneRate->shipments)) {
                    $standardData = ['carrier_group_id' => $cgId,
                        'carrier_code' =>  $carrierRate->carrierCode.'_'.$oneRate->code];
                    foreach ($oneRate->shipments as $shipment) {
                        $data = array_merge($standardData, $this->map($mapping, (array)$shipment));
                        $shipments[] = $data;
                    }
                }
            }
        }
        return $shipments;
    }

    public function extractDateFormat($carrierRate, $locale)
    {
        $interim = isset($carrierRate->deliveryDateFormat) ?
            $carrierRate->deliveryDateFormat : self::DEFAULT_DATE_FORMAT;
        $dateFormat = $this->dateHelper->getCldrDateFormat($locale, $interim);
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
        $codes = [
            'date_format'   =>[
                'dd-mm-yyyy'            => 'd-m-Y',
                'mm/dd/yyyy'            => 'm/d/Y',
                'EEE dd-MM-yyyy'        => 'D d-m-Y'
            ],
            'short_date_format'   =>[
                'dd-mm-yyyy'        => 'd-m-Y',
                'mm/dd/yyyy'            => 'm/d/Y',
                'EEE dd-MM-yyyy'        => 'D d-m-Y'
            ],
            'datepicker_format' => [
                'dd-mm-yyyy'         => 'dd-mm-yy',
                'mm/dd/yyyy'         => 'mm/dd/yy',
                'EEE dd-MM-yyyy'        => 'DD d-MM-yy'

            ],
            'zend_date_format'     => [
                'dd-mm-yyyy'         => 'dd-MM-y',
                'mm/dd/yyyy'         => 'MM/dd/y',
                'EEE dd-MM-yyyy'        => 'E d-M-y'
            ],
            'cldr_date_format'      => [
                'en-US'            => [
                    'yMd'           => 'n/d/Y',
                    'yMMMd'         => 'M d, Y',
                    'yMMMEd'        => 'D, M d, Y',
                    'yMEd'          => 'D, n/d/Y',
                    'MMMd'          => 'M d',
                    'MMMEd'         => 'D, M d',
                    'MEd'           => 'D, n/d',
                    'Md'            => 'n/d',
                    'yM'            => 'n/Y',
                    'yMMM'          => 'M Y',
                    'MMM'          => 'M',
                    'E'             => 'D',
                    'Ed'            => 'd D',
                ],
                'en-GB'            => [
                    'yMd'           => 'd/m/Y',
                    'yMMMd'         => 'd M Y',
                    'yMMMEd'        => 'D, d M Y',
                    'yMEd'          => 'D, d/m/Y',
                    'MMMd'          => 'd M',
                    'MMMEd'         => 'D d M',
                    'MEd'           => 'D d/m',
                    'Md'            => 'd/m',
                    'yM'            => 'm/Y',
                    'yMMM'          => 'M Y',
                    'MMM'          =>  'M',
                    'E'             => 'D',
                    'Ed'            => 'd D',
                ]
            ]
        ];

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

    public function map($mapping, $source)
    {
        $target = [];
        foreach ($mapping as $targetField => $sourceField) {
            if (is_string($sourceField)) {
                if (strpos($sourceField, '/') !== false) {
                    $fields = explode('/', $sourceField);
                    $value = $source;
                    while ($fields) {
                        $field = array_shift($fields);
                        if (isset($value[$field])) {
                            $value = $value[$field];
                        } else {
                            $value = null;
                            break;
                        }
                    }
                    $target[$targetField] = $value;
                } else {
                    $target[$targetField] = $source[$sourceField];
                }
            } elseif (is_array($sourceField)) {
                list($field, $defaultValue) = $sourceField;
                $target[$targetField] = (isset($source[$field]) ? $source[$field] : $defaultValue);
            }
        }

        return $target;
    }

    protected function getPackagesMapping()
    {
        return [
            'package_name' => 'name',
            'length' => 'length',
            'width' => 'width',
            'height' => 'height',
            'weight' => 'weight',
            'surcharge_price' => 'surchargePrice',
            'declared_value' => 'declaredValue',
            'items'         => 'boxedItems'
        ];
    }
}
