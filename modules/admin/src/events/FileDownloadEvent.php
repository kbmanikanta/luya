<?php

namespace admin\events;

class FileDownloadEvent extends \yii\base\Event
{
    public $isValid = true;
    
    public $file = null;
}
