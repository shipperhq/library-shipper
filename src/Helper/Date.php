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

namespace ShipperHQ\Lib\Helper;

/**
 * Class Helper
 *
 * @package ShipperHQ\Lib\Rate
 */
class Date
{
    const DEFAULT_DATEPICKER = 'mm/dd/y';
    const DEFAULT_CLDR_FORMAT =  'MM/dd/Y';
    const DEFAULT_DATE = 'm/d/y';

    public static function getDateFormat($locale)
    {
        $dateFormat = self::getCode('date_format', $locale) ?
            self::getCode('date_format', $locale): self::DEFAULT_DATE;

        return $dateFormat;
    }

    public static function getDatepickerFormat($locale)
    {
        $datePickerFormat = self::getCode('datepicker_format', $locale) ?
            self::getCode('datepicker_format', $locale): self::DEFAULT_DATEPICKER;

        return $datePickerFormat;
    }

    public static function getCldrDateFormat($locale, $code)
    {
        $dateFormatArray = self::getCode('cldr_date_format', $locale);
        $dateFormat = is_array($dateFormatArray) && isset($code, $dateFormatArray) ? $dateFormatArray[$code]:
           self::DEFAULT_CLDR_FORMAT;
        return $dateFormat;
    }

    /**
     * Get configuration data
     *
     * @param string $type
     * @param string $code
     * @return array|bool
     */
    public static function getCode($type, $code = '')
    {
        $codes = [
            'date_format'   => [
                'en-GB'         => 'd-m-Y',
                'en-US'         => 'm/d/Y'
            ],
            'datepicker_format' => [
                'en-GB'         => 'dd-mm-yy',
                'en-US'         => 'mm/dd/yy'
            ],
            'cldr_date_format'      => [
                'en-US'            => [
                    'yMd'           => 'n/d/Y',
                    'yMMMd'         => 'M d, Y',
                    'yMMMEd'        => 'D, M d, Y',
                    'yMEd'          => 'D, n/d/Y',
                    'MMMd'          => 'M d Y',
                    'MMMEd'         => 'D, M d, Y',
                    'MEd'           => 'D, n/d/Y',
                    'Md'            => 'n/d/Y',
                    'yM'            => 'n/Y',
                    'yMMM'          => 'M Y',
                    'MMM'           => 'M',
                    'E'             => 'D',
                    'Ed'            => 'd D',
                ],
                'en-GB'            => [
                    'yMd'           => 'd-m-Y',  //important that this is not changed to d/m/Y
                    'yMMMd'         => 'd M Y',  //the slashes will cause exceptions
                    'yMMMEd'        => 'D, d M Y',
                    'yMEd'          => 'D, d/m/Y',
                    'MMMd'          => 'd M Y',
                    'MMMEd'         => 'D d M Y',
                    'MEd'           => 'D d/m/y',
                    'Md'            => 'd/m/y',
                    'yM'            => 'm/Y',
                    'yMMM'          => 'M Y',
                    'MMM'           => 'M',
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
}
