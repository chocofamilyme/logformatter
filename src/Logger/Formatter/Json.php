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
    const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

    private $appDomain;

    /** @var CorrelationId */
    private $correlationId;

    /**
     * @var string the key for 'context' fields from the Monolog record
     */
    protected $contextKey = 'context';

    /**
     * @var Normalizer
     */
    private $normalizer;

    public function __construct()
    {
        $this->appDomain     = \Phalcon\Di::getDefault()->getShared('config')->domain;
        $this->correlationId = CorrelationId::getInstance();
        $this->normalizer = new Normalizer();
    }

    public function format($message, $type, $timestamp, $context = null)
    {
        if (is_array($context)) {
            $message = $this->interpolate($message, $context);
            $message[$this->contextKey] = $this->normalizer->normalize($context);
        }

        $logData = [
            "type"           => $this->getTypeString($type),
            "message"        => $message,
            "server"         => $this->appDomain,
            "timestamp"      => $timestamp,
            "correlation_id" => $this->correlationId->getCorrelationId(),
            "span_id"        => $this->correlationId->getSpanId(),
        ];

        return \json_encode($logData, self::DEFAULT_JSON_FLAGS).PHP_EOL;
    }
}
