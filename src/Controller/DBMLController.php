<?php

namespace Bauerdot\LaravelDbml\Controller;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Bauerdot\LaravelDbml\Traits\DBMLSyntaxTraits;
use Bauerdot\LaravelDbml\Traits\ModelCastAnalyzerTrait;


class DBMLController extends Controller
{
    use DBMLSyntaxTraits;
    use ModelCastAnalyzerTrait;
    use ModelCastAnalyzerTrait;

    /**
     * @var array
     */
    protected $options = [
        'custom_type' => null,
        'ignore_by_default' => true,
        'include_system' => false,
        'no_ignore' => false,
        'ignore_presets' => ['system'],
        'config' => null,
        'only_tables' => null,
    ];
    
    /**
     * @param array|null $options
     */
    public function __construct($options = null)
    {
        if (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        } elseif ($options !== null) {
            // For backward compatibility, assume it's custom_type
            $this->options['custom_type'] = $options;
        }
        
        /*if ($this->options['custom_type'] != null){
            foreach($this->options['custom_type'] as $ct => $key) {
                DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping($ct, $key);
            }
        }*/
    }

    /**
     *
     */
    private function getColumns($table_name, $type)
    {
        $columnInfo = [];
        if (!empty($table_name)) {
            $instance = Schema::getColumns ($table_name);
            foreach($instance as $tableColumn){
                if($type === "artisan"){
                    $columnInfo[] = "name : {$tableColumn['name']}\n" . "type : {$tableColumn['type']}\n";
                }
                if($type === "array"){
                    $special = [];
                    if($this->isPrimaryKey ($tableColumn['name'],$table_name) === "yes"){
                        $special[] = "pk";
                    }
                    if($this->isUniqueKey ($tableColumn['name'],$table_name) === "yes"){
                        $special[] = "unique";
                    }
                    $length = '';
                    if (preg_match('/.+\(([0-9]+)\)/', $tableColumn['type'], $matches)) {
                        $length = $matches[1];
                    }

                    // Check for schema information from model cast attributes
                    $jsonSchema = null;
                    $model = $this->findModelForTable($table_name);
                    
                    if ($model && config('laravel-dbml.document_casts', true)) {
                        $casts = $this->getModelCasts($model['instance']);
                        
                        if (isset($casts[$tableColumn['name']])) {
                            $castType = $casts[$tableColumn['name']];
                            
                            // Check if it's a cast type we should document
                            $documentedTypes = config('laravel-dbml.document_cast_types', ['json', 'array', 'object', 'collection']);
                            $shouldDocument = in_array(strtolower(str_replace(':', '', $castType)), $documentedTypes);
                            
                            if ($shouldDocument || $this->isSpatieDataObject($castType)) {
                                $jsonSchema = $this->analyzeJsonStructure($model['instance'], $tableColumn['name'], $castType);
                            }
                        }
                    }
                    
                    $columnInfo[] = [
                        "name" => $tableColumn['name'],
                        "type" => $tableColumn['type'],
                        "special" => $special,
                        "note" => isset($tableColumn['comment']) ? $tableColumn['comment'] : '',
                        "default_value" => isset($tableColumn['default']) ? $tableColumn['default'] : null,
                        "is_nullable" => (isset($tableColumn['nullable']) && $tableColumn['nullable'] ? "yes" : "no"),
                        "length" => $length,
                        "json_schema" => $jsonSchema
                    ];
                }
            }
        }
        return $columnInfo;
    }

    /**
     *
     */
    private function getForeignKey($table_name, $type)
    {
        $columnInfo = [];
        if (!empty($table_name)) {
            $instance = Schema::getForeignKeys ($table_name);
            foreach($instance as $tableFK){
                $fromColumns = implode(" | ",$tableFK['columns']);
                $toColumns = implode(" | ",$tableFK['foreign_columns']);
                if($type === "artisan"){
                    $columnInfo[] = "[{$table_name}][{$fromColumns}] -> "."[$toColumns] of [{$tableFK['foreign_table']}]";
                }
                if($type === "array"){
                    $columnInfo[] = [
                        "from"=>$table_name,
                        "name"=>$fromColumns,
                        "to"=>$toColumns,
                        "table"=>$tableFK['foreign_table']
                    ];
                }
            }
        }
        return $columnInfo;
    }

