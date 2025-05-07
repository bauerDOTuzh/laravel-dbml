<?php

namespace Bauerdot\LaravelDbml\Config;

use Illuminate\Support\Facades\File;
use Exception;

class ConfigManager
{
    /**
     * Parse and validate a configuration file
     * 
     * @param string $configPath
     * @return array
     * @throws \Exception
     */
    public static function parseConfigFile($configPath)
    {
        if (!File::exists($configPath)) {
            throw new Exception("Config file not found: {$configPath}");
        }
        
        // Get file extension
        $extension = File::extension($configPath);
        
        if ($extension === 'json') {
            $config = static::parseJsonConfig($configPath);
        } elseif ($extension === 'php') {
            $config = static::parsePhpConfig($configPath);
        } elseif (in_array($extension, ['yml', 'yaml'])) {
            $config = static::parseYamlConfig($configPath);
        } else {
            throw new Exception("Unsupported config file format: {$extension}. Must be json, php, or yaml");
        }
        
        // Validate required config keys
        static::validateConfig($config);
        
        return $config;
    }
    
    /**
     * Parse JSON config file
     * 
     * @param string $configPath
     * @return array
     * @throws \Exception
     */
    protected static function parseJsonConfig($configPath)
    {
        $content = File::get($configPath);
        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in config file: " . json_last_error_msg());
        }
        
        return $config;
    }
    
    /**
     * Parse PHP config file
     * 
     * @param string $configPath
     * @return array
     * @throws \Exception
     */
    protected static function parsePhpConfig($configPath)
    {
        // Include the file and expect it to return an array
        $config = include $configPath;
        
        if (!is_array($config)) {
            throw new Exception("PHP config file must return an array");
        }
        
        return $config;
    }
    
    /**
     * Parse YAML config file
     * 
     * @param string $configPath
     * @return array
     * @throws \Exception
     */
    protected static function parseYamlConfig($configPath)
    {
        // Check if yaml extension is loaded
        if (!function_exists('yaml_parse_file')) {
            throw new Exception("YAML extension not installed. Please install php-yaml or use JSON or PHP format.");
        }
        
        // Parse YAML file
        $config = yaml_parse_file($configPath);
        
        if ($config === false) {
            throw new Exception("Invalid YAML in config file");
        }
        
        return $config;
    }
    
    /**
     * Validate config structure
     * 
     * @param array $config
     * @return void
     * @throws \Exception
     */
    protected static function validateConfig($config)
    {
        // Check for required keys or set defaults
        if (!isset($config['system_tables'])) {
            $config['system_tables'] = config('laravel-dbml.system_tables', []);
        }
        
        if (!isset($config['ignored_tables'])) {
            $config['ignored_tables'] = [];
        }
        
        return $config;
    }
}
