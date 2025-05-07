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

namespace ShipperHQ\Lib\Quote;

/**
 * Abstract class service
 *
 * @package ShipperHQ\Lib\Quote
 */
abstract class AbstractService
{
   /*
     * Remove carrier shipping rates for given code
     */
    abstract function cleanDownRates($address, $carrierCode, $carriergroupId, $addressId = false);
}
