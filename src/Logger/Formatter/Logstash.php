<?php
declare(strict_types = 1);

namespace Chocofamily\Logger\Formatter;

use Phalcon\Di;
use Phalcon\Logger\Formatter;

final class Logstash extends Formatter
{
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

    /**
     * @var string
     */
    private $dateFormat;

    public function __construct()
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        $this->dateFormat = 'Y-m-d\TH:i:s.uP';

        $this->systemName      = $systemName ?? gethostname();
        $this->applicationName = Di::getDefault()->getShared('config')->get('domain');
    }

    public function format($message, $type, $timestamp, $context = null)
    {
        $message = [
            '@timestamp' => $timestamp,
            '@version'   => 1,
            'host'       => $this->systemName,
            'message'    => $message,
            'type'       => $this->getTypeString($type),
        ];

        if ($this->applicationName) {
            $message['type'] = $this->applicationName;
        }

        if ($context) {
            $message[$this->contextKey] = $context;
        }

        return \json_encode($message, JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
