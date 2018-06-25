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
 * Class BaseOption
 *
 * @package ShipperHQ_Lib
 */
class BaseOption extends BaseCalendar
{
    /**
     * @var \ShipperHQ\Lib\Checkout\AbstractService
     */
    protected $checkoutService;

    /**
     * @var array
     */
    private static $shippingOptions = [
        'liftgate_required',
        'notify_required',
        'inside_delivery',
        'destination_type',
        'limited_delivery',
        'customer_carrier',
        'customer_carrier_account',
        'customer_carrier_ph'
    ];


    /**
     * AbstractService $calendarService
     * @codeCoverageIgnore
     */
    public function __construct(
        \ShipperHQ\Lib\Checkout\AbstractService $checkoutService
    ) {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Process option select action: set option selections and request shipping rates
     *
     * @param $optionValues
     * @param $carrierId
     * @param $carrierCode
     * @param $carrierGroupId
     * @param $addressData
     * @param $cartId
     * @param bool $addressId
     * @return mixed
     */
    public function processOptionSelect(
        $optionValues,
        $carrierId,
        $carrierCode,
        $carrierGroupId,
        $addressData,
        $cartId,
        $addressId = false
    ) {
        $params = $this->getOptionSelectSaveParameters(
            $optionValues,
            $carrierId,
            $carrierCode,
            $carrierGroupId
        );
        $this->checkoutService->saveSelectedData($params);
        $this->checkoutService->cleanDownRates($cartId, $carrierCode, $carrierGroupId, $addressId);
        $addressArray = ['street' => $addressData->getStreet(), 'region' => $addressData->getRegion(),
            'region_id' => $addressData->getRegionId(), 'postcode' => $addressData->getPostcode(),
            'city' => $addressData->getCity(), 'country_id' => $addressData->getCountryId()];
        $rates = $this->checkoutService->reqeustShippingRates($cartId, $carrierCode, $carrierGroupId, $addressArray, $addressId);
        //need to do smart cleaning at this point
        $this->checkoutService->cleanDownSelectedData();
        return $rates;
    }


    /**
     * Generate selections object
     *
     * @param $optionValues
     * @param $carrierId
     * @param $carrierCode
     * @param $carrierGroupId
     * @return \ShipperHQ\Lib\Rate\CarrierSelections
     */
    public function getOptionSelectSaveParameters(
        $optionValues,
        $carrierId,
        $carrierCode,
        $carrierGroupId
    ) {

        $shippingOptions = [];
        foreach (self::$shippingOptions as $option) {
            //destination type is case sensitive in SHQ
            if (isset($optionValues[$option]) && $optionValues[$option] != '') {
                $shippingOptions[] = ['name'=> $option, 'value' => strtolower($optionValues[$option])];
            }
        }
        $selections = [
            'CarrierGroupId' => $carrierGroupId,
            'CarrierId' => $carrierId,
            'CarrierCode' => $carrierCode,
            'SelectedOptions' => $shippingOptions
        ];

        return $selections;
    }
    
    public function saveOptionSelectOnCheckoutProceed($checkoutSelection, $carrierId, $carrierCode, $carrierGroupId)
    {
        $params = $this->getOptionSelectSaveParameters($checkoutSelection, $carrierId, $carrierCode, $carrierGroupId);
        $this->checkoutService->saveSelectedData($params);
    }


    /**
     * Extract option details from rate response
     *
     * @param $carrierRate
     * @param $carrierGroupDetail
     * @return array
     */
    public function processOptionDetails($carrierRate, $carrierGroupDetail)
    {
        $options = (array)$carrierRate->availableOptions;
        $returnOptions = [];
        $this->checkForCustomerAccountOptions($carrierRate, $options);

        //SHQ16-2178 store the selected options on the rate
        $selections = [];
        foreach ($carrierRate->rates as $rate) {
            $rateArray = (array)$rate;
            if (isset($rateArray['selectedOptions'])) {
                foreach ($rateArray['selectedOptions'] as $option) {
                    foreach ($option as $oneOption) {
                        $selections[$oneOption->name] = $oneOption->value;
                    }
                }
            }
        }
        if (!empty($selections)) {
            $returnOptions['selections'] = $selections;
        }

        if (!empty($options)) {
            $formatedOptions = [];
            foreach ($options as $oneOption) {
                //we should be more dynamic here e.g.
                $optionArray = (array)$oneOption;
                $returnOption = [];
                $returnOption['show_' .$optionArray['code']] = true;
                $returnOption[$optionArray['code'] .'_values'] = (array)$optionArray['values'];
                $returnOption[$optionArray['code'] .'_type'] = $optionArray['availableOptionType'];
                //SHQ16-2178 use selected option as default value
                $returnOption[$optionArray['code'] .'_default_value'] =
                    isset($selections[$optionArray['code']]) ? $selections[$optionArray['code']] : $optionArray['defaultOptionValue'];
                $formatedOptions[$optionArray['code']] = $returnOption;
            }

            $returnOptions['carrier_id'] = $carrierRate->carrierId;
            $returnOptions['carrier_code'] = $carrierRate->carrierCode;
            $returnOptions['selectedOption'] = [];
            $returnOptions['formatedOptions'] = $formatedOptions;
        }

        return $returnOptions;
    }

    private function checkForCustomerAccountOptions($carrierRate, &$options)
    {
        //This should be server side and be included in the options sent
        if ($carrierRate->carrierType == 'customerAccount') {
            $config = [
                [
                'name'  => 'Customer Carrier',
                'code' => 'customer_carrier',
                'availableOptionType' => 'TEXT',
                'values' => null,
                'defaultOptionValue' => ''
                ],
                [
                    'name'  => 'Customer Carrier Phone Number',
                    'code' => 'customer_carrier_ph',
                    'availableOptionType' => 'TEXT',
                    'values' => null,
                    'defaultOptionValue' => ''
                ],
                [
                    'name'  => 'Customer Carrier Account Number',
                    'code' => 'customer_carrier_account',
                    'availableOptionType' => 'TEXT',
                    'values' => null,
                    'defaultOptionValue' => ''
                ],
            ];
            $options = array_merge($options, $config);
        }
    }
}