    /**
     *
     */
    private function getIndexes($table_name, $type)
    {
        $columnInfo = [];
        if (!empty($table_name)) {
            $instance = Schema::getIndexes ($table_name);
            foreach($instance as $tableIndex){
                $unique = $tableIndex['unique'] ? "yes" : "no";
                $primary = $tableIndex['primary'] ? "yes" : "no";
                if($type === "artisan"){
                    $columns = implode(" | ",$tableIndex['columns']);
                    $columnInfo[] = "name : {$tableIndex['name']}\n"."columns : {$columns}\n"."unique : {$unique}\n"."primary : {$primary}\n";
                }
                if($type === "array"){
                    $columnInfo[] = ["name"=>$tableIndex['name'],"columns"=>$tableIndex['columns'],"unique"=>$unique,"primary"=>$primary,"table"=>$table_name];
                }
            }
        }
        return $columnInfo;
    }

    /**
     *
     */
    private function isPrimaryKey($column, $table_name){
        try {
            $primaryKeyInstance = Schema::getIndexes($table_name);
            
            if (empty($primaryKeyInstance)) {
                return "no";
            }
            
            // Check if column is part of any primary key
            foreach($primaryKeyInstance as $tableIndex){
                // Check if this is a primary key index
                if (!isset($tableIndex['primary'])) {
                    continue;
                }
                
                if ($tableIndex['primary'] && isset($tableIndex['columns']) && is_array($tableIndex['columns'])) {
                    // Check if column is part of this primary key's columns
                    if (in_array($column, $tableIndex['columns'])) {
                        return "yes";
                    }
                }
            }
            
            return "no";
        } catch (Exception $e) {
            error_log("Error checking primary key for column '$column' in table '$table_name': " . $e->getMessage());
            return "no";
        }
    }

    /**
     *
     */
    private function isUniqueKey($column, $table_name){
        try {
            $uniqueKeyInstance = Schema::getIndexes($table_name);
            
            if (empty($uniqueKeyInstance)) {
                return "no";
            }
            
            // Check if column is part of any unique key
            foreach($uniqueKeyInstance as $tableIndex){
                // Check if this is a unique key index
                if (!isset($tableIndex['unique'])) {
                    continue;
                }
                
                if ($tableIndex['unique'] && isset($tableIndex['columns']) && is_array($tableIndex['columns'])) {
                    // Check if column is part of this unique key's columns
                    if (in_array($column, $tableIndex['columns'])) {
                        return "yes";
                    }
                }
            }
            
            return "no";
        } catch (Exception $e) {
            error_log("Error checking unique key for column '$column' in table '$table_name': " . $e->getMessage());
            return "no";
        }
    }
    /**
     *
     */
    public function getDatabaseTable($type){
        try {
            $tableName = Schema::getTables();
            $data = [];
            
            if(!$tableName || !is_array($tableName)) {
                error_log("No tables found in the database or Schema::getTables() returned invalid format");
                return [];
            }
            
            // If we're filtering tables
            if ($this->options['no_ignore'] !== true && $this->shouldIgnoreSystemTables()) {
                $tableName = $this->filterSystemTables($tableName);
            }
            
            if($tableName){
                if($type === "artisan"){
                    foreach($tableName as $tb){
                        // Validate table entry has required fields
                        if (!isset($tb['name'])) {
                            throw new Exception("Table entry missing 'name' key: " . print_r($tb, true));
                        }
                        
                        $data[] = [
                            "table_name" => $tb['name'],
                            "columns" => implode("\n",$this->getColumns ($tb['name'],$type)),
                            "foreign_key" => implode("\n",$this->getForeignKey ($tb['name'],$type)),
                            "indexes"=> implode("\n",$this->getIndexes ($tb['name'],$type)),
                            "comment"=> isset($tb['comment']) ? $tb['comment'] : ''
                        ];
                    }
                    return $data;
                }
                
                if($type === "array"){
                    foreach($tableName as $tb){
                        // Validate table entry has required fields
                        if (!isset($tb['name'])) {
                            throw new Exception("Table entry missing 'name' key: " . print_r($tb, true));
                        }
                        
                        // Get columns with proper error handling
                        $columns = $this->getColumns($tb['name'], $type);
                        if ($columns === false || $columns === null) {
                            throw new Exception("Failed to get columns for table '{$tb['name']}'");
                        }
                        
                        $data[] = [
                            "table_name" => $tb['name'],
                            "columns" => $columns,
                            "foreign_key" => $this->getForeignKey($tb['name'], $type),
                            "indexes" => $this->getIndexes($tb['name'], $type),
                            "comment" => isset($tb['comment']) ? $tb['comment'] : ''
                        ];
                }
                return $data;
            }
        }
        return $data;
        } catch (Exception $e) {
            // Log the error or handle it as needed
            print_r("Error getting database tables: " . $e->getMessage());
            return [];
        }
    }

