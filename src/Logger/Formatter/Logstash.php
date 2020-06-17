<?php
declare(strict_types = 1);

namespace Chocofamily\Logger\Formatter;

use Phalcon\Config;
use Phalcon\Di;
use Phalcon\Logger\Formatter;

final class Logstash extends Formatter
{
    const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @var string the name of the system for the Logstash log message, used to fill the @source field
     */
    protected $systemName;

    /**
     * @var string an application name for the Logstash log message, used to fill the @type field
     */
    protected $applicationName;

    /**
     * @var string the key for 'extra' fields from the Monolog record
     */
    protected $extraKey;

    /**
     * @var string the key for 'context' fields from the Monolog record
     */
    protected $contextKey = 'context';

    protected $normalizer;

    public function __construct()
    {
        /** @var Config $config */
        $config = Di::getDefault()->getShared('config');

        $this->systemName      = $config->get('server', gethostname());
        $this->applicationName = $config->get('domain');

        $this->normalizer = new Normalizer();
    }

    public function format($message, $type, $timestamp, $context = null)
    {
        $message = [
            '@timestamp'    => gmdate('c', $timestamp),
            '@version'      => 1,
            'host'          => $this->systemName,
            'message'       => $message,
            'type'          => $this->applicationName,
            'datetime'      => $timestamp,
            'level'         => $this->getTypeString($type),
            'monolog_level' => $type,
        ];

        if ($context) {
            $message[$this->contextKey] = $this->normalizer->normalize($context);
        }


        return \json_encode($message, self::DEFAULT_JSON_FLAGS);
    }
}
