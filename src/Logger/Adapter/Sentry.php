<?php

namespace Chocofamily\Logger\Adapter;

use Chocofamily\Logger\Formatter\Sentry as Formatter;
use Chocofamily\Http\CorrelationId;
use Phalcon\Config;
use Phalcon\Logger;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Severity;
use Sentry\State\Scope;

/**
 * The Sentry logger adapter for phalcon.
 */
class Sentry extends Logger\Adapter
{
    // The map of Phalcon log levels to Sentry log levels. Throughout the application, we use only Phalcon levels.
    const LOG_LEVELS = [
        Logger::EMERGENCE => 'fatal',
        Logger::CRITICAL  => 'fatal',
        Logger::ALERT     => 'info',
        Logger::ERROR     => 'error',
        Logger::WARNING   => 'warning',
        Logger::NOTICE    => 'debug',
        Logger::INFO      => 'info',
        Logger::DEBUG     => 'debug',
        Logger::CUSTOM    => 'info',
        Logger::SPECIAL   => 'info',
    ];

    /** @var Client */
    protected $client;

    /** @var string The sentry event ID from last request */
    protected $lastEventId;

    /** @var Config */
    protected $config;

    protected $dsnTemplate = 'https://%s@%s/%s';

    /** @var CorrelationId */
    protected $correlationId;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var Scope
     */
    protected $scope;

    /**
     * Instantiates new Sentry Adapter with given configuration.
     *
     * @param \Phalcon\Config|array $config
     */
    public function __construct(Config $config, string $environment)
    {
        $this->config        = $config;
        $this->environment   = $environment;
        $this->correlationId = CorrelationId::getInstance();
        $this->initClient();
        $this->initScope();
    }

    /**
     * @param int $level
     *
     * @return string|null
     */
    public static function toSentryLogLevel(int $level): ?string
    {
        return static::LOG_LEVELS[$level] ?? null;
    }

    /**
     * Logs the message to Sentry.
     *
     * @param string $message
     * @param int    $type
     * @param int    $time
     * @param array  $context
     *
     * @return void
     */
    public function logInternal(string $message, int $type, int $time, array $context = [])
    {
        $message = $this->getFormatter()->interpolate($message, $context);

        $this->send($message, $type);
    }

    /**
     * Logs the exception to Sentry.
     *
     * @param \Throwable $exception
     * @param int        $type
     *
     * @return void
     */
    public function logException(\Throwable $exception, int $type)
    {
        if (isset($this->config->dontReport)) {
            foreach ($this->config->dontReport as $ignore) {
                if ($exception instanceof $ignore) {
                    return;
                }
            }
        }

        $this->send($exception, $type);
    }

    /**
     * Sets the tag for logs which can be used for analysis in Sentry backend.
     *
     * @param string $key
     * @param string $value
     *
     * @return Sentry
     */
    public function setTag(string $key, string $value): Sentry
    {
        if ($this->client) {
            if (mb_strlen($value) > 200) {
                $value = mb_substr($value, 0, 200);
            }

            $this->scope->setTag($key, $value);
        }

        return $this;
    }

    /**
     * Gets the last event ID from Sentry.
     *
     * @return string|null
     */
    public function getLastEventId()
    {
        return $this->lastEventId;
    }

    /**
     * Sets the http client.
     *
     * @param Client $client
     *
     * @return Sentry
     */
    public function setClient(Client $client): Sentry
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Gets the http client.
     *
     * @return Client|null
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @inheritdoc
     */
    public function getFormatter()
    {
        if (empty($this->_formatter)) {
            $this->_formatter = new Formatter;
        }

        return $this->_formatter;
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
    }

    /**
     * Instantiates the http client.
     *
     * @return void
     */
    protected function initClient()
    {
        if (!isset($this->config->environments)) {
            return;
        }

        // Only initialize in configured environment(s).
        if (!in_array($this->environment, $this->config->environments->toArray(), true)) {
            return;
        }
        if (!isset($this->config->credential)) {
            return;
        }

        $key     = $this->config->credential->key;
        $project = $this->config->credential->projectId;
        $domain  = $this->config->credential->domain;

        if ($key && $project && $domain) {
            $dsn     = sprintf($this->dsnTemplate, $key, $domain, $project);
            $options = ['dsn' => $dsn];
            if (isset($this->config->options)) {
                $options += $this->config->options->toArray();
            }

            $client = ClientBuilder::create($options)->getClient();
            $this->setClient($client);
        }
    }

    /**
     * @return void
     */
    protected function initScope(): void
    {
        $this->scope = new Scope();
    }

    /**
     * Send Logs to Sentry for configured log levels.
     *
     * @param string|\Throwable $loggable
     * @param int               $type
     *
     * @return void
     */
    protected function send($loggable, int $type)
    {
        if (!$this->shouldSend($type)) {
            return;
        }

        $this->scope->setLevel(new Severity(static::toSentryLogLevel($type)));
        $this->scope->setTag('correlationId', $this->correlationId->getCorrelationId());

        $this->lastEventId = $loggable instanceof \Throwable
            ? $this->client->captureException($loggable, $this->scope)
            : $this->client->captureMessage($loggable, null, $this->scope);
    }

    /**
     * Should we send this log type to Sentry?
     *
     * @param int $type
     *
     * @return bool
     */
    protected function shouldSend(int $type): bool
    {
        if (!isset($this->config->levels)) {
            return false;
        }

        return $this->client && in_array($type, $this->config->levels->toArray(), true);
    }
}