    public function getDatabasePlatform()
    {
        $db = env('DB_CONNECTION');
        $dbname = env('DB_DATABASE');
        
        // For SQLite, if DB_DATABASE is not specified, use a default name
        if ($db === 'sqlite' && empty($dbname)) {
            $dbname = 'sqlite_database';
        }
        
        return $this->projectName($dbname,$db);
    }

    /**
     */
    public function parseToDBML()
    {
        try{
            $table = $this->getDatabaseTable ("array");
            
            // Check if table data is available
            if (empty($table)) {
                return "// No tables found in the database.\n// Make sure your database connection is configured properly.\n";
            }
            
            // Add debug logging
            error_log("DBML Parse: Found " . count($table) . " tables to process.");
            
            $syntax = $this->getDatabasePlatform();
            foreach($table as $info){
                // Add validation to prevent "Undefined array key" errors
                if (!isset($info['table_name'])) {
                    throw new Exception("Table entry is missing 'table_name' key: " . print_r($info, true));
                }
                
                if($info['table_name']){
                    $syntax .= $this->table ($info['table_name']) . $this->start ();
                    
                    // Validate columns exist
                    if (!isset($info['columns']) || !is_array($info['columns'])) {
                        throw new Exception("Missing or invalid 'columns' for table '{$info['table_name']}'");
                    }
                    
                    foreach($info['columns'] as $col){
                        // Validate required column attributes and provide defaults for missing ones
                        if (!isset($col['name'])) {
                            throw new Exception("Column missing 'name' in table '{$info['table_name']}': " . print_r($col, true));
                        }
                        
                        // Use default values for optional attributes if they're missing
                        $colName = $col['name'];
                        $colType = isset($col['type']) ? $col['type'] : 'string';
                        $colSpecial = isset($col['special']) ? $col['special'] : [];
                        $colNote = isset($col['note']) ? $col['note'] : '';
                        $colIsNullable = isset($col['is_nullable']) ? $col['is_nullable'] : 'yes';
                        $colDefaultValue = $col['default_value'] ?? null;
                        $colJsonSchema = isset($col['json_schema']) ? $col['json_schema'] : null;
                        
                        $syntax .= $this->column($colName, $colType, $colSpecial, $colNote, $colIsNullable, $colDefaultValue, '', $colJsonSchema);
                    }
                    
                    if(isset($info['comment']) && $info['comment']){
                      $syntax .= "\n\tNote: '".$info['comment']."'\n";
                    }
                    
                    // Add cast documentation if enabled
                    if (config('laravel-dbml.document_casts', true)) {
                        $castDocumentation = $this->documentCastsForTable($info['table_name']);
                        if ($castDocumentation) {
                            $syntax .= $castDocumentation . "\n";
                        }
                    }
                    
                    if(isset($info['indexes']) && $info['indexes']){
                        $syntax .= $this->index () . $this->start ();
                        foreach($info['indexes'] as $index){
                            $type = "";
                            if(isset($index['primary']) && $index['primary'] === "yes"){
                                $type = "pk";
                            }else if(isset($index['unique']) && $index['unique'] === "yes"){
                                $type = "unique";
                            }
                            
                            if (!isset($index['columns'])) {
                                throw new Exception("Index missing 'columns' in table '{$info['table_name']}': " . print_r($index, true));
                            }
                            
                            $syntax .= $this->indexesKey ($index['columns'],$type);
                        }
                        $syntax .= "\t".$this->end();
                    }
                    
                    $syntax .= $this->end ();
                    
                    if(isset($info['foreign_key']) && $info['foreign_key']){
                        foreach ($info['foreign_key'] as $fk){
                            // Validate foreign key attributes
                            if (!isset($fk['from'])) {
                                throw new Exception("Foreign key missing 'from' in table '{$info['table_name']}': " . print_r($fk, true));
                            }
                            if (!isset($fk['name'])) {
                                throw new Exception("Foreign key missing 'name' in table '{$info['table_name']}': " . print_r($fk, true));
                            }
                            if (!isset($fk['table'])) {
                                throw new Exception("Foreign key missing 'table' in table '{$info['table_name']}': " . print_r($fk, true));
                            }
                            if (!isset($fk['to'])) {
                                throw new Exception("Foreign key missing 'to' in table '{$info['table_name']}': " . print_r($fk, true));
                            }
                            
                            $syntax .= $this->foreignKey ($fk['from'],$fk['name'],$fk['table'],$fk['to'])."\n";
                        }
                    }
                }
            }
            return $syntax."\n";
        } catch(Exception $e){
            $errorMessage = $e->getMessage();
            $errorDetails = "Error in " . $e->getFile() . " on line " . $e->getLine() . ": " . $errorMessage;
            error_log($errorDetails);
            
            // Return the error as usable DBML content instead of throwing an exception
            return "// Error generating DBML: {$errorMessage}\n// Please check your database schema or connection settings.\n";
        } catch(\Error $e) {
            $errorMessage = $e->getMessage();
            $errorDetails = "PHP Error in " . $e->getFile() . " on line " . $e->getLine() . ": " . $errorMessage;
            error_log($errorDetails);
            
            return "// PHP Error occurred: {$errorMessage}\n// In file: {$e->getFile()} on line {$e->getLine()}\n";
        }
    }
    
