<?php

namespace Pkerrigan\Xray;

use Pkerrigan\Xray\Segment\HttpTrait;
use Pkerrigan\Xray\Segment\Plugins\ECS;
use Pkerrigan\Xray\Segment\Segment;

/**
 *
 * @author Patrick Kerrigan (patrickkerrigan.uk)
 * @since 13/05/2018
 */
class Trace extends Segment
{
    use HttpTrait;

    /**
     * @var static
     */
    private static $instance;
    /**
     * @var string
     */
    private $serviceVersion;
    /**
     * @var string
     */
    private $serviceEnvironment;
    /**
     * @var string
     */
    private $user;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * @param string $traceHeader
     * @return static
     */
    public function setTraceHeader($traceHeader = null)
    {
        if (is_null($traceHeader)) {
            return $this;
        }

        $parts = explode(';', $traceHeader);

        $variables = array_map(function ($str) {
            return explode('=', $str);
        }, $parts);

        $variables = array_column($variables, 1, 0);

        if (isset($variables['Root'])) {
            $this->setTraceId($variables['Root']);
        }
        $this->setSampled(isset($variables['Sampled']) && $variables['Sampled']);
        $this->setParentId(isset($variables['Parent']) ? $variables['Parent'] : null);

        return $this;
    }

    /**
     * @param string $serviceVersion
     * @return static
     */
    public function setServiceVersion($serviceVersion)
    {
        $this->serviceVersion = $serviceVersion;

        return $this;
    }

    /**
     * @param string $serviceEnvironment
     * @return static
     */
    public function setServiceEnvironment($serviceEnvironment)
    {
        $this->serviceEnvironment = $serviceEnvironment;

        return $this;
    }

    /**
     * @param string $user
     * @return static
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param string $clientIpAddress
     * @return static
     */
    public function setClientIpAddress($clientIpAddress)
    {
        $this->clientIpAddress = $clientIpAddress;

        return $this;
    }

    /**
     * @param string $userAgent
     * @return static
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function begin()
    {
        parent::begin();

        if (is_null($this->traceId)) {
            $this->generateTraceId();
        }

        return $this;
    }

    /**
     * Helper function to add ECS Plugin data
     * @return Trace
     */
    public function addECSPlugin()
    {
        return $this->addPluginData(new ECS());
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        $data = parent::jsonSerialize();

        $data['http'] = $this->serialiseHttpData();
        $data['service'] = array_filter([
            'version' => $this->serviceVersion,
            'environment' => $this->serviceEnvironment
        ]);
        $data['user'] = $this->user;

        return array_filter($data);
    }

    /**
     * Gets a trace header value that we can use to put on all future HTTP Requests
     * Put in `X-Amzn-Trace-Id`
     *
     * @return string
     */
    public function getAmazonTraceHeader()
    {
        return 'Root=' . $this->getTraceId() . ';' .
            'Parent=' . $this->getTraceId() . ';' .
            'Sampled=' . ($this->isSampled() ? '1' : '0');
    }

    private function generateTraceId()
    {
        $startHex = dechex((int)$this->startTime);
        $uuid = bin2hex(random_bytes(12));

        $this->setTraceId("1-{$startHex}-{$uuid}");
    }
}
