<?php

namespace Helmich\Schema2Class\Generator\Property;

use Helmich\Schema2Class\Generator\GeneratorRequest;
use Helmich\Schema2Class\Generator\SchemaToClass;
use PHPUnit\Framework\TestCase;

class IntersectPropertyTest extends TestCase
{

    /** @var IntersectProperty */
    private $underTest;

    /** @var GeneratorRequest|\Prophecy\Prophecy\ObjectProphecy */
    private $generatorRequest;

    public function testCanHandleSchema()
    {
        assertTrue(IntersectProperty::canHandleSchema((object) ['allOf' => []]));

        assertFalse(IntersectProperty::canHandleSchema([]));
    }

    protected function setUp()
    {
        $this->generatorRequest = $this->prophesize(GeneratorRequest::class);
        $key = 'myPropertyName';
        $this->underTest = new IntersectProperty($key, ['allOf' => []], $this->generatorRequest->reveal());
    }

    public function testIsComplex()
    {
        assertTrue($this->underTest->isComplex());
    }

    public function testConvertJsonToType()
    {
        $this->generatorRequest->getTargetClass()->willReturn('Foo');

        $underTest = new IntersectProperty('myPropertyName', ['allOf' => []], $this->generatorRequest->reveal());

        $result = $underTest->convertJSONToType('variable');

        $expected = <<<'EOCODE'
$myPropertyName = FooMyPropertyName::buildFromInput($variable['myPropertyName']);
EOCODE;

        assertSame($expected, $result);
    }

    public function testConvertTypeToJson()
    {
        $result = $this->underTest->convertTypeToJSON('variable');

        $expected = <<<'EOCODE'
$variable['myPropertyName'] = $this->myPropertyName->toJson();
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
        $this->generatorRequest->getTargetNamespace()->willReturn('BarNs');

        $underTest = new IntersectProperty('myPropertyName', ['allOf' => []], $this->generatorRequest->reveal());

        assertSame('FooMyPropertyName', $underTest->typeAnnotation());
        assertSame('\\BarNs\\FooMyPropertyName', $underTest->typeHint(7));
        assertSame('\\BarNs\\FooMyPropertyName', $underTest->typeHint(5));
    }


    public function provideTestSchema()
    {
        return [
            'empty allOf' => [
                (object) ['allOf' => []],
                (object) ['required' => [], 'properties' => (object) []]
            ],
            'required' => [
                (object) ['allOf' => [(object) ['required' => ['foo']], (object) ['required' => ['bar']]]],
                (object) ['required' => ['foo', 'bar'], 'properties' => (object) []]
            ],
            'properties' => [
                (object) ['allOf' => [
                    (object) ['properties' => (object) ['foo' => (object) ['type' => 'int']]],
                    (object) ['properties' => (object) ['bar' => (object) ['type' => 'date-time']]]
                ]],
                (object) ['required' => [], 'properties' => (object) ['foo' => (object) ['type' => 'int'], 'bar' => (object) ['type' => 'date-time']]]
            ],
            'oneOf inside' => [
                (object) ['allOf' => [
                    (object) ['oneOf' => [
                        (object) ['required' => ['foo'], 'properties' => (object) ['foo' => (object) ['type' => 'int']]],
                        (object) ['required' => ['bar', 'foo'], 'properties' => (object) ['bar' => (object) ['type' => 'date-time'], 'foo' => (object) ['type' => 'string']]]
                    ]]
                ]],
                (object) ['required' => ['foo'], 'properties' => (object) ['bar' => (object) ['type' => 'date-time'], 'foo' => (object) ['type' => 'string']]]
            ],
            'anyOf inside' => [
                (object) ['allOf' => [
                    (object) ['anyOf' => [
                        (object) ['required' => ['foo'], 'properties' => (object) ['foo' => (object) ['type' => 'int']]],
                        (object) ['required' => ['bar'], 'properties' => (object) ['bar' => (object) ['type' => 'date-time']]]
                    ]]
                ]],
                (object) ['required' => [], 'properties' => (object) ['bar' => (object) ['type' => 'date-time'], 'foo' => (object) ['type' => 'int']]]
            ],
        ];
    }

    /**
     * @dataProvider provideTestSchema
     */
    public function testGenerateSubTypes($schema, $subschema)
    {

        $this->generatorRequest->withSchema($subschema)->willReturn($this->generatorRequest->reveal());
        $this->generatorRequest->withClass('MyPropertyName')->willReturn($this->generatorRequest->reveal());
        $this->generatorRequest->getTargetClass()->willReturn('');

        $underTest = new IntersectProperty('myPropertyName', $schema, $this->generatorRequest->reveal());

        $schemaToClass = $this->prophesize(SchemaToClass::class);

        $underTest->generateSubTypes($schemaToClass->reveal());

        $schemaToClass->schemaToClass($this->generatorRequest->reveal())->shouldHaveBeenCalled();
    }
}
