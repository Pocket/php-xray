<?php

namespace Pkerrigan\Xray\Sampling;

use Pkerrigan\Xray\Sampling\RuleRepository\RuleRepository;
use Pkerrigan\Xray\Sampling\TargetRepository\AwsSdkTargetRepository;
use Pkerrigan\Xray\Sampling\TargetRepository\Target;
use Pkerrigan\Xray\Trace;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class SamplerCache
 * @package Pkerrigan\Xray
 */
class SamplerCache
{

    /**
     * @var RuleRepository
     */
    private $ruleRepository;
    /**
     * @var AwsSdkTargetRepository
     */
    private $awsSdkTargetRepository;
    /**
     * @var StateManager
     */
    private $stateManager;

    public function __construct(
        RuleRepository $ruleRepository,
        AwsSdkTargetRepository $awsSdkTargetRepository,
        StateManager $stateManager
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->awsSdkTargetRepository = $awsSdkTargetRepository;
        $this->stateManager = $stateManager;
    }


    /**
     * @return Rule[]
     * @throws InvalidArgumentException
     */
    public function getAllRules()
    {
        $segment = Trace::getInstance()->startSubsegment('SamplerCache::getAllRules');
        $rules = $this->ruleRepository->getAll();
        $segment->end();
        return $rules;
    }


    /**
     * @return Rule[]
     * @throws InvalidArgumentException
     */
    public function getAllSavedRules()
    {
        $segment = Trace::getInstance()->startSubsegment('SamplerCache::getAllSavedRules');
        $rules = $this->stateManager->getAllSavedRulesFromRules($this->ruleRepository->getAll());
        $segment->end();
        return $rules;
    }


    /**
     * Saves the rule to the cache
     *
     * @param Rule $rule
     * @throws InvalidArgumentException
     */
    public function saveRule(Rule $rule)
    {
        $segment = Trace::getInstance()->startSubsegment('SamplerCache::saveRule');
        $this->stateManager->saveRule($rule);
        $this->refreshTargets();
        $segment->end();
    }



    /**************************
     * Target Sample Updates
     *************************/

    /**
     * @throws InvalidArgumentException
     */
    private function refreshTargets()
    {
        $segment = Trace::getInstance()->startSubsegment('SamplerCache::refreshTargets');

        //TODO: see if we should report data (10 sec)
        $candidates = $this->getCandidates();
        if (!count($candidates)) {
            //No data to report
            return;
        }

        $targets = $this->awsSdkTargetRepository->getAll($candidates, $this->stateManager->getClientInstanceId());

        foreach ($targets as $target) {
            $this->updateRuleQuota($target);
        }

        $segment->end();
    }


    // Don't report a rule statistics if any of the conditions is met:
    // 1. The report time hasn't come (some rules might have larger report intervals).
    // 2. The rule is never matched.
    private function getCandidates()
    {
        $segment = Trace::getInstance()->startSubsegment('SamplerCache::getCandidates');
        $rules = $this->getAllSavedRules();

        $candidates = [];
        foreach ($rules as $rule) {
            if ($rule->everMatched() && $rule->timeToReport()) {
                $candidates[] = $rule;
            }
        }

        $segment->end();
        return $candidates;
    }


    /**
     * @param Target $target
     * @throws InvalidArgumentException
     */
    private function updateRuleQuota(Target $target)
    {
        $segment = Trace::getInstance()->startSubsegment('SamplerCache::updateRuleQuota');
        $rule = $this->stateManager->getRule($target->getRuleName());

        if ($rule === null) {
            //No rule to update
            return;
        }

        $rule->resetStatistics();
        $rule->getReservoir()->loadNewQuota(
            $target->getQuota(),
            $target->getTtl(),
            $target->getInterval()
        );

        $rule->setFixedRate($target->getRate());
        $this->stateManager->saveRule($rule);
        $segment->end();
    }
}
