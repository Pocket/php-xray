<?php

namespace Pkerrigan\Xray\Segment;

use JsonSerializable;
use Pkerrigan\Xray\Submission\SegmentSubmitter;

/**
 *
 * @author Patrick Kerrigan (patrickkerrigan.uk)
 * @since 13/05/2018
 */
class Segment implements JsonSerializable
{
    /**
     * @var string
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    protected $id;
    /**
     * @var string
     */
    protected $parentId;
    /**
     * @var string
     */
    protected $traceId;
    /**
     * @var string|null
     */
    protected $name;
    /**
     * @var float
     */
    protected $startTime;
    /**
     * @var float
     */
    protected $endTime;
    /**
     * @var Segment[]
     */
    protected $subsegments = [];
    /**
     * @var bool
     */
    protected $error = false;
    /**
     * @var bool
     */
    protected $fault = false;
    /**
     * @var bool
     */
    protected $sampled = false;
    /**
     * @var bool
     */
    protected $independent = false;
    /**
     * @var string[]
     */
    private $annotations;
    /**
     * @var string[]
     */
    private $metadata;
    /**
     * @var int
     */
    private $lastOpenSegment = 0;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(8));
    }

    /**
     * @return static
     */
    public function begin()
    {
        $this->startTime = microtime(true);

        return $this;
    }

    /**
     * @return static
     */
    public function end()
    {
        $this->endTime = microtime(true);

        return $this;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getType()
    {
        return $this->independent ? 'subsegment' : null;
    }

    /**
     * @param bool $error
     * @return static
     */
    public function setError($error)
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @param bool $fault
     * @return static
     */
    public function setFault($fault)
    {
        $this->fault = $fault;

        return $this;
    }

    /**
     * @return float
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @param float $startTime
     * @return Segment
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
        return $this;
    }

    /**
     * @return float
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @param float $endTime
     * @return Segment
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;
        return $this;
    }

    /**
     * @param Segment $subsegment
     * @return static
     */
    public function addSubsegment(Segment $subsegment)
    {
        if (!$this->isOpen()) {
            return $this;
        }

        $this->subsegments[] = $subsegment;
        $subsegment->setSampled($this->isSampled());

        return $this;
    }

    /**
     * @param SegmentSubmitter $submitter
     */
    public function submit(SegmentSubmitter $submitter)
    {
        if (!$this->isSampled()) {
            return;
        }

        $submitter->submitSegment($this);
    }

    /**
     * @return bool
     */
    public function isSampled()
    {
        return $this->sampled;
    }

    /**
     * @param bool $sampled
     * @return static
     */
    public function setSampled($sampled)
    {
        $this->sampled = $sampled;

        return $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $parentId
     * @return static
     */
    public function setParentId($parentId = null)
    {
        $this->parentId = $parentId;

        return $this;
    }

    /**
     * @param string $traceId
     * @return static
     */
    public function setTraceId($traceId)
    {
        $this->traceId = $traceId;

        return $this;
    }

    /**
     * @return string
     */
    public function getTraceId()
    {
        return $this->traceId;
    }

    /**
     * @return bool
     */
    public function isOpen()
    {
        return !is_null($this->startTime) && is_null($this->endTime);
    }

    /**
     * @param bool $independent
     * @return static
     */
    public function setIndependent($independent)
    {
        $this->independent = $independent;

        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @return static
     */
    public function addAnnotation($key, $value)
    {
        $this->annotations[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     * @return static
     */
    public function addMetadata($key, $value)
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * @return Segment
     */
    public function getCurrentSegment()
    {
        for ($max = count($this->subsegments); $this->lastOpenSegment < $max; $this->lastOpenSegment++) {
            if ($this->subsegments[$this->lastOpenSegment]->isOpen()) {
                return $this->subsegments[$this->lastOpenSegment]->getCurrentSegment();
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return array_filter([
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'trace_id' => $this->traceId,
            'name' => $this->name,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'subsegments' => empty($this->subsegments) ? null : $this->subsegments,
            'type' => $this->getType(),
            'fault' => $this->fault,
            'error' => $this->error,
            'annotations' => empty($this->annotations) ? null : $this->annotations,
            'metadata' => empty($this->metadata) ? null : $this->metadata
        ]);
    }
}
