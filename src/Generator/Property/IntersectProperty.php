<?php
namespace Helmich\Schema2Class\Generator\Property;

use Helmich\Schema2Class\Generator\SchemaToClass;

class IntersectProperty extends AbstractPropertyInterface
{
    public static function canHandleSchema($schema)
    {
        return isset($schema->allOf);
    }

    public function isComplex()
    {
        return true;
    }

    public function convertJSONToType($inputVarName = 'input')
    {
        $key = $this->key;

        return "\$$key = {$this->subTypeName()}::buildFromInput(\${$inputVarName}['$key']);";
    }

    public function convertTypeToJSON($outputVarName = 'output')
    {
        $key = $this->key;

        return "\${$outputVarName}['$key'] = \$this->{$key}->toJson();";
    }

    public function cloneProperty()
    {
        $key = $this->key;

        return "\$this->$key = clone \$this->$key;";
    }

    /**
     * @param SchemaToClass    $generator
     * @throws \Helmich\Schema2Class\Generator\GeneratorException
     */
    public function generateSubTypes(SchemaToClass $generator)
    {
        $propertyTypeName = $this->subTypeName();
        $combined = $this->buildSchemaIntersect($this->schema->allOf);

        $generator->schemaToClass(
            $this->generatorRequest
                ->withSchema($combined)
                ->withClass($propertyTypeName)
        );
    }

    public function typeAnnotation()
    {
        return $this->subTypeName();
    }

    public function typeHint($phpVersion)
    {
        return "\\" . $this->generatorRequest->getTargetNamespace() . "\\" . $this->subTypeName();
    }

    private function subTypeName()
    {
        return $this->generatorRequest->getTargetClass() . $this->capitalizedName;
    }

    private function buildSchemaUnion(array $schemas)
    {
        $combined = new \stdClass();
        $combined->required = [];
        $combined->properties = new \stdClass();

        foreach ($schemas as $i => $schema) {
            $required = isset($schema->required) ? $schema->required : [];

            if ($i === 0) {
                $combined->required = $required;
            } else {
                foreach ($combined->required as $j => $req) {
                    if (!in_array($req, $required)) {
                        unset($combined->required[$j]);
                    }
                }
            }

            if (isset($schema->properties)) {
                foreach ($schema->properties as $name => $def) {
                    $combined->properties->{$name} = $def;
                }
            }
        }

        return $combined;
    }

    private function buildSchemaIntersect($schemas)
    {
        $combined = new \stdClass();
        $combined->required = [];
        $combined->properties = new \stdClass();

        foreach ($schemas as $schema) {
            if (isset($schema->oneOf)) {
                $schema = $this->buildSchemaUnion($schema->oneOf);
            }

            if (isset($schema->anyOf)) {
                $schema = $this->buildSchemaUnion($schema->anyOf);
            }

            if (isset($schema->required)) {
                $combined->required = array_unique(array_merge($combined->required, $schema->required));
            }

            if (isset($schema->properties)) {
                foreach ($schema->properties as $name => $def) {
                    $combined->properties->{$name} = $def;
                }
            }
        }

        return $combined;
    }
}
