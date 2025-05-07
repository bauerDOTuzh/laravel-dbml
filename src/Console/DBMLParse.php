<?php

namespace Bauerdot\LaravelDbml\Console;

use Bauerdot\LaravelDbml\Config\ConfigManager;
use Bauerdot\LaravelDbml\Controller\DBMLController;
use Doctrine\DBAL\Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DBMLParse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dbml:parse 
                            {--dbdocs : Generate output for DBDocs}
                            {--custom : Use custom type mapping}
                            {--include-system : Include Laravel system tables (migrations, cache, etc.)}
                            {--no-ignore : Disable all table ignores}
                            {--ignore-preset=* : Use predefined ignore presets (system, spatie-permissions, telescope)}
                            {--config= : Path to external configuration file}
                            {--only=* : Only parse specific tables (comma-separated list or multiple values)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Artisan Parse Database Schema to DBML';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws Exception
     */
    public function handle()
    {
        $options = [
            'custom_type' => null,
            'ignore_by_default' => config('laravel-dbml.ignore_by_default', true),
            'include_system' => $this->option('include-system'),
            'no_ignore' => $this->option('no-ignore'),
            'ignore_presets' => $this->processIgnorePresets(),
            'config' => null,
            'only_tables' => $this->processOnlyTablesOption()
        ];
        
        // Process custom type option
        if($this->option("custom") != null){
            try {
                $customTypePath = storage_path() . "/app/custom_type.json";
                if (!file_exists($customTypePath)) {
                    $this->error("Custom type file not found: {$customTypePath}");
                    return 1;
                }
                $file = json_decode(file_get_contents($customTypePath), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("Invalid JSON in custom_type.json: " . json_last_error_msg());
                    return 1;
                }
                $options['custom_type'] = $file;
            } catch (\Exception $e) {
                $this->error("Error loading custom type: " . $e->getMessage());
                return 1;
            }
        }
        
        // Process config file option
        if ($configPath = $this->option('config')) {
            try {
                // Use the ConfigManager to parse and validate the config file
                $configPath = $this->resolveConfigPath($configPath);
                
                $this->info("Loading configuration from: {$configPath}");
                $externalConfig = ConfigManager::parseConfigFile($configPath);
                
                // Merge with our options
                if (isset($externalConfig['ignore_system'])) {
                    $options['ignore_system'] = $externalConfig['ignore_system'];
                }
                
                if (isset($externalConfig['custom_type']) && is_array($externalConfig['custom_type'])) {
                    $options['custom_type'] = $externalConfig['custom_type'];
                }
                
                $options['config'] = $externalConfig;
            } catch (\Exception $e) {
                $this->error("Error loading config file: " . $e->getMessage());
                return 1;
            }
        }
        
        try {
            // Enable error reporting to catch the exact location of the error
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            $db = new DBMLController($options);
            
            $artisan = $db->parseToDBML();
            
            // Check if the result starts with an error comment
            if (is_string($artisan) && strpos($artisan, '// Error generating DBML') === 0) {
                $this->error(trim(str_replace('// ', '', $artisan)));
                return 1;
            } elseif (is_string($artisan) && strpos($artisan, '// PHP Error occurred') === 0) {
                $this->error(trim(str_replace('// ', '', $artisan)));
                return 1;
            }
            
            if (empty($artisan)) {
                $this->error("Failed to generate DBML content (empty result)");
                return 1;
            }
            
            $database = env('DB_DATABASE');
            
            // For SQLite connections, provide a default database name if not specified
            $connection = env('DB_CONNECTION');
            if (empty($database)) {
                if ($connection === 'sqlite') {
                    $database = 'sqlite_database';
                    $this->info("Using default name 'sqlite_database' for SQLite connection");
                } else {
                    $this->error("DB_DATABASE environment variable is not set");
                    return 1;
                }
            }
            
            $rand = Str::random(8);
            $path = "dbml";
            $fileName = "{$path}/dbml_{$database}_".$rand.".txt";
            
            // Make sure the directory exists
            if (!Storage::exists($path)) {
                Storage::makeDirectory($path);
            }
            
            Storage::put($fileName, $artisan);
            $getPath = Storage::path($fileName);
            $this->info("Created! File Path: ".$getPath);
            
            if($this->option("dbdocs") != null){
                $this->warn("Please Install dbdocs (npm install -g dbdocs) before run command");
                $this->info("Now you can run with command: dbdocs build $getPath --project=$database --password=$rand");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Resolve config file path
     *
     * @param string $path
     * @return string
     */
    protected function resolveConfigPath($path)
    {
        // If path is absolute, use it directly
        if (file_exists($path)) {
            return $path;
        }
        
        // Try relative to project root
        $projectRoot = base_path();
        $projectPath = $projectRoot . '/' . $path;
        if (file_exists($projectPath)) {
            return $projectPath;
        }
        
        // Try relative to config directory
        $configPath = config_path($path);
        if (file_exists($configPath)) {
            return $configPath;
        }
        
        // If not found, return the original path (which will cause an error when trying to read it)
        return $path;
    }
    
    /**
     * Process the --only option to get a list of tables to include
     *
     * @return array|null
     */
    protected function processOnlyTablesOption()
    {
        $onlyTables = $this->option('only');
        
        // If no --only option was provided, return null
        if (empty($onlyTables)) {
            return null;
        }
        
        $tables = [];
        
        // Process each value of the --only option
        foreach ($onlyTables as $tableList) {
            // If the value contains commas, split it into multiple table names
            if (strpos($tableList, ',') !== false) {
                $splitTables = array_map('trim', explode(',', $tableList));
                $tables = array_merge($tables, $splitTables);
            } else {
                $tables[] = trim($tableList);
            }
        }
        
        // Filter out any empty strings
        $tables = array_filter($tables, function ($table) {
            return !empty($table);
        });
        
        $this->info("Only processing tables: " . implode(', ', $tables));
        
        return !empty($tables) ? $tables : null;
    }
    
    /**
     * Process ignore presets option to get a list of active presets
     *
     * @return array
     */
    protected function processIgnorePresets()
    {
        // Get presets from the command line option
        $commandLinePresets = $this->option('ignore-preset');
        
        // If no presets are specified on the command line, use the default active presets from config
        if (empty($commandLinePresets)) {
            return config('laravel-dbml.active_presets', ['system']);
        }
        
        $presets = [];
        
        // Process each preset specified on the command line
        foreach ($commandLinePresets as $presetList) {
            // If the value contains commas, split it into multiple presets
            if (strpos($presetList, ',') !== false) {
                $splitPresets = array_map('trim', explode(',', $presetList));
                $presets = array_merge($presets, $splitPresets);
            } else {
                $presets[] = trim($presetList);
            }
        }
        
        // Filter out any empty strings and return unique presets
        $presets = array_filter($presets, function ($preset) {
            return !empty($preset);
        });
        
        if (!empty($presets)) {
            $this->info("Using ignore presets: " . implode(', ', $presets));
        }
        
        return array_unique($presets);
    }
}
