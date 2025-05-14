<?php

namespace Bauerdot\LaravelDbml\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionProperty;

trait ModelCastAnalyzerTrait
{
    /**
     * Find Laravel model that corresponds to a database table
     * 
     * @param string $tableName
     * @return array|null
     */
    protected function findModelForTable($tableName)
    {
        // Get models directory from config
        $modelsDir = config('laravel-dbml.models_dir', app_path('Models'));
        
        if (!File::exists($modelsDir)) {
            return null;
        }
        
        $models = [];
        
        // Recursive function to find all model files
        $findModelFiles = function($dir) use (&$findModelFiles) {
            $files = [];
            
            foreach (File::allFiles($dir) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
            
            return $files;
        };
        
        // Get all PHP files in models directory
        $modelFiles = $findModelFiles($modelsDir);
        
        // Parse each model file to find the one for this table
        foreach ($modelFiles as $modelFile) {
            // Extract namespace and class name from the file
            $namespace = $this->getNamespaceFromFile($modelFile);
            $className = $this->getClassNameFromFile($modelFile);
            
            if (!$namespace || !$className) {
                continue;
            }
            
            // Create fully qualified class name
            $fqcn = $namespace . '\\' . $className;
            
            // Check if class exists and is a model
            if (!class_exists($fqcn) || !is_subclass_of($fqcn, 'Illuminate\\Database\\Eloquent\\Model')) {
                continue;
            }
            
            try {
                $instance = new $fqcn();
                
                // Get table name from model
                $modelTable = $instance->getTable();
                
                // If table name matches, we found our model
                if ($modelTable === $tableName) {
                    return [
                        'class' => $fqcn,
                        'instance' => $instance
                    ];
                }
            } catch (\Exception $e) {
                // Skip models that cannot be instantiated
                continue;
            }
        }
        
        return null;
    }
    
    /**
     * Extract namespace from PHP file
     */
    protected function getNamespaceFromFile($filepath)
    {
        $content = file_get_contents($filepath);
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    /**
     * Extract class name from PHP file
     */
    protected function getClassNameFromFile($filepath)
    {
        $content = file_get_contents($filepath);
        if (preg_match('/class\s+(\w+)(?:\s+extends|\s+implements|\s*\{)/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    /**
     * Get cast attributes from a model
     * 
     * @param object $model
     * @return array
     */
    protected function getModelCasts($model)
    {
        if (!method_exists($model, 'getCasts')) {
            return [];
        }
        
        return $model->getCasts();
    }
    
    /**
     * Check if a class is a Spatie Data object
     * 
     * @param string $className
     * @return bool
     */
    protected function isSpatieDataObject($className)
    {
        // First check if the class exists as is
        if (class_exists($className)) {
            return is_subclass_of($className, 'Spatie\\LaravelData\\Data');
        }
        
        // If class doesn't exist, try with the configured namespace
        $namespace = config('laravel-dbml.spatie_data_namespace', 'App\\ValueObjects');
        $fullyQualifiedClassName = $namespace . '\\' . class_basename($className);
        
        if (class_exists($fullyQualifiedClassName)) {
            return is_subclass_of($fullyQualifiedClassName, 'Spatie\\LaravelData\\Data');
        }
        
        return false;
    }
    
    /**
     * Get the fully qualified class name for a Spatie Data object
     * 
     * @param string $className
     * @return string|null
     */
    protected function getSpatieDataObjectClassName($className)
    {
        // First check if the class exists as is
        if (class_exists($className) && is_subclass_of($className, 'Spatie\\LaravelData\\Data')) {
            return $className;
        }
        
        // If class doesn't exist, try with the configured namespace
        $namespace = config('laravel-dbml.spatie_data_namespace', 'App\\ValueObjects');
        $fullyQualifiedClassName = $namespace . '\\' . class_basename($className);
        
        if (class_exists($fullyQualifiedClassName) && is_subclass_of($fullyQualifiedClassName, 'Spatie\\LaravelData\\Data')) {
            return $fullyQualifiedClassName;
        }
        
        return null;
    }
    
    /**
     * Get schema information for a Spatie Data object
     * 
     * @param string $className
     * @return array|null
     */
    protected function getSpatieDataObjectSchema($className)
    {
        $fullyQualifiedClassName = $this->getSpatieDataObjectClassName($className);
        
        if (!$fullyQualifiedClassName) {
            return null;
        }
        
        try {
            $reflectionClass = new \ReflectionClass($fullyQualifiedClassName);
            $properties = [];
            
            // Get constructor parameters to understand the structure
            $constructor = $reflectionClass->getConstructor();
            
            if ($constructor) {
                $parameters = $constructor->getParameters();
                
                foreach ($parameters as $parameter) {
                    $type = null;
                    
                    // Try to get the parameter type
                    if ($parameter->hasType()) {
                        $paramType = $parameter->getType();
                        $typeName = $paramType instanceof \ReflectionNamedType ? $paramType->getName() : (string)$paramType;
                        $type = $typeName;
                        
                        // If it's nullable, mark it as such
                        if ($paramType->allowsNull()) {
                            $type = '?' . $type;
                        }
                    }
                    
                    // Store property information
                    $properties[$parameter->getName()] = [
                        'type' => $type,
                        'nullable' => $parameter->allowsNull(),
                        'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                        'has_default' => $parameter->isDefaultValueAvailable(),
                    ];
                }
            }
            
            return $properties;
        } catch (\Exception $e) {
            error_log("Error analyzing Spatie Data object {$className}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Analyze and document cast attributes for a table
     * 
     * @param string $tableName
     * @return string|null
     */
    protected function documentCastsForTable($tableName)
    {
        if (!config('laravel-dbml.document_casts', true)) {
            return null;
        }
        
        $model = $this->findModelForTable($tableName);
        
        if (!$model) {
            return null;
        }
        
        $casts = $this->getModelCasts($model['instance']);
        
        if (empty($casts)) {
            return null;
        }
        
        $documentation = "\n\tNote: '''Laravel Cast Attributes:";
        
        foreach ($casts as $attribute => $castType) {
            // Only document specific cast types
            $documentedTypes = config('laravel-dbml.document_cast_types', ['json', 'array', 'object', 'collection']);
            
            if (!in_array(strtolower(str_replace(':', '', $castType)), $documentedTypes)) {
                continue;
            }
            
            $documentation .= "\n\t\t$attribute ($castType)";
            
            // For JSON or array casts, try to determine the structure
            if (Str::contains($castType, ['json', 'array', 'object', 'collection'])) {
                $structure = $this->analyzeJsonStructure($model['instance'], $attribute);
                if ($structure) {
                    $documentation .= ":\n\t\t\t" . str_replace("\n", "\n\t\t\t", $structure);
                }
            }
        }
    
        // if documentation empty return noting
        if (trim($documentation) === trim("\n\tNote: '''Laravel Cast Attributes:")) {
            return null;
        }

        return $documentation . "'''";
    }
    
    /**
     * Analyze JSON structure for a model attribute
     * 
     * @param object $model
     * @param string $attribute
     * @param string $castType
     * @return string|null
     */
    protected function analyzeJsonStructure($model, $attribute, $castType = null)
    {
        $structure = null;
        
        // Check for Spatie Data object cast
        $casts = $this->getModelCasts($model);
        
        if (isset($casts[$attribute]) && !in_array($casts[$attribute], ['json', 'array', 'object', 'collection'])) {
            $castClassName = $casts[$attribute];
            
            // Check if it's a Spatie Data object
            if ($this->isSpatieDataObject($castClassName)) {
                $dataObjectSchema = $this->getSpatieDataObjectSchema($castClassName);
                if ($dataObjectSchema) {
                    $fullyQualifiedClassName = $this->getSpatieDataObjectClassName($castClassName);
                    return $this->formatSpatieDataObjectSchema($dataObjectSchema, $fullyQualifiedClassName);
                }
            }
        }
        
        // Check if model has any accessors or mutators for the attribute with json structure comments
        $reflection = new \ReflectionClass(get_class($model));
        
        // Look for accessor method with doc comments
        $accessorMethod = 'get' . Str::studly($attribute) . 'Attribute';
        if (method_exists($model, $accessorMethod)) {
            $method = $reflection->getMethod($accessorMethod);
            $docComment = $method->getDocComment();
            if ($docComment) {
                $structure = $this->extractJsonStructureFromDocComment($docComment);
            }
        }
        
        // Look for property with doc comments
        if (!$structure && $reflection->hasProperty($attribute)) {
            $property = $reflection->getProperty($attribute);
            $docComment = $property->getDocComment();
            if ($docComment) {
                $structure = $this->extractJsonStructureFromDocComment($docComment);
            }
        }
        
        return $structure;
    }
    
    /**
     * Format Spatie Data object schema as a string
     * 
     * @param array $schema
     * @param string $className
     * @return string
     */
    protected function formatSpatieDataObjectSchema($schema, $className)
    {
        $shortClassName = substr($className, strrpos($className, '\\') + 1);
        $result = "Spatie Data Object ($shortClassName): {\n";
        
        foreach ($schema as $property => $info) {
            $type = $info['type'] ?: 'mixed';
            $nullable = $info['nullable'] ? '(nullable)' : '';
            $result .= "  $property: $type $nullable";
            
            if ($info['has_default']) {
                $defaultValue = is_scalar($info['default']) ? var_export($info['default'], true) : 'default-value';
                $result .= " = $defaultValue";
            }
            
            $result .= "\n";
        }
        
        $result .= '}';
        return $result;
    }
    
    /**
     * Extract JSON structure from doc comment
     * 
     * @param string $docComment
     * @return string|null
     */
    protected function extractJsonStructureFromDocComment($docComment)
    {
        // Look for @json-structure tag in doc comment
        if (preg_match('/@json-structure\s+(.+?)(?=\s*\*\/|\s*@|\s*$)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
}
