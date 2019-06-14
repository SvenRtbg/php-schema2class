<?php
namespace Helmich\Schema2Class\Generator\Property;

class DateProperty extends AbstractPropertyInterface
{
    public static function canHandleSchema($schema)
    {
        return isset($schema->type)
            && isset($schema->format)
            && $schema->type === "string"
            && $schema->format === "date-time";
    }

    public function isComplex()
    {
        return true;
    }

    public function convertJSONToType($inputVarName = 'input')
    {
        $key = $this->key;
        return "\$$key = new \\DateTime(\${$inputVarName}['$key']);";
    }

    public function cloneProperty()
    {
        $key = $this->key;
        return "\$this->$key = clone \$this->$key;";
    }

    public function typeAnnotation()
    {
        return "\\DateTime";
    }

    public function typeHint($phpVersion)
    {
        return "\\DateTime";
    }

}
