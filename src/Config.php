<?php namespace Maer\Config;

use UnexpectedValueException;
use Maer\Config\Readers;

class Config implements ConfigInterface
{
    /**
     * @var array
     */
    protected $files = [];

    /**
     * @var array
     */
    protected $conf  = [];

    protected $readers = [
        'php'   => null,
        'json'  => null,
        'ini'   => null,
    ];


    /**
     * {@inheritdoc}
     */
    public function __construct(array $files = array())
    {
        $this->readers['php']  = new Readers\ArrayReader;
        $this->readers['json'] = new Readers\JsonReader;
        $this->readers['ini']  = new Readers\IniReader;

        if ($files) {
            $this->load($files);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function get($key = null, $default = null)
    {
        if (!$key) {
            return $default;
        }

        $conf =& $this->conf;
        $keys = explode('.', $key);

        foreach ($keys as $test) {
            $direct = implode('.', $keys);

            // Check for a direct match, containing dot
            if (is_array($conf) && array_key_exists($direct, $conf)) {
                return $conf[$direct];
            }

            if (!is_array($conf) || !array_key_exists($test, $conf)) {
                // No hit, return the default
                return $default;
            }

            $conf =& $conf[$test];
            array_shift($keys);
        }

        return $conf;
    }


    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $conf =& $this->conf;

        $segments = explode('.', $key);

        while (count($segments) > 1) {
            $segment = array_shift($segments);
            if (!isset($conf[$segment]) || !is_array($conf[$segment])) {
                $conf[$segment] = array();
            }
            $conf =& $conf[$segment];
        }

        return $conf[array_shift($segments)] = $value;
    }


    /**
     * {@inheritdoc}
     */
    public function push($key, $value)
    {
        $items = $this->get($key);
        if (!is_array($items)) {
            throw new UnexpectedValueException("Expected the target to be array, got " . gettype($items));
        }

        $items[] = $value;
        $this->set($key, $items);
    }


    /**
     * {@inheritdoc}
     */
    public function override(array $array)
    {
        $this->merge($array);
    }


    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        $conf =& $this->conf;
        $keys = explode('.', $key);

        foreach ($keys as $test) {
            $direct = implode('.', $keys);

            // Check for a direct match, containing dot
            if (is_array($conf) && array_key_exists($direct, $conf)) {
                return true;
            }

            if (!is_array($conf) || !array_key_exists($test, $conf)) {
                return false;
            }

            $conf =& $conf[$test];
            array_shift($keys);
        }

        return true;
    }


    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return $this->exists($key);
    }


    /**
     * {@inheritdoc}
     */
    public function load($files, $forceReload = false)
    {
        if (!is_array($files)) {
            // Make it an array so we can use the same code
            $files = array($files);
        }

        foreach ($files as $file) {
            if ((array_key_exists($file, $this->files) && !$forceReload)
                || !is_file($file) || !is_readable($file)) {
                // It's already loaded, or doesn't exist, so let's skip it
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (!array_key_exists($ext, $this->readers)) {
                throw new \Exception("No reader found for the extension '{$ext}'");
            }

            $conf = $this->readers[$ext]->read($file);

            if ($conf && is_array($conf)) {
                // We're only interested if it is a non-empty array
                $this->override($conf);
                $this->files[$file] = true;
            }

            unset($conf);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function isLoaded($file)
    {
        return array_key_exists($file, $this->files);
    }


    /**
     * Recursivly merge the array to the existing collection
     *
     * @param  array $array
     */
    protected function merge(array $array)
    {
        $this->conf = array_replace_recursive($this->conf, $array);
    }
}
