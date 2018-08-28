<?php

/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\Logger\Formatter;

use Chocofamily\Http\CorrelationId;

/**
 * Class Json
 *
 * Форматирует логи в json строку
 *
 * @package RestAPI\Logger\Formatter
 */
class Json extends \Phalcon\Logger\Formatter
{
    private $appDomain;

    /** @var CorrelationId */
    private $correlationId;

    public function __construct()
    {
        $this->appDomain     = \Phalcon\Di::getDefault()->getShared('config')->domain;
        $this->correlationId = CorrelationId::getInstance();
    }

    public function format($message, $type, $timestamp, $context = null)
    {
        if (is_array($context)) {
            $message = $this->interpolate($message, $context);
        }

        $logData = [
            "type"           => $this->getTypeString($type),
            "message"        => $message,
            "server"         => $this->appDomain,
            "timestamp"      => $timestamp,
            "correlation_id" => $this->correlationId->getCorrelationId(),
            "span_id"        => $this->correlationId->getSpanId(),
        ];

        return \json_encode($logData, JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
