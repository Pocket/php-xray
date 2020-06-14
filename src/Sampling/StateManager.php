<?php

namespace Pkerrigan\Xray\Sampling;

use Pkerrigan\Xray\Utils;
use Psr\SimpleCache\CacheInterface;

/**
 * Manages state. In most other multi-threaded applications this is the runtime process.
 *
 * Class StateManager
 * @package Pkerrigan\Xray\Sampling
 */
class StateManager
{
    const CLIENT_ID_CACHE = 'StateManagerClientID';

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }


    /**
     * Gets a merged rule for a rule
     * @param Rule $rule
     * @return Rule
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function updateRule(Rule $rule)
    {
        if (($oldRule = $this->getRule($rule->getCacheKey())) !== null) {
            $rule = $rule->merge($oldRule);
        }

        $this->saveRule($rule);
        return $rule;
    }

    /**
     * Gets a rule from a cache key
     * @param string $cacheKey
     * @return Rule
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getRule($cacheKey)
    {
        $cacheKey = Utils::stripInvalidCharacters($cacheKey);
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        return null;
    }

    /**
     * Saves a Rule
     * @param Rule $rule
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function saveRule(Rule $rule)
    {
        $this->cache->set($rule->getCacheKey(), $rule);
    }

    /**
     * @param Rule[] $rules
     */
    public function getAllSavedRulesFromRules(array $rules)
    {
        $savedRules = [];
        foreach ($rules as $rule) {
            $savedRules[] = $this->updateRule($rule);
        }

        return $savedRules;
    }


    /**
     * Gets a cached client id for this app process
     * @return string
     */
    public function getClientInstanceId()
    {
        if ($this->cache->has(self::CLIENT_ID_CACHE)) {
            return $this->cache->get(self::CLIENT_ID_CACHE);
        }

        $clientID = bin2hex(random_bytes(12));
        $this->cache->set(self::CLIENT_ID_CACHE, $clientID);
        return $clientID;
    }
}
