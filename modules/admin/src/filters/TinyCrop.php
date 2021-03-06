<?php

namespace admin\filters;

/**
 * Admin Module default Filter: Tiny Crop (40x40)
 *
 * @author Basil Suter <basil@nadar.io>
 */
class TinyCrop extends \admin\base\Filter
{
    public function identifier()
    {
        return 'tiny-crop';
    }

    public function name()
    {
        return 'Crop tiny (40x40)';
    }

    public function chain()
    {
        return [
            [self::EFFECT_THUMBNAIL, [
                'width' => 40,
                'height' => 40,
            ]],
        ];
    }
}
