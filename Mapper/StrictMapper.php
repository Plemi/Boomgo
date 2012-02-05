<?php

/**
 * This file is part of the Boomgo PHP ODM.
 *
 * http://boomgo.org
 * https://github.com/Retentio/Boomgo
 *
 * (c) Ludovic Fleury <ludo.fleury@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Boomgo\Mapper;

use Boomgo\Parser\ParserInterface;
use Boomgo\Cache\CacheInterface;

/**
 * StrictMapper
 *
 * Break schemaless mongo feature,
 * data processing is only ruled by a Map definition.
 *
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 */
class StrictMapper extends MapperProvider implements MapperInterface
{
    /**
     * @var ParserInterface
     */
    private $parser;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * Constructor
     *
     * @param FormatterInterface An key/attribute formatter
     * @param string $annotation The annotation used for mapping
     */
    public function __construct(ParserInterface $parser, CacheInterface $cache)
    {
        $this->setParser($parser);
        $this->setCache($cache);
    }

    /**
     * Defines the parser
     *
     * @param ParserInterface $parser
     */
    public function setParser(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Returns the parser used
     *
     * @return ParserInterface
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * Define the cache driver
     *
     * @param CacheInterface $cache
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Returns the cache driver used
     *
     * @return CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Returns a map
     * Reused from the cache or on-the-fly parsed then cached
     *
     * @param  string $class
     * @return Map
     */
    public function getMap($class)
    {
        if ($this->cache->contains($class)) {
            $map = $this->cache->fetch($class);
        } else {
            $map = $this->parser->buildMap($class);
            $this->cache->save($class, $map);
        }

        return $map;
    }

    /**
     * Convert this object to array
     *
     * @param  object  $object  An object to convert.
     * @return Array
     */
    public function toArray($object)
    {
        if (!is_object($object)) {
            throw new \InvalidArgumentException('Argument must be an object');
        }

        $reflectedObject = new \ReflectionObject($object);
        $className = $reflectedObject->getName();

        $map = $this->getMap($className);

        $array = array();
        $attributes = $map->getMongoIndex();

        foreach ($attributes as $key => $attribute) {
            $value = null;
            if ($map->hasAccessorFor($key)) {
                $accessor = $map->getAccessorFor($key);
                $value = $object->$accessor();
            } else {
                $value = $object->$attribute;
            }

            // Recursively normalize nested non-scalar data
            if (null !== $value) {
                if (!is_scalar($value)) {
                    $value = $this->normalize($value);
                }
                $array[$key] = $value;
            }
        }

        // If all keys has a null value, we should return an empy array.
        // PHP suck balls (isset, empty, array_value)
        if (!array_filter($array)) {
            $array = array();
        }

        return $array;
    }

    /**
     * Hydrate an object
     *
     * @param  string $object A full qualified domain name or an object
     * @param  array  $array An array of data from mongo
     * @return object
     */
    public function hydrate($object, array $array)
    {
        if (is_string($object)) {
            $className = $object;

            $reflectedClass = new \ReflectionClass($className);
            $constructor = $reflectedClass->getConstructor();

            if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                throw new \RuntimeException('Unable to hydrate an object requiring constructor param');
            }

            $object = new $object;

        } elseif (is_object($class)) {
            $reflectedObject = new \ReflectionObject($object);
            $className = $reflectedObject->getName();
        }

        $map = $this->getMap($className);

        foreach ($array as $key => $value) {
            if (null !== $value && $map->hasAttributeFor($key)) {

                $attribute = $map->getAttributeFor($key);

                if ($map->hasEmbedMapFor($key)) {
                    $value = $this->hydrateEmbed($map, $key, $value);
                }

                if ($map->hasMutatorFor($key)) {
                    $mutator = $map->getMutatorFor($key);
                    $object->$mutator($value);
                } else {
                    $object->$attribute = $value;
                }
            }
        }
        return $object;
    }

    /**
     * Hydrate embed documents from a super Map
     *
     * @param  Map    $map    The super map
     * @param  string $key    The key defined as embedding doc
     * @param  mixed  $value  The embed data
     * @return mixed
     */
    protected function hydrateEmbed(Map $map, $key, $value)
    {
        // Embed declaration
        $embedType = $map->getEmbedTypeFor($key);
        $embedMap = $map->getEmbedMapFor($key);

        if (!is_array($value)) {
            throw new \RuntimeException('Embedded document or collection expect an array');
        }

        if ($embedType == Map::DOCUMENT) {
            // Embed document

            // Expect an hash (associative array), @todo maybe remove this check ?
            if (array_keys($value) === range(0, sizeof($value) - 1)) {
                throw new \RuntimeException('Embedded document expect an associative array');
            }

            $value = $this->hydrate($embedMap->getClass(), $value);

        } elseif ($embedType == Map::COLLECTION) {
            // Embed collection

            // Expect an array (numeric array), @todo maybe remove this check ?
            if (array_keys($value) !== range(0, sizeof($value) - 1)) {
                throw new \RuntimeException('Embedded collection expect a numeric-indexed array');
            }

            $collection = array();

            // Recursively hydrate embed documents
            foreach ($value as $embedValue) {
               $collection[] = $this->hydrate($embedMap->getClass(), $embedValue);
            }

            $value = $collection;
        }

        return $value;
    }
}