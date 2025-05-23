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

    /**
     * MNB-422 Changed to revalidate if addressValidationStatus is anything but a match
     *
     * @param $addressValidationStatus
     * @param string $destinationType
     *
     * @return bool
     */
    public function shouldValidateAddress(?string $addressValidationStatus, ?string $destinationType): bool
    {
        $destinationType = $destinationType ?? "";
        $validate = true;

        if (strlen($destinationType) > 0 || $addressValidationStatus == 'EXACT_MATCH' ||
            $addressValidationStatus == 'CORRECTED_EXACT_MATCH') {
            $validate = false;
        }

        return $validate;
    }

    public function extractTransactionId($rateResponse)
    {
        $responseSummary = (array)$rateResponse['responseSummary'];
        return $responseSummary['transactionId'];
    }

    public function extractGlobalSettings($rateResponse)
    {
        $globals = (array)$rateResponse['globalSettings'];
        return $globals;
    }

    public function extractDestinationType($rateResponse)
    {
        $addressType = false;
        if (isset($rateResponse['addressValidationResponse'])) {
            $avResponse = $rateResponse['addressValidationResponse'];
            if (!isset($avResponse['validationStatus']) || $avResponse['validationStatus'] == self::AV_DISABLED) {
                return $addressType;
            }
            if (isset($avResponse['destinationType']) && $avResponse['destinationType'] != self::ADDRESS_TYPE_UNKNOWN) {
                $addressType = $avResponse['destinationType'];
            }
        }
        return $addressType;
    }

    public function extractAddressValidationStatus($rateResponse)
    {
        $validStatus = false;
        if (isset($rateResponse['addressValidationResponse'])) {
            $avResponse = $rateResponse['addressValidationResponse'];
            if (!isset($avResponse['validationStatus']) || $avResponse['validationStatus'] == self::AV_DISABLED) {
                return $validStatus;
            }
            $validStatus = $avResponse['validationStatus'];
        }
        return $validStatus;
    }

    public function extractValidatedAddress($rateResponse)
    {
        $validatedAddress = false;
        if (isset($rateResponse['addressValidationResponse'])) {
            $avResponse = $rateResponse['addressValidationResponse'];
            if (!isset($avResponse['suggestedAddresses']) || count($avResponse['suggestedAddresses']) !== 1) {
                return $validatedAddress;
            }
            $validatedAddress = is_array($avResponse['suggestedAddresses']) ? $avResponse['suggestedAddresses'][0] : false;
        }
        return $validatedAddress;
    }

    public function extractCarrierGroupDetail($carrierGroup, $transactionId, $configSettings)
    {
        $carrierGroupDetail = (array)$carrierGroup['carrierGroupDetail'];
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
            'code' => $carrierRate['carrierCode'],
            'title' => $carrierRate['carrierTitle']
        ];

        if (isset($carrierRate['error'])) {
            $carrierResultWithRates['error'] = $carrierRate['error'];
            $carrierResultWithRates['carriergroup_detail']['carrierGroupId'] = $carrierGroupId;
        }

        if (isset($carrierRate['rates']) && !array_key_exists('error', $carrierResultWithRates)) {
            $thisCarriersRates = $this->populateRates(
                $carrierRate,
                $carrierGroupDetail,
                $config,
                $splitCarrierGroupDetail
            );

            // SHQ18-1613 If rate shopped method, look for actual method code and name
            foreach ($carrierRate['rates'] as $oneRate) {
                if (isset($oneRate['rateBreakdownList']) && count($oneRate['rateBreakdownList']) > 0) {
                    $rateBreakdown = $oneRate['rateBreakdownList'];
                    $actualCarrierCode = $oneRate['carrierCode'];
                    $actualShippingRate = $oneRate['totalCharges'];
                    $carrierGroupShippingDetail = [];

                    foreach ($rateBreakdown as $candidateActualRate) {
                        if ($candidateActualRate['carrierCode'] == $actualCarrierCode
                            && $actualShippingRate == $candidateActualRate['totalCharges']) {
                            $carrierGroupShippingDetail['carrier_code'] = $candidateActualRate['carrierCode'];
                            $carrierGroupShippingDetail['methodTitle'] = $candidateActualRate['name'];
                            $carrierGroupShippingDetail['carrierType'] = $candidateActualRate['carrierType'];
                            $carrierGroupShippingDetail['code'] = $candidateActualRate['methodCode'];
                            $carrierGroupShippingDetail['carrierTitle'] = $oneRate['carrierTitle'];
                            $carrierGroupShippingDetail['carrierName'] = $oneRate['carrierTitle'];
                        }
                    }

                    foreach ($thisCarriersRates as $key => $rateToAdd) {
                        if ($rateToAdd['methodcode'] != $oneRate['code']) {
                            continue;
                        }
                        $rateToAdd['carriergroup_detail'] = array_merge($rateToAdd['carriergroup_detail'], $carrierGroupShippingDetail);
                        $thisCarriersRates[$key] = $rateToAdd;
                    }
                }
            }

            $carrierResultWithRates['rates'] = $thisCarriersRates;
            $thisCarriersShipments = $this->populateShipments($carrierRate, $carrierGroupDetail);
            $carrierResultWithRates['shipments'] = $thisCarriersShipments;
        }

        if (isset($carrierRate['customDescription']) && !empty($carrierRate['customDescription'])) {
            $carrierResultWithRates['custom_description'] = $carrierRate['customDescription'];
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
            'code' => $carrierRate['carrierCode'],
            'title' => $carrierRate['carrierTitle']
        ];

        if (isset($carrierRate['error'])) {
            $mergedCarrierResultWithRates['error'] = $carrierRate['error'];
            $mergedCarrierResultWithRates['code'] = $config->getShipperHQCode();
            $mergedCarrierResultWithRates['title'] = $config->getShipperHQTitle();
        }

        if (isset($carrierRate['rates']) && !isset($mergedCarrierResultWithRates['error'])) {
            $carrierGroupDetail = ['transaction' => $transactionId];
            $emptySplitCarrierGroupArray = false;
            $mergedRates = $this->populateRates(
                $carrierRate,
                $carrierGroupDetail,
                $config,
                $emptySplitCarrierGroupArray
            );
            foreach ($carrierRate['rates'] as $mergedRate) {
                if (isset($mergedRate['rateBreakdownList'])) {
                    $carrierGroupShippingDetail = [];
                    $shipments = [];
                    $rateBreakdown = $mergedRate['rateBreakdownList'];

                    foreach ($rateBreakdown as $rateInMergedRate) {
                        if (isset($splitCarrierGroupDetail[$rateInMergedRate['carrierGroupId']])) {
                            if (isset($splitCarrierGroupDetail[$rateInMergedRate['carrierGroupId']][$rateInMergedRate['carrierCode']])
                                && isset($splitCarrierGroupDetail[$rateInMergedRate['carrierGroupId']][$rateInMergedRate['carrierCode']][$rateInMergedRate['methodCode']])) {
                                $cg = $splitCarrierGroupDetail[$rateInMergedRate['carrierGroupId']][$rateInMergedRate['carrierCode']][$rateInMergedRate['methodCode']];
                                $carrierGroupShippingDetail[] = $cg;
                                $shipments = array_merge($this->populateShipments($rateInMergedRate, $cg, true), $shipments);
                            }
                        }
                    }

                    foreach ($mergedRates as $key => $rateToAdd) {
                        if ($rateToAdd['methodcode'] != $mergedRate['code']) {
                            continue;
                        }
                        $rateToAdd['carriergroup_detail'] = $carrierGroupShippingDetail;
                        $rateToAdd['shipments'] = $shipments;
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
        $this->populateCarrierLevelDetails($carrierRate, $carrierGroupDetail, $config->getHideNotifications());

        $dateFormat = $this->extractDateFormat($carrierRate, $config->getLocale());

        $dateOption = $carrierRate['dateOption'];

        foreach ($carrierRate['rates'] as $oneRate) {
            $methodDescription = false;
            $title = $config->getTransactionIdEnabled() ?
                $oneRate['name'] . ' (' . $carrierGroupDetail['transaction'] . ')'
                : $oneRate['name'];

            $this->populateRateLevelDetails((array)$oneRate, $carrierGroupDetail, $baseRate, $config->getHideNotifications());

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
            $carrierType = $oneRate['carrierType'];
            if ($carrierRate['carrierType'] == 'shqshared') {
                $carrierType = $carrierRate['carrierType'];

                // MNB-2930 The carrier type can be null when merged rates are being used
                if ($oneRate['carrierType']) {
                    $carrierType = $carrierRate['carrierType'] . '_' . $oneRate['carrierType'];
                }

                $carrierGroupDetail['carrierType'] = $carrierType;
                if (isset($oneRate['carrierTitle'])) {
                    $carrierGroupDetail['carrierTitle'] = $oneRate['carrierTitle'];
                }
            }
            // create rateToAdd array - freight_rate, custom_duties,
            $rateToAdd = [
                'methodcode' => $oneRate['code'],
                'method_title' => $title,
                'cost' => (float)$oneRate['shippingPrice'],
                'price' => (float)$oneRate['totalCharges'],
                'currency' => $oneRate['currency'],
                'carrier_type' => $carrierType,
                'carrier_id' => $carrierRate['carrierId'],
                'nypAmount' => $oneRate['cost'],
            ];
            if (isset($carrierGroupDetail['dispatch_date'])) {
                $rateToAdd['dispatch_date'] = $carrierGroupDetail['dispatch_date'];
            }
            if (isset($carrierGroupDetail['delivery_date'])) {
                $rateToAdd['delivery_date'] = $carrierGroupDetail['delivery_date'];
            }
            $rateToAdd['tooltip'] = $oneRate['description'];

            if ($methodDescription) {
                $rateToAdd['method_description'] = $methodDescription;
            }
            $rateToAdd['carriergroup_detail'] = $carrierGroupDetail;
            if (is_array($splitCarrierGroupDetail)) {
                $splitCarrierGroupDetail[$carrierGroupDetail['carrierGroupId']][$oneRate['carrierCode']][$oneRate['code']] = $carrierGroupDetail;
            }
            $thisCarriersRates[] = $rateToAdd;
        }
        return $thisCarriersRates;
    }

    public function populateCarrierLevelDetails($carrierRate, &$carrierGroupDetail, $hideNotifyConfigFlag)
    {
        $carrierGroupDetail['carrierType'] = $carrierRate['carrierType'];
        $carrierGroupDetail['carrierTitle'] = $carrierRate['carrierTitle'];
        $carrierGroupDetail['carrier_code'] = $carrierRate['carrierCode'];
        $carrierGroupDetail['carrierName'] = $carrierRate['carrierName'];

        $notice = $customDescription = false;
        if (!$hideNotifyConfigFlag && isset($carrierRate['notices'])) {
            $notice = '';
            foreach ($carrierRate['notices'] as $oneNotice) {
                $notice .= $oneNotice;
            }
        }
        $carrierGroupDetail['notice'] = $notice;
        if (isset($carrierRate['customDescription'])) {
            $customDescription =  __($carrierRate['customDescription']);
        }
        $carrierGroupDetail['custom_description'] = $customDescription;
    }

    public function populateRateLevelDetails($rate, &$carrierGroupDetail, $currencyConversionRate, $hideNotifications)
    {
        $carrierGroupDetail['methodTitle'] = $rate['name'];
        $carrierGroupDetail['price'] = (float)$rate['totalCharges']*$currencyConversionRate;
        $carrierGroupDetail['cost'] = (float)$rate['shippingPrice']*$currencyConversionRate;
        $carrierGroupDetail['rate_cost'] = (float)$rate['cost']*$currencyConversionRate;
        $carrierGroupDetail['customDuties'] = (float)$rate['customDuties']*$currencyConversionRate;
        $carrierGroupDetail['customsMessage'] = $rate['customsMessage'];
        $carrierGroupDetail['hideNotifications'] = $hideNotifications;
        $carrierGroupDetail['code'] = $rate['code'];
        if (isset($rate['quoteId'])) {
            $carrierGroupDetail['freightQuoteId'] = $rate['quoteId'];
        }
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
            $date->setTimestamp(intval($rate['deliveryDate']/1000));
            $deliveryDate = $date->format($dateFormat);
            $carrierGroupDetail['delivery_date'] = $deliveryDate;
        }
        if (isset($rate['dispatchDate']) && is_numeric($rate['dispatchDate'])) {
            $dispatch = new \DateTime();
            $dispatch->setTimezone(new \DateTimeZone($timezone));
            $dispatch->setTimestamp(intval($rate['dispatchDate']/1000));
            $dispatchDate = $dispatch->format($dateFormat);
            $carrierGroupDetail['dispatch_date'] = $dispatchDate;
        }

        if (isset($rate['deliveryMessage']) && $rate['deliveryMessage'] !== null && $rate['deliveryMessage'] != '') {
            $methodDescription = $dateOption == self::TIME_IN_TRANSIT ?
                '(' . $rate['deliveryMessage'] . ')' : $rate['deliveryMessage'];
        }
    }

    /**
     * Returns an array of shipments which includes carrier group info
     *
     * @param      $carrierRate
     * @param      $carrierGroupDetail
     * @param bool $addMethodCode Adds method code to carrier_code if set
     *
     * @return array
     */
    protected function populateShipments($carrierRate, $carrierGroupDetail, $addMethodCode = false)
    {
        $shipments = [];
        $cgId = array_key_exists('carrierGroupId', $carrierGroupDetail) ? $carrierGroupDetail['carrierGroupId'] : null;
        $methodCode = array_key_exists('methodCode', $carrierRate) ? '_' . $carrierRate['methodCode'] : '';
        $mapping = $this->getPackagesMapping();

        //populate packages
        if (isset($carrierRate['shipments']) && $carrierRate['shipments'] != null) {
            $carrierCode = $addMethodCode ? $carrierRate['carrierCode'] . $methodCode : $carrierRate['carrierCode'];

            $standardData = [
                'carrier_group_id' => $cgId,
                'carrier_code'     => $carrierCode
            ];

            foreach ($carrierRate['shipments'] as $shipment) {
                $data = array_merge($standardData, $this->map($mapping, (array)$shipment));
                $shipments[] = $data;
            }
        }
        if (isset($carrierRate['rates'])) {
            foreach ($carrierRate['rates'] as $oneRate) {
                if (isset($oneRate['shipments'])) {
                    // MNB-48 Ensure we save the actual carrier code if using rate shopping
                    if ($carrierRate['carrierCode'] == "multicarrier") {
                        $carrierCode = $oneRate['carrierCode'];
                    } else {
                        $carrierCode = $carrierRate['carrierCode'] . '_' . $oneRate['code'];
                    }

                    $standardData = ['carrier_group_id' => $cgId,
                                     'carrier_code'     => $carrierCode
                    ];

                    foreach ($oneRate['shipments'] as $shipment) {
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
        $interim = $carrierRate['deliveryDateFormat'] ?? self::DEFAULT_DATE_FORMAT;

        return $this->dateHelper->getCldrDateFormat($locale, $interim);
    }

    /**
     * Get configuration data
     *
     * @param string $type
     * @param string $code
     * @return array|bool
     * @deprecated instead use the function in the Date helper
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

    /**
     * Used to convert JSON response object to array
     *
     * @param $obj
     *
     * @return array
     */
    public function object_to_array($obj)
    {
        if (is_object($obj)) {
            $obj = (array) $obj;
        }

        if (is_array($obj)) {
            $new = [];
            foreach ($obj as $key => $val) {
                $new[$key] = $this->object_to_array($val);
            }
        } else {
            $new = $obj;
        }
        return $new;
    }
}
