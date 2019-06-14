<?php

namespace Helmich\Schema2Class\Generator\Property;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\SchemaToClass;
use PHPUnit\Framework\TestCase;

class UnionPropertyTest extends TestCase
{

    /** @var UnionProperty */
    private $underTest;

    /** @var GeneratorRequest|\Prophecy\Prophecy\ObjectProphecy */
    private $generatorRequest;

    public function testCanHandleSchema()
    {
        assertTrue(UnionProperty::canHandleSchema((object) ['anyOf' => []]));
        assertTrue(UnionProperty::canHandleSchema((object) ['oneOf' => []]));
    }


    protected function setUp()
    {
        $this->generatorRequest = $this->prophesize(GeneratorRequest::class);
        $key = 'myPropertyName';
        $this->underTest = new UnionProperty($key, (object) ['anyOf' => [(object) ['properties' => (object) ['subFoo1' => (object) ['type' => 'string']]], (object) ['properties' => (object) ['subFoo2' => (object) ['type' => 'string']]]]], $this->generatorRequest->reveal());
    }

    public function testIsComplex()
    {
        assertTrue($this->underTest->isComplex());
    }

    public function testConvertJsonToType()
    {
        $this->generatorRequest->getTargetClass()->willReturn('Foo');

        $underTest = new UnionProperty('myPropertyName', (object) ['anyOf' => [(object) ['properties' => (object) ['subFoo1' => (object) ['type' => 'string']]], (object) ['properties' => (object) ['subFoo2' => (object) ['type' => 'string']]]]], $this->generatorRequest->reveal());

        $result = $underTest->convertJSONToType('variable');

        $expected = <<<'EOCODE'
if (FooMyPropertyNameAlternative1::validateInput($variable['myPropertyName'], true)) {
    $myPropertyName = FooMyPropertyNameAlternative1::buildFromInput($variable['myPropertyName']);
} else if (FooMyPropertyNameAlternative2::validateInput($variable['myPropertyName'], true)) {
    $myPropertyName = FooMyPropertyNameAlternative2::buildFromInput($variable['myPropertyName']);
} else {
    $myPropertyName = $variable['myPropertyName'];
}
EOCODE;

        assertSame($expected, $result);
    }

    public function testConvertTypeToJson()
    {
        $this->generatorRequest->getTargetClass()->willReturn('Foo');

        $underTest = new UnionProperty('myPropertyName', (object) ['anyOf' => [(object) ['properties' => (object) ['subFoo1' => (object) ['type' => 'string']]], (object) ['properties' => (object) ['subFoo2' => (object) ['type' => 'string']]]]], $this->generatorRequest->reveal());

        $result = $underTest->convertTypeToJSON('variable');

        $expected = <<<'EOCODE'
if ($this instanceof FooMyPropertyNameAlternative1) {
    $variable['myPropertyName'] = $this->myPropertyName->toJson();
}
if ($this instanceof FooMyPropertyNameAlternative2) {
    $variable['myPropertyName'] = $this->myPropertyName->toJson();
}
EOCODE;

        assertSame($expected, $result);
    }

    public function testCloneProperty()
    {
        $expected = <<<'EOCODE'
$this->myPropertyName = clone $this->myPropertyName;
EOCODE;
        assertSame($expected, $this->underTest->cloneProperty());
    }

    public function testGetAnnotationAndHintWithSimpleArray()
    {
        $this->generatorRequest->getTargetClass()->willReturn('Foo');

        $underTest = new UnionProperty('myPropertyName', (object) ['anyOf' => [(object) ['properties' => (object) ['subFoo1' => (object) ['type' => 'string']]], (object) ['properties' => (object) ['subFoo2' => (object) ['type' => 'string']]]]], $this->generatorRequest->reveal());

        assertSame('FooMyPropertyNameAlternative1|FooMyPropertyNameAlternative2', $underTest->typeAnnotation());
        assertSame(null, $underTest->typeHint(7));
        assertSame(null, $underTest->typeHint(5));
    }

    public function provideTestSchema()
    {
        return [
            'oneOf inside' => [
                (object) ['oneOf' => [
                    (object) ['required' => ['foo'], 'properties' => (object) ['foo' => (object) ['type' => 'int']]],
                    (object) ['required' => ['bar', 'foo'], 'properties' => (object) ['bar' => (object) ['type' => 'date-time'], 'foo' => (object) ['type' => 'string']]]
                ]],
            ],
            'anyOf inside' => [
                (object) ['anyOf' => [
                    (object) ['required' => ['foo'], 'properties' => (object) ['foo' => (object) ['type' => 'int']]],
                    (object) ['required' => ['bar'], 'properties' => (object) ['bar' => (object) ['type' => 'date-time']]]
                ]],
            ],
        ];
    }

    /**
     * @dataProvider provideTestSchema
     */
    public function testGenerateSubTypes($schema)
    {
        if (isset($schema->oneOf)) {
            $subschemas = $schema->oneOf;
        } elseif (isset($schema->anyOf)) {
            $subschemas = $schema->anyOf;
        }

        $this->generatorRequest->getTargetClass()->willReturn('');

        foreach ($subschemas as $i => $subschema) {
            $this->generatorRequest->withSchema($subschema)->willReturn($this->generatorRequest->reveal());
            $this->generatorRequest->withClass('MyPropertyNameAlternative'.($i+1))->willReturn($this->generatorRequest->reveal());
        }

        $underTest = new UnionProperty('myPropertyName', $schema, $this->generatorRequest->reveal());

        $schemaToClass = $this->prophesize(SchemaToClass::class);

        $underTest->generateSubTypes($schemaToClass->reveal());

        $schemaToClass->schemaToClass($this->generatorRequest->reveal())->shouldHaveBeenCalled();
    }

}
