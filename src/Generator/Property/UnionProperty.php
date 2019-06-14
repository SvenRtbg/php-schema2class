<?php
namespace Helmich\Schema2Class\Generator\Property;

use Helmich\Schema2Class\Generator\GeneratorContext;
use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\SchemaToClass;

class UnionProperty extends AbstractPropertyInterface
{
    public static function canHandleSchema($schema)
    {
        return isset($schema->oneOf) || isset($schema->anyOf);
    }

    public function __construct($key, $schema, GeneratorRequest $generatorRequest)
    {
        if (isset($schema->anyOf)) {
            $schema->oneOf = $schema->anyOf;
            unset($schema->anyOf);
        }

        parent::__construct($key, $schema, $generatorRequest);
    }

    public function isComplex()
    {
        return true;
    }

    public function convertJSONToType($inputVarName = 'input')
    {
        $conversions = [];
        $def = $this->schema;
        $key = $this->key;

        foreach ($def->oneOf as $i => $subDef) {
            $propertyTypeName = $this->subTypeName($i);

            if ((isset($subDef->type) && $subDef->type === "object") || isset($subDef->properties)) {
                $conversions[] = ($i > 0 ? "else " : "") . "if ($propertyTypeName::validateInput(\${$inputVarName}['$key'], true)) {\n    \$$key = $propertyTypeName::buildFromInput(\${$inputVarName}['$key']);\n}";
            }
        }

        $conversions[] = "else {\n    \$$key = \${$inputVarName}['$key'];\n}";

        return str_replace("}\nelse", "} else", join("\n", $conversions));
    }

    public function convertTypeToJSON($outputVarName = 'output')
    {
        $conversions = [];
        $def = $this->schema;
        $key = $this->key;

        foreach ($def->oneOf as $i => $subDef) {
            $propertyTypeName = $this->subTypeName($i);

            if ((isset($subDef->type) && $subDef->type === "object") || isset($subDef->properties)) {
                $conversions[] = "if (\$this instanceof $propertyTypeName) {\n    \${$outputVarName}['$key'] = \$this->{$key}->toJson();\n}";
            }
        }

        return join("\n", $conversions);
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
        $def = $this->schema;

        foreach ($def->oneOf as $i => $subDef) {
            $propertyTypeName = $this->subTypeName($i);

            if ((isset($subDef->type) && $subDef->type === "object") || isset($subDef->properties)) {
                $generator->schemaToClass(
                    $this->generatorRequest
                        ->withSchema($subDef)
                        ->withClass($propertyTypeName)
                );
            }
        }
    }

    public function typeAnnotation()
    {
        $types = [];
        $def = $this->schema;

        foreach ($def->oneOf as $i => $subDef) {
            $propertyTypeName = $this->subTypeName($i);
            if ((isset($subDef->type) && $subDef->type === "object") || isset($subDef->properties)) {
                $types[] = $propertyTypeName;
            } else {
                $types[] = $this->phpPrimitiveForSchemaType($subDef)[0];
            }
        }

        return join("|", $types);
    }

    public function typeHint($phpVersion)
    {
        return null;
    }

    private function subTypeName($idx = 0)
    {
        return $this->generatorRequest->getTargetClass() . $this->capitalizedName . "Alternative" . ($idx + 1);
    }

}
