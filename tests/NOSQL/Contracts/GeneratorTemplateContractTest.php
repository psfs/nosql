<?php

namespace NOSQL\Test\Contracts;

use PHPUnit\Framework\TestCase;

final class GeneratorTemplateContractTest extends TestCase
{
    public function testApiTemplateIncludesAttributeMetadataContract(): void
    {
        $template = file_get_contents(__DIR__ . '/../../Templates/generator/api.php.twig');

        self::assertIsString($template);
        self::assertStringContainsString("use PSFS\\base\\types\\helpers\\attributes\\Api;", $template);
        self::assertStringContainsString("#[Api('{{ model }}')]", $template);
    }

    public function testApiBaseTemplateIncludesAttributeMetadataContract(): void
    {
        $template = file_get_contents(__DIR__ . '/../../Templates/generator/api.base.php.twig');

        self::assertIsString($template);
        self::assertStringContainsString('use PSFS\\base\\types\\helpers\\attributes\\HttpMethod;', $template);
        self::assertStringContainsString('use PSFS\\base\\types\\helpers\\attributes\\Route;', $template);
        self::assertStringContainsString("#[HttpMethod('GET')]", $template);
        self::assertStringContainsString("#[Route('/{__DOMAIN__}/Api/{__API__}')]", $template);
        self::assertStringNotContainsString('/APi/', $template);
    }
}
