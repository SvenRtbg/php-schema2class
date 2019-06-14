<?php
namespace Helmich\Schema2Class\Generator\Property;

class MixedProperty extends AbstractPropertyInterface
{
    public static function canHandleSchema($schema)
    {
        return true;
    }

    public function typeAnnotation()
    {
        return "mixed";
    }

    public function typeHint($phpVersion)
    {
        return null;
    }

}
