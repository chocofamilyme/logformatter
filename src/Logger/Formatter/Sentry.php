<?php

namespace Chocofamily\Logger\Formatter;

use Phalcon\Logger;

class Sentry extends Logger\Formatter
{
    /**
     * @inheritdoc
     */
    public function format($message, $type, $timestamp, $context = null)
    {
        return $this->interpolate($message, $context ?: []);
    }
}
