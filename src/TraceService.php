<?php

namespace Pkerrigan\Xray;

use Pkerrigan\Xray\Sampling\RuleMatcher;
use Pkerrigan\Xray\Sampling\RuleRepository\RuleRepository;
use Pkerrigan\Xray\Submission\DaemonSegmentSubmitter;
use Pkerrigan\Xray\Submission\SegmentSubmitter;

/**
 * This layer sits ontop of the segment submitter to control which traces are submitted
 *
 * @author Niklas Ekman <nikl.ekman@gmail.com>
 * @since 01/07/2019
 */
class TraceService
{

    /** @var RuleRepository */
    private $samplingRuleRepository;

    /** @var SegmentSubmitter */
    private $segmentSubmitter;


    public function __construct(
        RuleRepository $samplingRuleRepository,
        SegmentSubmitter $segmentSubmitter = null
    ) {
        $this->samplingRuleRepository = $samplingRuleRepository;
        $this->segmentSubmitter = ($segmentSubmitter !== null) ? $segmentSubmitter : new DaemonSegmentSubmitter();
    }

    public function submitTrace(Trace $trace)
    {
        $samplingRules = $this->samplingRuleRepository->getAll();
        $samplingRule = RuleMatcher::matchFirst($trace, $samplingRules);

        $isSampled = $samplingRule !== null && Utils::randomPossibility($samplingRule->getFixedRate() * 100);
        $trace->setSampled($isSampled);

        if ($isSampled) {
            $this->segmentSubmitter->submitSegment($trace);
        }
    }
}
