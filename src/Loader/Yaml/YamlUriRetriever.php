<?php

namespace Helmich\Schema2Class\Loader\Yaml;

use JsonSchema\Uri\UriRetriever;
use Symfony\Component\Yaml\Yaml;

class YamlUriRetriever extends UriRetriever
{
    private $yamlCache = [];

    /**
     * Fetch a schema from the given URI, json-decode it and return it.
     * Caches schema objects.
     *
     * @param string $fetchUri Absolute URI
     *
     * @return object JSON schema object
     */
    protected function loadSchema($fetchUri)
    {
        if (isset($this->yamlCache[$fetchUri])) {
            return $this->yamlCache[$fetchUri];
        }

        $uriRetriever = $this->getUriRetriever();
        $contents = $uriRetriever->retrieve($fetchUri);
        // find correct YAML mime type for: $this->confirmMediaType($uriRetriever, $fetchUri);
        $jsonSchema = Yaml::parse($contents);

        $this->yamlCache[$fetchUri] = $jsonSchema;

        return $jsonSchema;
    }
}
