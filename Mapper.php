<?php

namespace Boomgo;

/**
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 */
class Mapper
{
    public function __construct()
    {
        
    }

    /**
     * Normalize data for mongo
     * 
     * This code chunk was extract from the Symfony framework
     * and is subject to the MIT license. Please see the LICENCE
     * at https://github.com/symfony/symfony
     * 
     * (c) Fabien Potencier <fabien@symfony.com>
     * @author Nils Adermann <naderman@naderman.de>
     * 
     * @param  mixed $data
     * @return array
     */
    public function normalize($data)
    {
        if (null === $data || is_scalar($data)) {
            return $data;
        }
        if (is_object($data)) {
            return $this->toArray($data);
        }
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->normalize($val);
            }

            return $data;
        }
        throw new \RuntimeException('An unexpected value could not be normalized: '.var_export($data, true));
    }

    /**
     * Convert this object to array
     * 
     * @param  object  $object  An object to convert.
     * @param  Boolean $embedId True to force _id in embedded document
     * @return Array
     */
    public function toArray($object)
    {
        $array = array();

        if (!is_object($object)) {
            throw new \InvalidArgumentException('Argument must be an object');
        }

        $reflectedObject = new \ReflectionObject($object);
        
        // Assert that a stand alone document must have an id field
        if (!$reflectedObject->hasProperty('id') || 
            !$reflectedObject->hasMethod('getId') ||
            !$this->isValidAccessor($reflectedObject->getMethod('getId'))) {
            throw new \RuntimeException('Invalid identifier prerequisite');
        }

        // Fetch mandatory _id first
        $array['_id'] = $object->getId();

        $reflectedProperties = $reflectedObject->getProperties();

        foreach ($reflectedProperties as $reflectedProperty) {
            if ($this->isMongoProperty($reflectedProperty)) {
                $accessorName = 'get'.ucfirst($reflectedProperty->getName());
                
                if ($reflectedObject->hasMethod($accessorName)) {
                    $reflectedMethod = $reflectedObject->getMethod($accessorName);

                    if ($this->isValidAccessor($reflectedMethod)) {
                        $key = $this->uncamelize($reflectedProperty->getName());
                        $value = $reflectedMethod->invoke($object);

                        // Recursively normalize nested non-scalar data
                        if (null !== $value && !is_scalar($value)) {
                            $value = $this->normalize($value);
                        }

                        $array[$key] = $value;
                    }
                }
            }
        }

        // Unset id field since we already have _id and it's non sens for mongo 
        unset($array['id']);

        // If all keys has a null value, we should return an empy array.
        // Since PHP suck balls (isset, empty, array_value) implode is the hack.
        if (!array_filter($array)) {
            $array = array();
        }

        return $array;
    }

    /**
     * Hydrate an object
     * 
     * @param  array  $array     An array of data from mongo
     * @param  string $className A full qualified domain name
     * @return object
     */
    public function hydrate($className, array $array)
    {
        if (!isset($array['_id'])) {
            throw new \InvalidArgumentException('Data without _id are not yet supported');
        }

        $array['id'] = $array['_id'];
        unset($array['_id']);

        $reflectedClass = new \ReflectionClass($className);
        $constructor = $reflectedClass->getConstructor();

        if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new \RuntimeException('Unable to hydrate object requiring constructor param');
        }

        $object = new $className;

        $reflectedObject = new \ReflectionObject($object);

        // Assert that a stand alone document must have an id field
        if (!$reflectedObject->hasProperty('id') || 
            !$reflectedObject->hasMethod('setId') ||
            !$this->isValidMutator($reflectedObject->getMethod('setId'))) {
            throw new \RuntimeException('Invalid identifier prerequisite');
        }

        foreach ($array as $key => $value) {
            $camelized = $this->camelize($key);
            $attributeName = lcfirst($camelized);
            $mutatorName = 'set' . $camelized;

            if ($reflectedObject->hasProperty($attributeName) && $reflectedObject->hasMethod($mutatorName)) {
                $reflectedProperty = $reflectedObject->getProperty($attributeName);

                if ($this->isMongoProperty($reflectedProperty)) {
                    $reflectedMethod = $reflectedObject->getMethod($mutatorName);

                    if ($this->isValidMutator($reflectedMethod)) {
                        $reflectedMethod->invoke($object, $value);
                    }
                }
            }
        }

        return $object;
    }

    /**
     * Convert underscored string to camelCase
     * 
     * @param  string $string 
     * @return string
     */
    public function camelize($string)
    {
        $words = explode('_', strtolower($string));
        
        $camelized = '';
        
        foreach ($words as $word) {
            if (strpos($word,'_') === false) {
                $camelized .= ucfirst(trim($word));
            }
        }

        return $camelized;
    }

    /**
     * Convert camelCase string to underscore
     * 
     * @param  string $string a camelCase string
     * @return string
     */
    public function uncamelize($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
    }

    /**
     * Check if an object property should be persisted.
     *
     * @param  ReflectionProperty $property the property to check
     * @return Boolean True if the property should be stored
     */
    private function isMongoProperty(\ReflectionProperty $property)
    {
        return (0 < strpos($property->getDocComment(), '@Mongo'));
    }

    /**
     * Check if the getter is public and has no required argument.
     * 
     * @param  ReflectionMethod $method the method to check
     * @return Boolean True if the getter is valid
     */
    private function isValidAccessor(\ReflectionMethod $method)
    {
        return ($method->isPublic() && 
                0 === $method->getNumberOfRequiredParameters());
    }

    /**
     * Check if the setter is public and has one required argument.
     * 
     * @param  ReflectionMethod $method the method to check
     * @return Boolean True if the setter is valid
     */
    private function isValidMutator(\ReflectionMethod $method)
    {
        return ($method->isPublic() && 
                1 === $method->getNumberOfRequiredParameters());
    }
}