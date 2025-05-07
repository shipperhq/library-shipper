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
class CarrierSelections
{
    public $carrierGroupId;
    public $carrierCode;
    public $carrierId;
    public $selectedDate;
    public $selectedLocation;
    public $selectedOptions;

    /**
     * CarrierSelections constructor.
     * @param $carrierGroupId
     * @param $carrierCode
     * @param $carrierId
     */
    public function __construct($carrierGroupId = '', $carrierCode = '', $carrierId = '')
    {
        $this->carrierGroupId = $carrierGroupId;
        $this->carrierCode = $carrierCode;
        $this->carrierId = $carrierId;
    }

    /**
     * @return boolean
     */
    public function getCarrierGroupId()
    {
        return $this->carrierGroupId;
    }

    /**
     * @param boolean $carrierGroupId
     */
    public function setCarrierGroupId($carrierGroupId)
    {
        $this->carrierGroupId = $carrierGroupId;
    }

    /**
     * @return boolean
     */
    public function getCarrierCode()
    {
        return $this->carrierCode;
    }

    /**
     * @param boolean $carrierCode
     */
    public function setCarrierCode($carrierCode)
    {
        $this->carrierCode = $carrierCode;
    }

    /**
     * @return null
     */
    public function getCarrierId()
    {
        return $this->carrierId;
    }

    /**
     * @param null $carrierId
     */
    public function setCarrierId($carrierId)
    {
        $this->carrierId = $carrierId;
    }

    /**
     * @return mixed
     */
    public function getSelectedDate()
    {
        return $this->selectedDate;
    }

    /**
     * @param mixed $selectedDate
     */
    public function setSelectedDate($selectedDate)
    {
        $this->selectedDate = $selectedDate;
    }

    /**
     * @return mixed
     */
    public function getSelectedLocation()
    {
        return $this->selectedLocation;
    }

    /**
     * @param mixed $selectedLocation
     */
    public function setSelectedLocation($selectedLocation)
    {
        $this->selectedLocation = $selectedLocation;
    }

    /**
     * @return mixed
     */
    public function getSelectedOptions()
    {
        return $this->selectedOptions;
    }

    /**
     * @param mixed $selectedOptions
     */
    public function setSelectedOptions($selectedOptions)
    {
        $this->selectedOptions = $selectedOptions;
    }
}
