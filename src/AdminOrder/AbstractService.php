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
