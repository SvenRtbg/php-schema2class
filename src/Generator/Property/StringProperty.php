<?php
namespace Helmich\Schema2Class\Generator\Property;

class StringProperty extends AbstractPropertyInterface
{
    public static function canHandleSchema($schema)
    {
        return isset($schema->type) && $schema->type === "string";
    }

    public function typeAnnotation()
    {
        return "string";
    }

    public function typeHint($phpVersion)
    {
        if ($phpVersion === 5) {
            return null;
        }

        return "string";
    }

}
