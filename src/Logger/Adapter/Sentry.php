<?php

namespace Chocofamily\Logger\Adapter;

use Chocofamily\Logger\Formatter\Sentry as Formatter;
use Chocofamily\Http\CorrelationId;
use Phalcon\Config;
use Phalcon\Logger;

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

    /** @var \Raven_Client */
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
    }

    /**
     * @param string $level
     *
     * @return int|null
     */
    public static function toPhalconLogLevel(string $level)
    {
        return array_flip(static::LOG_LEVELS)[$level] ?? null;
    }

    /**
     * @param int $level
     *
     * @return string|null
     */
    public static function toSentryLogLevel(int $level)
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

        $this->send($message, $type, $context);
    }

    /**
     * Logs the exception to Sentry.
     *
     * @param \Throwable $exception
     * @param array      $context
     * @param int|null   $type
     *
     * @return void
     */
    public function logException(\Throwable $exception, array $context = [], int $type = null)
    {
        foreach ($this->config->dontReport as $ignore) {
            if ($exception instanceof $ignore) {
                return;
            }
        }

        $this->send($exception, $type, $context);
    }

    /**
     * Sets the user context &/or identifier.
     *
     * @param array $context
     *
     * @return \CrazyFactory\PhalconLogger\Adapter\Sentry
     */
    public function setUserContext(array $context): Sentry
    {
        if ($this->client) {
            $this->client->user_context($context);
        }

        return $this;
    }

    /**
     * Sets the extra context (arbitrary key-value pair).
     *
     * @param array $context
     *
     * @return \CrazyFactory\PhalconLogger\Adapter\Sentry
     */
    public function setExtraContext(array $context): Sentry
    {
        if ($this->client) {
            $this->client->extra_context($context);
        }

        return $this;
    }

    /**
     * Sets the tag for logs which can be used for analysis in Sentry backend.
     *
     * @param string $key
     * @param string $value
     *
     * @return \CrazyFactory\PhalconLogger\Adapter\Sentry
     */
    public function setTag(string $key, string $value): Sentry
    {
        if ($this->client) {
            if (strlen($value) > 200) {
                $value = substr($value, 0, 200);
            }
            
            $this->client->tags_context([$key => $value]);
        }

        return $this;
    }

    /**
     * Append bread crumbs to the Sentry log that can be used to trace process flow.
     *
     * @param string $message
     * @param string $category
     * @param array  $data
     * @param int    $type
     *
     * @return \CrazyFactory\PhalconLogger\Adapter\Sentry
     */
    public function addCrumb(string $message, string $category = 'default', array $data = [], int $type = null): Sentry
    {
        if ($this->client) {
            $level = static::toSentryLogLevel($type ?? Logger::INFO);
            $crumb = compact('message', 'category', 'data', 'level') + ['timestamp' => time()];

            $this->client->breadcrumbs->record($crumb);
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
     * Sets the raven client.
     *
     * @param \Raven_Client $client
     *
     * @return \CrazyFactory\PhalconLogger\Adapter\Sentry
     */
    public function setClient(\Raven_Client $client): Sentry
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Gets the raven client.
     *
     * @return \Raven_Client|null
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
     * Instantiates the Raven_Client.
     *
     * @return void
     */
    protected function initClient()
    {
        if (PHP_SAPI == "cli") {
            $this->config->options->curl_method = 'sync';
        }

        // Only initialize in configured environment(s).
        if (!in_array($this->environment, $this->config->environments->toArray(), true)) {
            return;
        }

        $key     = $this->config->credential->key;
        $project = $this->config->credential->projectId;
        $domain  = $this->config->credential->domain;

        if ($key && $project && $domain) {
            $dsn     = sprintf($this->dsnTemplate, $key, $domain, $project);
            $options = ['environment' => $this->environment] + $this->config->options->toArray();

            $this->setClient(new \Raven_Client($dsn, $options));
        }
    }

    /**
     * Send Logs to Sentry for configured log levels.
     *
     * @param string|\Throwable $loggable
     * @param int               $type
     * @param array             $context
     *
     * @return void
     */
    protected function send($loggable, int $type, array $context = [])
    {
        if (!$this->shouldSend($type)) {
            return;
        }

        $context += ['level' => static::toSentryLogLevel($type)];

        // Wipe out extraneous keys. Issue #3.
        $context = array_intersect_key($context, array_flip([
            'context',
            'extra',
            'fingerprint',
            'level',
            'logger',
            'release',
            'tags',
        ]));

        // Tag current request ID for search/trace.
        $this->client->tags_context(['request' => $this->correlationId->getCorrelationId()]);

        $this->lastEventId = $loggable instanceof \Throwable
            ? $this->client->captureException($loggable, $context)
            : $this->client->captureMessage($loggable, [], $context);
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
        return (bool) $this->client && in_array($type, $this->config->levels->toArray(), true);
    }
}