    /**
     * Check if we should ignore tables based on our configuration
     *
     * @return bool
     */
    protected function shouldIgnoreSystemTables()
    {
        // If --no-ignore flag is set, don't ignore any tables
        if ($this->options['no_ignore'] === true) {
            return false;
        }
        
        // If --only-tables is specified, we're already filtering to specific tables
        if (!empty($this->options['only_tables'])) {
            return true;
        }
        
        // If --include-system flag is set, don't ignore system tables
        if ($this->options['include_system'] === true) {
            return false;
        }
        
        // Otherwise use the default setting
        return $this->options['ignore_by_default'] === true;
    }
    
    /**
     * Filter out system tables from the tables list
     *
     * @param array $tables
     * @return array
     */
    protected function filterSystemTables($tables)
    {
        // First check the only_tables option
        if (!empty($this->options['only_tables'])) {
            return array_filter($tables, function($table) {
                if (!isset($table['name'])) {
                    return false; // Skip if there's no name
                }
                
                $tableName = strtolower($table['name']);
                
                // Keep only the tables specified in the only_tables option
                foreach ($this->options['only_tables'] as $onlyTable) {
                    if (strtolower($onlyTable) === $tableName) {
                        return true;
                    }
                    
                    // Check for wildcard pattern
                    if (strpos($onlyTable, '*') !== false) {
                        $pattern = '/^' . str_replace('*', '.*', $onlyTable) . '$/i';
                        if (preg_match($pattern, $table['name'])) {
                            return true;
                        }
                    }
                }
                
                return false;
            });
        }
        
        // Get tables to ignore from active presets
        $allIgnoredTables = [];
        $presets = $this->options['ignore_presets'];
        
        // Get all presets from config
        $configPresets = config('laravel-dbml.ignore_presets', []);
        
        // Process each active preset
        foreach ($presets as $preset) {
            if (isset($configPresets[$preset]) && is_array($configPresets[$preset])) {
                $allIgnoredTables = array_merge($allIgnoredTables, $configPresets[$preset]);
            }
        }
        
        // If we have config, merge any additional system tables from there
        if (isset($this->options['config']['ignore_presets']) && is_array($this->options['config']['ignore_presets'])) {
            foreach ($this->options['ignore_presets'] as $preset) {
                if (isset($this->options['config']['ignore_presets'][$preset]) && is_array($this->options['config']['ignore_presets'][$preset])) {
                    $allIgnoredTables = array_merge($allIgnoredTables, $this->options['config']['ignore_presets'][$preset]);
                }
            }
        }
        
        // Add any additional ignored tables from config
        $ignoredTables = config('laravel-dbml.ignored_tables', []);
        
        if (isset($this->options['config']['ignored_tables']) && is_array($this->options['config']['ignored_tables'])) {
            $ignoredTables = array_merge($ignoredTables, $this->options['config']['ignored_tables']);
        }
        
        // Combine all ignored tables
        $allIgnoredTables = array_merge($allIgnoredTables, $ignoredTables);
        
        return array_filter($tables, function($table) use ($allIgnoredTables) {
            if (!isset($table['name'])) {
                return true; // Don't filter if there's no name
            }
            
            // Check if table name is in the ignore list
            foreach ($allIgnoredTables as $ignoredTable) {
                // Direct match
                if (strtolower($table['name']) === strtolower($ignoredTable)) {
                    return false;
                }
                
                // Pattern match (using * as wildcard)
                if (strpos($ignoredTable, '*') !== false) {
                    $pattern = '/^' . str_replace('*', '.*', $ignoredTable) . '$/i';
                    if (preg_match($pattern, $table['name'])) {
                        return false;
                    }
                }
            }
            
            return true;
        });
    }
}
