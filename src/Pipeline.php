<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync;

use Jeytekdev\ApiSync\Config\Config;
use Jeytekdev\ApiSync\Doc\ApidocBlockReader;
use Jeytekdev\ApiSync\Doc\AttributeDocReader;
use Jeytekdev\ApiSync\Doc\Yii2AuthDocReader;
use Jeytekdev\ApiSync\Export\BrunoExporter;
use Jeytekdev\ApiSync\Export\CollectionExporterInterface;
use Jeytekdev\ApiSync\Export\InsomniaExporter;
use Jeytekdev\ApiSync\Export\PostmanExporter;
use Jeytekdev\ApiSync\Extractor\ApidocRouteExtractor;
use Jeytekdev\ApiSync\Extractor\AttributeRouteExtractor;
use Jeytekdev\ApiSync\Extractor\LaravelPatternExtractor;
use Jeytekdev\ApiSync\Extractor\RouteExtractorInterface;
use Jeytekdev\ApiSync\Extractor\Yii2Config;
use Jeytekdev\ApiSync\Extractor\Yii2PatternExtractor;
use Jeytekdev\ApiSync\Support\ApidocDefineRegistry;
use Jeytekdev\ApiSync\Support\PsrAutoloadResolver;
use Jeytekdev\ApiSync\Support\Yii2AuthResolver;
use Jeytekdev\ApiSync\Sync\CollectionMerger;

/**
 * Wires the default extractors/doc readers/exporters together and runs
 * scan -> export -> merge for the requested formats. This is what both
 * CLI commands (generate, check) drive.
 */
final class Pipeline
{
    /**
     * @return CollectionExporterInterface[] indexed by name()
     */
    public static function exporters(): array
    {
        $exporters = [new PostmanExporter(), new InsomniaExporter(), new BrunoExporter()];

        $indexed = [];
        foreach ($exporters as $exporter) {
            $indexed[$exporter->name()] = $exporter;
        }

        return $indexed;
    }

    /**
     * @return RouteExtractorInterface[]
     */
    public static function extractors(?Config $config = null): array
    {
        $psrResolver = $config !== null ? PsrAutoloadResolver::forProjectRoot($config->projectRoot) : null;
        $yii2Config = Yii2Config::discover($config?->projectRoot ?? '', $psrResolver);

        return [
            new AttributeRouteExtractor(),
            new ApidocRouteExtractor(),
            new Yii2PatternExtractor($yii2Config),
            new LaravelPatternExtractor(),
        ];
    }

    public static function scanner(?Config $config = null): Scanner
    {
        $psrResolver = $config !== null ? PsrAutoloadResolver::forProjectRoot($config->projectRoot) : null;
        $defines = new ApidocDefineRegistry();

        return new Scanner(
            self::extractors($config),
            // Lowest priority first: attribute reader is applied last, so it wins field-by-field.
            [new Yii2AuthDocReader(new Yii2AuthResolver($psrResolver)), new ApidocBlockReader($defines), new AttributeDocReader()],
            $defines,
        );
    }

    /**
     * @param string[] $formats
     * @return array<string, array<string, string>> format => (relative path => merged content)
     */
    public static function run(Config $config, array $formats): array
    {
        $collection = self::scanner($config)->scan($config->source, $config->name, $config->baseUrl);
        $merger = new CollectionMerger($config->prune);
        $exporters = self::exporters();

        $result = [];
        foreach ($formats as $format) {
            if (!isset($exporters[$format])) {
                continue;
            }

            $generated = $exporters[$format]->export($collection, $config->environmentName ?? $config->name);
            $outputDir = rtrim($config->out, '/') . '/' . $format;
            $result[$format] = $merger->merge($format, $generated, $outputDir);
        }

        return $result;
    }
}
