<?php

/**
 * This file is part of the Boomgo PHP ODM for MongoDB.
 *
 * http://boomgo.org
 * https://github.com/Retentio/Boomgo
 *
 * (c) Ludovic Fleury <ludo.fleury@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Boomgo\Builder;

use Boomgo\Formatter\FormatterInterface;
use Boomgo\Parser\ParserInterface;

/**
 * MapBuilder
 *
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 * @author David Guyon <dguyon@gmail.com>
 */
class MapBuilder
{
    /**
     * @var Boomgo\Parser\ParserInterface
     */
    protected $parser;

    /**
     * @var Boomgo\Formatter\FormatterInterface
     */
    protected $formatter;

    /**
     * @var string
     */
    protected $mapClassName;

    /**
     * @var string
     */
    protected $definitionClassName;

    /**
     * Constructor defines the Parser & Formatter
     *
     * @param ParserInterface    $parser
     * @param FormatterInterface $formatter
     */
    public function __construct(ParserInterface $parser, FormatterInterface $formatter)
    {
        $this->setParser($parser);
        $this->setFormatter($formatter);
        $this->mapClassName = 'Boomgo\\Builder\\Map';
        $this->definitionClassName = 'Boomgo\\Builder\\Definition';
    }

    /**
     * Define the parser instance
     *
     * @param ParserInterface $parser
     */
    public function setParser(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Return parser instance
     *
     * @return ParserInterface
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * Define the key/attribute formatter instance
     *
     * @param FormatterInterface $formatter
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Return the key/attribute formatter instance
     *
     * @return FormatterInterface
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * Define the map classname
     * 
     * @param string $mapClassName
     */
    public function setMapClassName($mapClassName)
    {
        $this->mapClassName = $mapClassName;
    }

    /**
     * Return the map classname
     * 
     * @return string
     */
    public function getMapClassName()
    {
        return $this->mapClassName;
    }

    /**
     * Define the definition classname
     * 
     * @param string $definitionClassName
     */
    public function setDefinitionClassName($definitionClassName)
    {
        $this->definitionClassName = $definitionClassName;
    }

    /**
     * Return the definition classname
     * 
     * @return string
     */
    public function getDefinitionClassName()
    {
        return $this->definitionClassName;
    }

    /**
     * Build Map(s) for an array of file
     *
     * @param array $files
     *
     * @return array $processed
     */
    public function build(array $files)
    {
        $processed = array();

        foreach ($files as $file) {
            if ($this->parser->supports($file)) {
                $metadata = $this->parser->parse($file);
                $map = $this->buildMap($metadata);

                $processed[$map->getClass()] = $map;
            }
        }

        return $processed;
    }

    /**
     * Build a Map
     *
     * @param array $metadata
     *
     * @return Map
     */
    protected function buildMap(array $metadata)
    {
        $className = $this->getMapClassName();
        $map = new $className($metadata['class']);

        foreach ($metadata['definitions'] as $metadataDefinition) {
            $definition = $this->buildDefinition($metadataDefinition);
            $map->addDefinition($definition);
        }

        return $map;
    }

    /**
     * Build a Definition
     *
     * @param array $metadata
     *
     * @return Definition
     */
    protected function buildDefinition(array $metadata)
    {
        if (!isset($metadata['attribute']) && !isset($metadata['key'])) {
            throw new \RuntimeException('Invalid metadata should provide an attribute or a key');
        }

        // @TODO Rethink this hacky method cause I hate annotation ?
        if (!isset($metadata['key'])) {
            $metadata['key'] = $this->formatter->toMongoKey($metadata['attribute']);
        } elseif (!isset($metadata['attribute'])) {
            $metadata['attribute'] = $this->formatter->toPhpAttribute($metadata['key']);
        }

        $metadata['accessor'] = $this->formatter->getPhpAccessor($metadata['attribute']);
        $metadata['mutator'] = $this->formatter->getPhpMutator($metadata['attribute']);

        $className = $this->getDefinitionClassName();
        $definition = new $className($metadata);

        return $definition;
    }
}