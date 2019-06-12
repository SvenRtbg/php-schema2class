<?php

namespace Helmich\Schema2Class\Generator;

use Helmich\Schema2Class\Generator\Property\ArrayProperty;
use Helmich\Schema2Class\Generator\Property\DateProperty;
use Helmich\Schema2Class\Generator\Property\IntegerProperty;
use Helmich\Schema2Class\Generator\Property\IntersectProperty;
use Helmich\Schema2Class\Generator\Property\MixedProperty;
use Helmich\Schema2Class\Generator\Property\NestedObjectProperty;
use Helmich\Schema2Class\Generator\Property\OptionalPropertyDecorator;
use Helmich\Schema2Class\Generator\Property\PropertyCollection;
use Helmich\Schema2Class\Generator\Property\StringProperty;
use Helmich\Schema2Class\Generator\Property\UnionProperty;
use Helmich\Schema2Class\Writer\WriterInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\PropertyGenerator;

class SchemaToClass
{

    /** @var WriterInterface */
    private $writer;

    /** @var OutputInterface */
    private $output;

    /**
     * @param WriterInterface $writer
     * @return $this
     */
    public function setWriter(WriterInterface $writer)
    {
        $this->writer = $writer;
        return $this;
    }

    /**
     * @param OutputInterface $output
     * @return $this
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @param GeneratorRequest $generatorRequest
     * @param OutputInterface  $output
     * @throws GeneratorException
     */
    public function schemaToClass(GeneratorRequest $generatorRequest)
    {
        if (!$this->writer instanceof WriterInterface) {
            throw new \UnexpectedValueException('A file writer has not been set.');
        }

        if (!$this->output instanceof OutputInterface) {
            throw new \UnexpectedValueException('A console output has not been set.');
        }

        $schema = $generatorRequest->getSchema();
        $schemaProperty = new PropertyGenerator("schema", $schema, PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC);
        $schemaProperty->setDocBlock(new DocBlockGenerator(
            "Schema used to validate input for creating instances of this class",
            null,
            [new GenericTag("var", "array")]
        ));

        $properties = [$schemaProperty];
        $methods = [];

        if (!isset($schema["properties"])) {
            throw new GeneratorException("cannot generate class for types other than 'object'");
        }

        $propertiesFromSchema = new PropertyCollection();
        $propertyTypes = [
            IntersectProperty::class,
            UnionProperty::class,
            DateProperty::class,
            StringProperty::class,
            ArrayProperty::class,
            IntegerProperty::class,
            NestedObjectProperty::class,
            MixedProperty::class,
        ];

        // @todo references have to be resolved at this point
        foreach ($schema["properties"] as $key => $definition) {
            $isRequired = isset($schema["required"]) && in_array($key, $schema["required"]);

            foreach ($propertyTypes as $propertyType) {
                if ($propertyType::canHandleSchema($definition)) {
                    $this->output->writeln("building generator <info>$propertyType</info> for property <comment>$key</comment>");

                    $property = new $propertyType($key, $definition, $generatorRequest);

                    if (!$isRequired) {
                        $property = new OptionalPropertyDecorator($key, $property);
                    }

                    $propertiesFromSchema->add($property);

                    continue 2;
                }
            }
        }

        // @todo infinite loops have to be prevented at this point
        foreach ($propertiesFromSchema as $property) {
            $property->generateSubTypes($this);
        }

        $codeGenerator = new Generator($generatorRequest);

        $methods[] = $codeGenerator->generateConstructor($propertiesFromSchema);

        $properties = array_merge($properties, $codeGenerator->generateProperties($propertiesFromSchema));
        $methods = array_merge($methods, $codeGenerator->generateGetterMethods($propertiesFromSchema));
        $methods = array_merge($methods, $codeGenerator->generateSetterMethods($propertiesFromSchema));

        $methods[] = $codeGenerator->generateBuildMethod($propertiesFromSchema);
        $methods[] = $codeGenerator->generateToJSONMethod($propertiesFromSchema);
        $methods[] = $codeGenerator->generateValidateMethod($propertiesFromSchema);
        $methods[] = $codeGenerator->generateCloneMethod($propertiesFromSchema);

        $cls = new ClassGenerator(
            $generatorRequest->getTargetClass(),
            $generatorRequest->getTargetNamespace(),
            null,
            null,
            [],
            $properties,
            $methods,
            null
        );

        $file = new FileGenerator([
            "classes" => [$cls],
        ]);

        $content = $file->generate();

        // Do some corrections because the Zend code generation library is stupid.
        $content = preg_replace('/ : \\\\self/', ' : self', $content);
        $content = preg_replace('/\\\\'.preg_quote($generatorRequest->getTargetNamespace()).'\\\\/', '', $content);

        $this->writer->writeFile($generatorRequest->getTargetDirectory() . '/' . $generatorRequest->getTargetClass() . '.php', $content);
    }

}
