<?php
namespace Helmich\Schema2Class\Loader;

use JsonSchema\SchemaStorage;
use Helmich\Schema2Class\Loader\Yaml\YamlUriRetriever;

class SchemaLoader
{
    const YAML = 'yaml';
    const JSON = 'json';
    const ROOT_SCHEMA = 'root';

    /**
     * @param string $filename
     * @return mixed|object
     * @throws LoadingException
     */
    public function loadSchema($filename)
    {
        if (!file_exists($filename)) {
            throw new LoadingException($filename, "file does not exist");
        }

        $format = $this->getFileFormat($filename);

        if ($format === self::YAML) {
            $schemaStorage = new SchemaStorage(new YamlUriRetriever());
        } else {
            throw new LoadingException($filename, 'unrecognized file format');
        }

        $id = 'file:///' . strtr(realpath($filename), ['\\' => '/']);
        $schemaStorage->addSchema(SchemaStorage::INTERNAL_PROVIDED_SCHEMA_URI, ['$ref' => $id]);
        $schema = $schemaStorage->getSchema(SchemaStorage::INTERNAL_PROVIDED_SCHEMA_URI);

        return $schema;
    }

    private function getFileFormat($filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }
}
