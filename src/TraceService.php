<?php

namespace Pkerrigan\Xray;

use Pkerrigan\Xray\Submission\SegmentSubmitter;

/**
 * This layer sits ontop of the segment submitter to control which traces are submitted
 *
 * @author Niklas Ekman <nikl.ekman@gmail.com>
 * @since 01/07/2019
 */
class TraceService
{


    /**
     * @var SegmentSubmitter
     */
    private $segmentSubmitter;

    /**
     * @var Sampler
     */
    private $sampler;


    public function __construct(
        Sampler $sampler,
        SegmentSubmitter $segmentSubmitter
    ) {
        $this->segmentSubmitter = $segmentSubmitter;
        $this->sampler = $sampler;
    }

    /**
     * Adds a sampling decision to the Trace
     * @param Trace $trace
     * @return Trace
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function addSamplingDecision(Trace $trace)
    {
        return $trace->setSampled($this->sampler->shouldSample($trace));
    }


    /**
     * Submits a trace without deciding the sampling
     * @param Trace $trace
     */
    public function submitTrace(Trace $trace)
    {
        if ($trace->isSampled()) {
            $this->segmentSubmitter->submitSegment($trace);
        }
    }
}
