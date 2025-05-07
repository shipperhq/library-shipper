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

namespace ShipperHQ\Lib\AdminOrder;

/**
 * Abstract class service
 *
 * @package ShipperHQ\Lib\Checkout
 */
abstract class AbstractService
{
    /*
     * Cache data selected at checkout for use in rate request
     */
    abstract public function saveSelectedData($data);

    /*
     * Remove carrier shipping rates before re-requesting
     */
    abstract public function cleanDownRates($cartId, $carrierCode, $carriergroupId);

    /*
     * Request shipping rates for specified carrier
     */
    abstract public function reqeustShippingRates($cartId, $carrierCode, $carriergroupId);
    /*
     * Removed cached data selected at checkout
     */
    abstract public function cleanDownSelectedData();

    public function getKey(\ShipperHQ\Lib\Rate\CarrierSelections $data)
    {
        $key = $data->getCarrierGroupId() .'_' .$data->getCarrierId();
        return $key;
    }
}
