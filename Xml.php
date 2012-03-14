<?php

/**
 * Temporary file provided @jubianchi to generate a clover-like xml report
 *
 * @see https://gist.github.com/2024820
 */

namespace jubianchi\atoum\report\fields\runner\coverage;

use
    mageekguy\atoum,
    mageekguy\atoum\locale,
    mageekguy\atoum\report,
    mageekguy\atoum\template,
    mageekguy\atoum\exceptions,
    mageekguy\atoum\cli\prompt,
    mageekguy\atoum\cli\colorizer
;

/**
 * Xml class report
 *
 * @author Julien Bianchi
 */
class Xml extends report\fields\runner\coverage
{
    protected $adapter = null;
    protected $projectName = '';
    protected $destinationDirectory = null;
    protected $baseDir = null;
    protected $loc = 0;
    protected $cloc = 0;
    protected $methods = 0;

    /**
     * Construct
     *
     * @param string   $projectName
     * @param string   $destinationDirectory
     * @param string   $baseDir
     * @param adapater $adapter
     * @param string   $locale
     */
    public function __construct($projectName, $destinationDirectory, $baseDir, atoum\adapter $adapter = null, locale $locale = null)
    {
        parent::__construct($locale);

        $this->setProjectName($projectName)
            ->setDestinationDirectory($destinationDirectory)
            ->setAdapter($adapter ?: new atoum\adapter());

        $this -> baseDir = $baseDir;
    }

    /**
     * Define the project name
     *
     * @param string $projectName
     *
     * @return Xml
     */
    public function setProjectName($projectName)
    {
        $this->projectName = (string) $projectName;

        return $this;
    }

    /**
     * Return the project name
     *
     * @return string
     */
    public function getProjectName()
    {
        return $this->projectName;
    }

    /**
     * Define the output directory
     *
     * @param string $path
     *
     * @return Xml
     */
    public function setDestinationDirectory($path)
    {
        $this->destinationDirectory = (string) $path;

        return $this;
    }

    /**
     * Return the outpout directory
     *
     * @return string
     */
    public function getDestinationDirectory()
    {
        return $this->destinationDirectory;
    }

    /**
     * Define the adapater
     *
     * @param atoum\adapter $adapter
     *
     * @return Xml
     */
    public function setAdapter(atoum\adapter $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Return the adapter
     *
     * @return adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Build an xml coverage node
     *
     * @param cstring $class
     * @param string  $file
     * @param array   $coverage
     *
     * @return string
     */
    private function getClassXml($class, $file, array $coverage)
    {
        $methods = count($coverage);

        $lcount = 0;
        $covered = 0;
        $xml = '';

        foreach ($coverage as $lines) {
            foreach ($lines as $lnum => $line) {
                ++$lcount;

                if ($line === 1) {
                    $xml .= "\t\t\t" . '<line num="'. $lnum . '" type="stmt" count="' . $line . '"/>' . PHP_EOL;
                    ++$covered;
                }
            }
        }

        $body = "\t\t" . '<file name="' . $file . '">' . PHP_EOL
              . "\t\t\t" .'<class name="' . $class . '">' . PHP_EOL
              . "\t\t\t\t" . '<metrics methods="' . $methods . '" coveredmethods="' . $methods . '" statements="' . $lcount . '" coveredstatements="' . $covered . '" elements="' . ($lcount + $methods) . '" coveredelements="' . ($covered + $methods) . '"/>' . PHP_EOL
              . "\t\t\t" . '</class>' . PHP_EOL
              . '%s'
              . "\t\t\t" . '<metrics loc="' . $lcount . '" ncloc="' . $covered . '" classes="1" methods="' . $methods . '" coveredmethods="' . $methods . '" statements="' . $lcount . '" coveredstatements="' . $covered . '" elements="' . ($lcount + $methods) . '" coveredelements="' . ($covered + $methods) . '"/>' . PHP_EOL
              . "\t\t" . '</file>' . PHP_EOL;

        $xml = !empty($xml) ? sprintf($body, $xml) : '';

        $this->incrementLoc($lcount)
            ->incrementCoveredLoc($covered)
            ->incrementMethod($covered);

        return $xml;
    }

    /**
     * Increment LOC metric
     *
     * @param int $count
     *
     * @return Xml
     */
    public function incrementLoc($count)
    {
        $this -> loc += $count;

        return $this;
    }

    /**
     * Increment covered LOC metric
     *
     * @param int $count
     *
     * @return Xml
     */
    public function incrementCoveredLoc($count)
    {
        $this -> cloc += $count;

        return $this;
    }

    /**
     * Increment method counter
     *
     * @param int $count
     *
     * @return Xml
     */
    public function incrementMethod($count)
    {
        $this -> methods += $count;

        return $this;
    }

    /**
     * Return the xml report
     *
     * @return string
     */
    public function __toString()
    {
        $project = $this -> getProjectName();
        $timestamp = time();

        $body = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="$timestamp" atoum="3.3.1">
    <project name="$project" timestamp="$timestamp">
%s
    </project>
</coverage>
XML;

        $xml = '';
        $methods = $this->coverage->getMethods();

        foreach ($this->coverage->getClasses() as $class => $file) {
            $xml .= $this->getClassXml($class, $file, $methods[$class]);
        }
        $xml .= "\t\t" . '<metrics files="' . count($this->coverage->getClasses()) . '" loc="' . $this -> loc . '" ncloc="' . $this -> cloc . '" classes="' . count($this->coverage->getClasses()) . '" methods="' . $this -> methods . '" coveredmethods="' . $this -> methods . '" statements="' . $this -> loc . '" coveredstatements="' . $this -> cloc . '" elements="' . ($this -> loc + $this -> methods) . '" coveredelements="' . ($this -> cloc + $this -> methods) . '"/>';
        $xml = sprintf($body, $xml);

        $this->adapter->file_put_contents($this->destinationDirectory, $xml);

        return '';
    }
}