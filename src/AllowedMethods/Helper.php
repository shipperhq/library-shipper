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

namespace ShipperHQ\Lib\AllowedMethods;

/**
 * Class Helper
 *
 * @package ShipperHQ\Lib\AllowedMethods
 */
class Helper
{
    /**
     * @param $allAllowedMethodResponse array containing allowed method responses for each saved API key
     * @param $allowedMethods array that processed allowed methods are saved to
     *
     * @return array
     */
    public function extractAllowedMethodsAndCarrierConfig($allAllowedMethodResponse, &$allowedMethods)
    {
        $carrierConfig = [];

        if (!is_array($allAllowedMethodResponse)) {
            $allAllowedMethodResponse = array($allAllowedMethodResponse);
        }

        foreach ($allAllowedMethodResponse as $allowedMethodResponse) {
            $returnedMethods = $allowedMethodResponse['carrierMethods'];
            foreach ($returnedMethods as $carrierMethod) {
                $methodList = $carrierMethod['methods'];
                $methodCodeArray = [];
                foreach ($methodList as $method) {
                    $allowedMethodCode = $method['methodCode'];
                    $allowedMethodCode = preg_replace('/&|;| /', "", (string) $allowedMethodCode);
                    if (!array_key_exists($allowedMethodCode, $allowedMethods)) {
                        $methodCodeArray[$allowedMethodCode] = $method['name'];
                    }
                }
                $allowedMethods[$carrierMethod['carrierCode']] = $methodCodeArray;
                $carrierConfig[$carrierMethod['carrierCode']]['title'] = $carrierMethod['title'];
                if (isset($carrierMethod['sortOrder'])) {
                    $carrierConfig[$carrierMethod['carrierCode']]['sortOrder'] = $carrierMethod['sortOrder'];
                }
            }
        }

        return $carrierConfig;
    }

    public function getAllowedMethodsArray($allowed, $requestedCode)
    {
        $arr = [];
        foreach ($allowed as $carrierCode => $allowedMethodArray) {
            if ($requestedCode === null || $carrierCode == $requestedCode) {
                foreach ($allowedMethodArray as $methodCode => $allowedMethod) {
                    $arr[$methodCode] = $allowedMethod;
                }
            }
        }
        return $arr;
    }
}
